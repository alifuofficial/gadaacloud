<?php

namespace Workdo\Goal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Goal\Events\CreateGoalMilestone;
use Workdo\Goal\Events\DestroyGoalMilestone;
use Workdo\Goal\Events\UpdateGoalMilestone;
use Workdo\Goal\Http\Requests\StoreMilestoneRequest;
use Workdo\Goal\Http\Requests\UpdateMilestoneRequest;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalMilestone;
use Workdo\Goal\Traits\GoalControllerHelpers;

class GoalMilestoneController extends Controller
{
    use GoalControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-goal-milestones')) {
            return back()->with('error', __('Permission denied'));
        }

        $sortMap = [
            'milestone_name'  => 'milestone_name',
            'target_amount'   => 'target_amount',
            'achieved_amount' => 'achieved_amount',
            'target_date'     => 'target_date',
            'status'          => 'status',
            'created_at'      => 'created_at',
        ];

        $milestones = GoalMilestone::query()
            ->with('goal')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-goal-milestones')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-goal-milestones')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('milestone_name'), fn($q) => $q->where('milestone_name', 'like', '%' . request('milestone_name') . '%'))
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->when(request('goal_id'), fn($q) => $q->where('goal_id', request('goal_id')))
            ->when(request('date_from'), fn($q) => $q->whereDate('target_date', '>=', request('date_from')))
            ->when(request('date_to'), fn($q) => $q->whereDate('target_date', '<=', request('date_to')));

        $milestones = $this->applySafeSort($milestones, $sortMap, 'target_date')
            ->paginate($this->safePerPage(10))
            ->withQueryString();

        $goals = Goal::where('created_by', creatorId())
            ->where('status', 'active')
            ->select('id', 'goal_name')
            ->orderBy('goal_name')
            ->get();

        return Inertia::render('Goal/Milestones/Index', [
            'milestones' => $milestones,
            'goals'      => $goals,
        ]);
    }

    public function store(StoreMilestoneRequest $request)
    {
        if (!Auth::user()->can('create-goal-milestones')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        $milestone = DB::transaction(function () use ($validated) {
            $milestone = new GoalMilestone();
            $milestone->goal_id = $validated['goal_id'];
            $milestone->milestone_name = $validated['milestone_name'];
            $milestone->milestone_description = $validated['milestone_description'] ?? null;
            $milestone->target_amount = $validated['target_amount'];
            $milestone->target_date = $validated['target_date'];
            $milestone->achieved_amount = 0.0;
            $milestone->status = 'pending';
            $milestone->creator_id = Auth::id();
            $milestone->created_by = creatorId();
            $milestone->save();
            return $milestone;
        });

        CreateGoalMilestone::dispatch($request, $milestone);

        return redirect()->route('goal.milestones.index')->with('success', __('The milestone has been created successfully.'));
    }

    public function update(UpdateMilestoneRequest $request, GoalMilestone $milestone)
    {
        if (!Auth::user()->can('edit-goal-milestones') || !$this->assertBelongsToTenant($milestone)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($milestone, $validated) {
            if (isset($validated['goal_id'])) {
                $milestone->goal_id = $validated['goal_id'];
            }
            $milestone->milestone_name = $validated['milestone_name'];
            $milestone->milestone_description = $validated['milestone_description'] ?? null;
            $milestone->target_amount = $validated['target_amount'];
            $milestone->target_date = $validated['target_date'];
            if (isset($validated['achieved_amount'])) {
                $milestone->achieved_amount = $validated['achieved_amount'];
            }
            $milestone->status = $validated['status'];
            $milestone->save();
        });

        UpdateGoalMilestone::dispatch($request, $milestone);

        return back()->with('success', __('The milestone details are updated successfully.'));
    }

    public function show(GoalMilestone $milestone)
    {
        if (!Auth::user()->can('view-goal-milestones') || !$this->assertBelongsToTenant($milestone)) {
            return redirect()->route('goal.milestones.index')->with('error', __('Permission denied'));
        }

        $milestone->load('goal');

        return Inertia::render('Goal/Milestones/View', [
            'milestone' => $milestone,
        ]);
    }

    public function destroy(GoalMilestone $milestone)
    {
        if (!Auth::user()->can('delete-goal-milestones') || !$this->assertBelongsToTenant($milestone)) {
            return back()->with('error', __('Permission denied'));
        }

        DestroyGoalMilestone::dispatch($milestone);

        DB::transaction(function () use ($milestone) {
            $milestone->delete();
        });

        return redirect()->route('goal.milestones.index')->with('success', __('The milestone has been deleted.'));
    }
}
