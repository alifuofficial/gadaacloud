<?php

namespace Workdo\Goal\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalContribution;

class GoalErpIntegrationService
{
    protected GoalService $goalService;

    public function __construct(GoalService $goalService)
    {
        $this->goalService = $goalService;
    }

    /**
     * Check if Account module is active and available.
     */
    protected function isAccountAvailable(): bool
    {
        if (function_exists('Module_is_active') && Module_is_active('Account')) {
            return true;
        }

        return class_exists(\Workdo\Account\Models\BankAccount::class);
    }

    /**
     * Check if BudgetPlanner module is active and available.
     */
    protected function isBudgetPlannerAvailable(): bool
    {
        if (function_exists('Module_is_active') && Module_is_active('BudgetPlanner')) {
            return true;
        }

        return class_exists(\Workdo\BudgetPlanner\Models\Budget::class);
    }

    /**
     * Synchronize a savings goal with an Ethiopian bank account reserve (CBE, Dashen, Telebirr, etc.).
     */
    public function syncFromBankAccount(Goal $goal): bool
    {
        if (!$this->isAccountAvailable() || !$goal->bank_account_id) {
            return false;
        }

        try {
            /** @var \Workdo\Account\Models\BankAccount|null $bankAccount */
            $bankAccount = \Workdo\Account\Models\BankAccount::find($goal->bank_account_id);
            if (!$bankAccount) {
                return false;
            }

            // Sync savings progress to bank balance if bank balance is higher than current amount
            $bankBalance = (float) ($bankAccount->opening_balance ?? 0.0);

            // Also sum posted journal debits minus credits to bank GL account if linked
            if (!empty($bankAccount->chart_account_id) && Schema::hasTable('journal_entry_items') && Schema::hasTable('journal_entries')) {
                $glBalance = (float) DB::table('journal_entry_items')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
                    ->where('journal_entry_items.account_id', $bankAccount->chart_account_id)
                    ->where('journal_entries.status', 'posted')
                    ->where('journal_entries.created_by', $goal->created_by)
                    ->selectRaw('SUM(debit_amount - credit_amount) as balance')
                    ->value('balance');

                if ($glBalance > 0) {
                    $bankBalance = $glBalance;
                }
            }

            if ($bankBalance > (float) $goal->current_amount) {
                $diff = $bankBalance - (float) $goal->current_amount;
                $this->goalService->addGoalContribution($goal->id, [
                    'contribution_date'   => now(),
                    'contribution_amount' => $diff,
                    'contribution_type'   => 'automatic',
                    'reference_type'      => 'bank_transaction',
                    'reference_id'        => $bankAccount->id,
                    'notes'               => "Automated sync from Bank Reserve: {$bankAccount->bank_name} ({$bankAccount->holder_name})",
                ]);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning("GoalErpIntegrationService::syncFromBankAccount failed for Goal #{$goal->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronize revenue goals with customer invoice collections.
     */
    public function syncRevenueFromPaidInvoices(Goal $goal): bool
    {
        if (!$this->isAccountAvailable() || $goal->goal_type !== 'revenue_target') {
            return false;
        }

        try {
            $startDate = Carbon::parse($goal->start_date)->startOfDay();
            $targetDate = Carbon::parse($goal->target_date)->endOfDay();

            if (!Schema::hasTable('customer_payments')) {
                return false;
            }

            // Check payments or customer invoices marked as paid
            $totalCollected = (float) DB::table('customer_payments')
                ->where('created_by', $goal->created_by)
                ->whereBetween('date', [$startDate, $targetDate])
                ->sum('amount');

            if ($totalCollected > (float) $goal->current_amount) {
                $diff = $totalCollected - (float) $goal->current_amount;
                $this->goalService->addGoalContribution($goal->id, [
                    'contribution_date'   => now(),
                    'contribution_amount' => $diff,
                    'contribution_type'   => 'automatic',
                    'reference_type'      => 'journal_entry',
                    'reference_id'        => null,
                    'notes'               => "Automated revenue sync from paid customer invoices ({$startDate->toDateString()} to {$targetDate->toDateString()})",
                ]);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning("GoalErpIntegrationService::syncRevenueFromPaidInvoices failed for Goal #{$goal->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Benchmark expense reduction goals against BudgetPlanner allocations.
     */
    public function getBudgetPlannerVariance(Goal $goal): ?array
    {
        if (!$this->isBudgetPlannerAvailable() || $goal->goal_type !== 'expense_reduction') {
            return null;
        }

        try {
            if (!$goal->account_id || !Schema::hasTable('budget_allocations')) {
                return null;
            }

            // Find matching budget allocation for this GL account
            $allocation = DB::table('budget_allocations')
                ->where('created_by', $goal->created_by)
                ->where('account_id', $goal->account_id)
                ->first();

            if (!$allocation) {
                return null;
            }

            $allocatedBudget = (float) ($allocation->allocated_amount ?? 0);
            $actualSpent = (float) ($allocation->spent_amount ?? 0);
            $savings = max(0.0, $allocatedBudget - $actualSpent);

            return [
                'allocated_budget' => $allocatedBudget,
                'actual_spent'     => $actualSpent,
                'budget_savings'   => $savings,
                'variance_pct'     => $allocatedBudget > 0 ? round(($savings / $allocatedBudget) * 100, 2) : 0.0,
            ];
        } catch (\Throwable $e) {
            Log::warning("GoalErpIntegrationService::getBudgetPlannerVariance failed for Goal #{$goal->id}: " . $e->getMessage());
            return null;
        }
    }
}
