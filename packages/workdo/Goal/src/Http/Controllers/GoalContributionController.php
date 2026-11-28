<?php

namespace Workdo\Goal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Goal\Events\CreateGoalContribution;
use Workdo\Goal\Events\DestroyGoalContribution;
use Workdo\Goal\Events\UpdateGoalContribution;
use Workdo\Goal\Http\Requests\StoreContributionRequest;
use Workdo\Goal\Http\Requests\UpdateContributionRequest;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalContribution;
use Workdo\Goal\Services\GoalService;
use Workdo\Goal\Traits\GoalControllerHelpers;

class GoalContributionController extends Controller
{
    use GoalControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-goal-contributions')) {
            return back()->with('error', __('Permission denied'));
        }

        $sortMap = [
            'contribution_date'   => 'contribution_date',
            'contribution_amount' => 'contribution_amount',
            'contribution_type'   => 'contribution_type',
            'reference_type'      => 'reference_type',
            'created_at'          => 'created_at',
        ];

        $contributions = GoalContribution::query()
            ->with(['goal'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-goal-contributions')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-goal-contributions')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->whereHas('goal', function ($q) {
                if (request('goal_name')) {
                    $q->where('goal_name', 'like', '%' . request('goal_name') . '%');
                }
            })
            ->when(request('goal_id'), fn($q) => $q->where('goal_id', request('goal_id')))
            ->when(request('contribution_type'), fn($q) => $q->where('contribution_type', request('contribution_type')))
            ->when(request('date_from'), fn($q) => $q->whereDate('contribution_date', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('contribution_date', '<=', request('date_to')));

        $contributions = $this->applySafeSort($contributions, $sortMap, 'latest')
            ->paginate($this->safePerPage(10))
            ->withQueryString();

        $goals = Goal::where('created_by', creatorId())
            ->where('status', 'active')
            ->select('id', 'goal_name')
            ->orderBy('goal_name')
            ->get();

        return Inertia::render('Goal/Contributions/Index', [
            'contributions' => $contributions,
            'goals'         => $goals,
        ]);
    }

    public function store(StoreContributionRequest $request)
    {
        if (!Auth::user()->can('create-goal-contributions')) {
            return redirect()->route('goal.contributions.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        $goalService = new GoalService();
        $contributionId = $goalService->addGoalContribution((int) $validated['goal_id'], $validated);

        if (!$contributionId) {
            return back()->with('error', __('Unable to record contribution. The goal target may already be reached.'));
        }

        $contribution = GoalContribution::find($contributionId);
        CreateGoalContribution::dispatch($request, $contribution);

        return redirect()->route('goal.contributions.index')->with('success', __('The contribution has been created successfully.'));
    }

    public function update(UpdateContributionRequest $request, GoalContribution $contribution)
    {
        if (!Auth::user()->can('edit-goal-contributions') || !$this->assertBelongsToTenant($contribution)) {
            return redirect()->route('goal.contributions.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $contribution, $request) {
            $goalService = new GoalService();
            $oldAmount = (float) $contribution->contribution_amount;
            $oldGoalId = (int) $contribution->goal_id;
            $newGoalId = (int) $validated['goal_id'];
            $newAmount = (float) $validated['contribution_amount'];

            $contribution->goal_id = $newGoalId;
            $contribution->contribution_date = $validated['contribution_date'];
            $contribution->contribution_amount = $newAmount;
            $contribution->contribution_type = $validated['contribution_type'];
            $contribution->reference_type = $validated['reference_type'] ?? 'manual';
            $contribution->reference_id = $validated['reference_id'] ?? null;
            $contribution->notes = \App\Support\HtmlSanitizer::sanitize($validated['notes'] ?? null) ?? '';
            $contribution->save();

            if ($oldGoalId !== $newGoalId) {
                // Refund old goal
                $oldGoal = Goal::find($oldGoalId);
                if ($oldGoal) {
                    $oldGoal->current_amount = max(0.0, (float) $oldGoal->current_amount - $oldAmount);
                    if ($oldGoal->status === 'completed' && $oldGoal->current_amount < (float) $oldGoal->target_amount) {
                        $oldGoal->status = 'active';
                    }
                    $oldGoal->save();
                    $goalService->rebalanceMilestonesFromCurrentAmount($oldGoal->id, (float) $oldGoal->current_amount);
                    $goalService->updateGoalTracking($oldGoal->id);
                }

                // Add to new goal
                $newGoal = Goal::find($newGoalId);
                if ($newGoal) {
                    $newGoal->current_amount = min((float) $newGoal->target_amount, (float) $newGoal->current_amount + $newAmount);
                    if ($newGoal->current_amount >= (float) $newGoal->target_amount) {
                        $newGoal->status = 'completed';
                    }
                    $newGoal->save();
                    $goalService->rebalanceMilestonesFromCurrentAmount($newGoal->id, (float) $newGoal->current_amount);
                    $goalService->updateGoalTracking($newGoal->id);
                }
            } else {
                // Same goal - apply diff
                $goal = Goal::find($newGoalId);
                if ($goal) {
                    $diff = $newAmount - $oldAmount;
                    $goal->current_amount = max(0.0, min((float) $goal->target_amount, (float) $goal->current_amount + $diff));
                    if ($goal->current_amount >= (float) $goal->target_amount) {
                        $goal->status = 'completed';
                    } elseif ($goal->status === 'completed' && $goal->current_amount < (float) $goal->target_amount) {
                        $goal->status = 'active';
                    }
                    $goal->save();
                    $goalService->rebalanceMilestonesFromCurrentAmount($goal->id, (float) $goal->current_amount);
                    $goalService->updateGoalTracking($goal->id);
                }
            }

            UpdateGoalContribution::dispatch($request, $contribution);
        });

        return back()->with('success', __('The contribution details are updated successfully.'));
    }

    public function destroy(GoalContribution $contribution)
    {
        if (!Auth::user()->can('delete-goal-contributions') || !$this->assertBelongsToTenant($contribution)) {
            return redirect()->route('goal.contributions.index')->with('error', __('Permission denied'));
        }

        DB::transaction(function () use ($contribution) {
            $goalService = new GoalService();
            $goal = Goal::find($contribution->goal_id);
            if ($goal) {
                $goal->current_amount = max(0.0, (float) $goal->current_amount - (float) $contribution->contribution_amount);
                if ($goal->status === 'completed' && $goal->current_amount < (float) $goal->target_amount) {
                    $goal->status = 'active';
                }
                $goal->save();
                $goalService->rebalanceMilestonesFromCurrentAmount($goal->id, (float) $goal->current_amount);
                $goalService->updateGoalTracking($goal->id);
            }

            DestroyGoalContribution::dispatch($contribution);
            $contribution->delete();
        });

        return redirect()->back()->with('success', __('The contribution has been deleted.'));
    }
}
