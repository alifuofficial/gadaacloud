<?php

namespace Workdo\Goal\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalContribution;
use Workdo\Goal\Models\GoalMilestone;
use Workdo\Goal\Models\GoalTracking;

class GoalService
{
    /**
     * Auto-contribute from a posted Journal Entry to any matching active financial goals.
     */
    public function autoContributeFromJournalEntry($journalEntry): void
    {
        if (!$journalEntry || !isset($journalEntry->items)) {
            return;
        }

        $accountIds = $journalEntry->items->pluck('account_id')->filter()->unique();
        if ($accountIds->isEmpty()) {
            return;
        }

        $linkedGoals = Goal::whereIn('account_id', $accountIds)
            ->where('status', 'active')
            ->get();

        foreach ($linkedGoals as $goal) {
            $contributionAmount = $this->calculateContributionFromJournalEntry($goal, $journalEntry);
            if ($contributionAmount > 0) {
                $journalDate = $journalEntry->journal_date ?? now();
                $this->addGoalContribution($goal->id, [
                    'contribution_date'   => $journalDate,
                    'contribution_amount' => $contributionAmount,
                    'contribution_type'   => 'automatic',
                    'reference_type'      => 'journal_entry',
                    'reference_id'        => $journalEntry->id,
                    'notes'               => "Auto-contribution from Journal Entry #{$journalEntry->id}",
                ]);
            }
        }
    }

    /**
     * Calculate contribution amount from a journal entry based on standard double-entry accounting rules.
     */
    public function calculateContributionFromJournalEntry($goal, $journalEntry): float
    {
        $goalAccount = $goal->account;
        if (!$goalAccount) {
            return 0.0;
        }

        $contributionAmount = 0.0;

        foreach ($journalEntry->items as $item) {
            if ((int) $item->account_id !== (int) $goal->account_id) {
                continue;
            }

            $debit = (float) ($item->debit_amount ?? 0);
            $credit = (float) ($item->credit_amount ?? 0);

            switch ($goal->goal_type) {
                case 'savings':
                    // Cash/Bank Asset accounts are DEBIT normal: net debit increases savings!
                    if ($goalAccount->normal_balance === 'debit') {
                        $contributionAmount += ($debit - $credit);
                    }
                    // Equity/Reserve accounts are CREDIT normal: net credit increases savings!
                    elseif ($goalAccount->normal_balance === 'credit') {
                        $contributionAmount += ($credit - $debit);
                    }
                    break;

                case 'debt_reduction':
                    // Liability accounts are CREDIT normal: debit reduces the debt obligation!
                    if ($goalAccount->normal_balance === 'credit') {
                        $contributionAmount += ($debit - $credit);
                    }
                    break;

                case 'expense_reduction':
                    // Expense accounts are DEBIT normal: lower net debits compared to baseline
                    if ($goalAccount->normal_balance === 'debit') {
                        $contributionAmount += $this->calculateExpenseReduction($goal, $item, $journalEntry);
                    }
                    break;

                case 'revenue_target':
                    // Revenue accounts are CREDIT normal: net credit increases earned revenue!
                    if ($goalAccount->normal_balance === 'credit') {
                        $contributionAmount += ($credit - $debit);
                    }
                    break;
            }
        }

        return max(0.0, round($contributionAmount, 2));
    }

    /**
     * Calculate expense reduction savings against trailing 3-month baseline.
     */
    public function calculateExpenseReduction($goal, $journalItem, $journalEntry): float
    {
        $journalDate = Carbon::parse($journalEntry->journal_date ?? now());
        $baselineAmount = $this->getExpenseBaseline($goal->account_id, $journalDate);
        $currentExpense = (float) $journalItem->debit_amount - (float) $journalItem->credit_amount;
        $reduction = $baselineAmount - $currentExpense;

        return max(0.0, $reduction);
    }

    /**
     * Trailing 3-month average expense baseline for an account.
     */
    public function getExpenseBaseline($accountId, $currentDate): float
    {
        $startDate = $currentDate->copy()->subMonths(3);
        $endDate = $currentDate->copy()->subDay();

        $avgExpense = JournalEntryItem::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('journal_date', [$startDate, $endDate]);
            })
            ->selectRaw('AVG(debit_amount - credit_amount) as avg_amount')
            ->value('avg_amount');

        return (float) ($avgExpense ?: 100.0);
    }

    /**
     * Add a contribution to a goal wrapped in an atomic database transaction.
     */
    public function addGoalContribution(int $goalId, array $contributionData): ?int
    {
        return DB::transaction(function () use ($goalId, $contributionData) {
            $goal = Goal::lockForUpdate()->find($goalId);
            if (!$goal) {
                return null;
            }

            // Cap contribution so goal doesn't exceed target amount
            $remainingAmount = max(0.0, (float) $goal->target_amount - (float) $goal->current_amount);
            $actualContribution = min((float) $contributionData['contribution_amount'], $remainingAmount);

            if ($actualContribution <= 0.0) {
                return null; // Target already fully achieved
            }

            $contribution = new GoalContribution();
            $contribution->goal_id = $goalId;
            $contribution->contribution_date = $contributionData['contribution_date'] ?? now();
            $contribution->contribution_amount = $actualContribution;
            $contribution->contribution_type = $contributionData['contribution_type'] ?? 'manual';
            $contribution->reference_type = $contributionData['reference_type'] ?? 'manual';
            $contribution->reference_id = $contributionData['reference_id'] ?? null;
            $contribution->reversal_of_id = $contributionData['reversal_of_id'] ?? null;
            $contribution->notes = \App\Support\HtmlSanitizer::sanitize($contributionData['notes'] ?? null) ?? '';
            $contribution->creator_id = Auth::id() ?? $goal->creator_id;
            $contribution->created_by = creatorId() ?? $goal->created_by;
            $contribution->save();

            // Update goal current amount
            $goal->current_amount = min((float) $goal->target_amount, (float) $goal->current_amount + $actualContribution);

            if ($goal->current_amount >= (float) $goal->target_amount) {
                $goal->status = 'completed';
            }
            $goal->save();

            // Distribute across milestones
            $this->distributeContributionToMilestones($goalId, $actualContribution);

            // Record tracking entry
            $this->updateGoalTracking($goalId);

            return $contribution->id;
        });
    }

    /**
     * Reverse an existing goal contribution safely with full milestone roll-back.
     */
    public function reverseGoalContribution(int $contributionId, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($contributionId, $reason) {
            $contribution = GoalContribution::find($contributionId);
            if (!$contribution) {
                return false;
            }

            $goal = Goal::lockForUpdate()->find($contribution->goal_id);
            if (!$goal) {
                return false;
            }

            $reversalAmount = (float) $contribution->contribution_amount;

            // Update goal current amount
            $goal->current_amount = max(0.0, (float) $goal->current_amount - $reversalAmount);
            if ($goal->status === 'completed' && $goal->current_amount < (float) $goal->target_amount) {
                $goal->status = 'active';
            }
            $goal->save();

            // Rebalance milestones based on the updated goal amount
            $this->rebalanceMilestonesFromCurrentAmount($goal->id, (float) $goal->current_amount);

            // Record tracking entry
            $this->updateGoalTracking($goal->id);

            return true;
        });
    }

    /**
     * Update tracking metrics and calculate true pace run-rate.
     */
    public function updateGoalTracking(int $goalId): ?int
    {
        $goal = Goal::find($goalId);
        if (!$goal) {
            return null;
        }

        $previousTracking = GoalTracking::where('goal_id', $goalId)
            ->orderBy('tracking_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $previousAmount = $previousTracking ? (float) $previousTracking->current_amount : 0.0;
        $currentAmount = (float) $goal->current_amount;
        $targetAmount = (float) $goal->target_amount;
        $contributionAmount = round($currentAmount - $previousAmount, 2);

        // Progress percentage
        $progressPercentage = $targetAmount > 0
            ? min(100.0, round(($currentAmount / $targetAmount) * 100, 2))
            : 0.0;

        // Days remaining calculation
        $targetDate = Carbon::parse($goal->target_date);
        $startDate = Carbon::parse($goal->start_date);
        $now = Carbon::now();

        $daysRemaining = (int) $now->diffInDays($targetDate, false);
        $totalDays = max(1, (int) $startDate->diffInDays($targetDate, false));
        $daysElapsed = max(0, (int) $startDate->diffInDays($now, false));

        // Time elapsed percentage
        $timeElapsedPercentage = min(100.0, max(1.0, round(($daysElapsed / $totalDays) * 100, 2)));

        // Real pace evaluation: progress % / time elapsed %
        $paceRatio = $progressPercentage / $timeElapsedPercentage;

        if ($currentAmount >= $targetAmount) {
            $onTrackStatus = 'ahead';
        } elseif ($daysRemaining <= 0) {
            $onTrackStatus = 'critical';
        } elseif ($timeElapsedPercentage <= 10.0) {
            $onTrackStatus = 'on_track';
        } elseif ($paceRatio >= 1.10) {
            $onTrackStatus = 'ahead';
        } elseif ($paceRatio >= 0.90) {
            $onTrackStatus = 'on_track';
        } elseif ($paceRatio >= 0.60) {
            $onTrackStatus = 'behind';
        } else {
            $onTrackStatus = 'critical';
        }

        $projectedCompletionDate = $this->calculateProjectedCompletion($goal);

        $tracking = new GoalTracking();
        $tracking->goal_id = $goalId;
        $tracking->tracking_date = now();
        $tracking->previous_amount = $previousAmount;
        $tracking->contribution_amount = $contributionAmount;
        $tracking->current_amount = $currentAmount;
        $tracking->progress_percentage = $progressPercentage;
        $tracking->days_remaining = $daysRemaining;
        $tracking->projected_completion_date = $projectedCompletionDate;
        $tracking->on_track_status = $onTrackStatus;
        $tracking->creator_id = Auth::id() ?? $goal->creator_id;
        $tracking->created_by = creatorId() ?? $goal->created_by;
        $tracking->save();

        return $tracking->id;
    }

    /**
     * Forecast completion date using actual contribution velocity.
     */
    public function calculateProjectedCompletion(Goal $goal): ?Carbon
    {
        if ($goal->current_amount >= $goal->target_amount) {
            return Carbon::now();
        }

        $contributions = GoalContribution::where('goal_id', $goal->id)
            ->where('contribution_date', '>=', $goal->start_date)
            ->orderBy('contribution_date', 'asc')
            ->get();

        if ($contributions->count() < 2) {
            return $goal->target_date ? Carbon::parse($goal->target_date) : null;
        }

        $firstDate = Carbon::parse($contributions->first()->contribution_date);
        $lastDate = Carbon::parse($contributions->last()->contribution_date);
        $daysDiff = max(1, $firstDate->diffInDays($lastDate));

        $totalContribution = (float) $contributions->sum('contribution_amount');
        $dailyVelocity = $totalContribution / $daysDiff;

        if ($dailyVelocity <= 0.0001) {
            return null; // Stagnant progress
        }

        $remainingAmount = (float) $goal->target_amount - (float) $goal->current_amount;
        $daysNeeded = (int) ceil($remainingAmount / $dailyVelocity);

        return Carbon::now()->addDays($daysNeeded);
    }

    /**
     * Distribute an incoming contribution amount sequentially across pending milestones.
     */
    public function distributeContributionToMilestones(int $goalId, float $contributionAmount): void
    {
        $allMilestones = GoalMilestone::where('goal_id', $goalId)
            ->orderBy('target_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($allMilestones->isEmpty()) {
            return;
        }

        $remainingAmount = $contributionAmount;

        foreach ($allMilestones as $milestone) {
            if ($remainingAmount <= 0.0) {
                break;
            }

            $currentAchieved = (float) ($milestone->achieved_amount ?? 0);
            $target = (float) $milestone->target_amount;
            $needed = max(0.0, $target - $currentAchieved);

            if ($needed > 0.0) {
                $allocated = min($remainingAmount, $needed);
                $milestone->achieved_amount = round($currentAchieved + $allocated, 2);
                $remainingAmount -= $allocated;

                if ($milestone->achieved_amount >= $target) {
                    $milestone->status = 'achieved';
                    if (!$milestone->achieved_date) {
                        $milestone->achieved_date = now();
                    }
                } else {
                    $milestone->status = 'pending';
                }

                $milestone->save();
            }
        }
    }

    /**
     * Rebalance all milestones from a target achieved amount (e.g. after a contribution edit or delete).
     */
    public function rebalanceMilestonesFromCurrentAmount(int $goalId, float $totalCurrentAmount): void
    {
        $allMilestones = GoalMilestone::where('goal_id', $goalId)
            ->orderBy('target_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $runningAmount = $totalCurrentAmount;

        foreach ($allMilestones as $milestone) {
            $target = (float) $milestone->target_amount;

            if ($runningAmount >= $target) {
                $milestone->achieved_amount = $target;
                $milestone->status = 'achieved';
                if (!$milestone->achieved_date) {
                    $milestone->achieved_date = now();
                }
                $runningAmount -= $target;
            } elseif ($runningAmount > 0.0) {
                $milestone->achieved_amount = round($runningAmount, 2);
                $milestone->status = 'pending';
                $milestone->achieved_date = null;
                $runningAmount = 0.0;
            } else {
                $milestone->achieved_amount = 0.0;
                $milestone->status = 'pending';
                $milestone->achieved_date = null;
            }

            $milestone->save();
        }
    }
}
