<?php

namespace Workdo\Goal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Goal\Events\CreateGoal;
use Workdo\Goal\Events\DestroyGoal;
use Workdo\Goal\Events\UpdateGoal;
use Workdo\Goal\Http\Requests\StoreGoalRequest;
use Workdo\Goal\Http\Requests\UpdateGoalRequest;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalCategory;
use Workdo\Goal\Services\GoalService;
use Workdo\Goal\Traits\GoalControllerHelpers;
use Workdo\Goal\Traits\GoalDateHelpers;

class GoalController extends Controller
{
    use GoalControllerHelpers, GoalDateHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-goals')) {
            return back()->with('error', __('Permission denied'));
        }

        $sortMap = [
            'goal_name'      => 'goal_name',
            'goal_type'      => 'goal_type',
            'target_amount'  => 'target_amount',
            'current_amount' => 'current_amount',
            'start_date'     => 'start_date',
            'target_date'    => 'target_date',
            'priority'       => 'priority',
            'status'         => 'status',
            'created_at'     => 'created_at',
        ];

        $goals = Goal::query()
            ->with(['category', 'account', 'bankAccount'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-goals')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-goals')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('goal_name'), fn($q) => $q->where('goal_name', 'like', '%' . request('goal_name') . '%'))
            ->when(request('goal_type'), fn($q) => $q->where('goal_type', request('goal_type')))
            ->when(request('status'), fn($q) => $q->where('status', request('status')))
            ->when(request('priority'), fn($q) => $q->where('priority', request('priority')))
            ->when(request('category_id'), fn($q) => $q->where('category_id', request('category_id')));

        $goals = $this->applySafeSort($goals, $sortMap, 'latest')
            ->paginate($this->safePerPage(10))
            ->withQueryString();

        $categories = GoalCategory::where('is_active', true)
            ->where('created_by', creatorId())
            ->orderBy('category_name')
            ->get();

        $chartOfAccounts = ChartOfAccount::where('created_by', creatorId())
            ->select('id', 'account_code', 'account_name', 'normal_balance')
            ->orderBy('account_code')
            ->get();

        $bankAccounts = BankAccount::where('created_by', creatorId())
            ->select('id', 'bank_name', 'holder_name', 'opening_balance')
            ->get();

        return Inertia::render('Goal/Goals/Index', [
            'goals'           => $goals,
            'categories'      => $categories,
            'chartOfAccounts' => $chartOfAccounts,
            'bankAccounts'    => $bankAccounts,
        ]);
    }

    public function store(StoreGoalRequest $request)
    {
        if (!Auth::user()->can('create-goals')) {
            return redirect()->route('goal.goals.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated) {
            $goal = new Goal();
            $goal->goal_name = $validated['goal_name'];
            $goal->goal_description = $validated['goal_description'] ?? null;
            $goal->category_id = $validated['category_id'];
            $goal->goal_type = $validated['goal_type'];
            $goal->target_amount = $validated['target_amount'];
            $goal->current_amount = 0.0;
            $goal->start_date = $validated['start_date'];
            $goal->target_date = $validated['target_date'];
            $goal->ethiopian_fiscal_year = $validated['ethiopian_fiscal_year'] ?? null;
            $goal->ethiopian_quarter = $validated['ethiopian_quarter'] ?? null;
            $goal->priority = $validated['priority'];
            $goal->status = $validated['status'] ?? 'draft';
            $goal->account_id = $validated['account_id'] ?? null;
            $goal->bank_account_id = $validated['bank_account_id'] ?? null;
            $goal->creator_id = Auth::id();
            $goal->created_by = creatorId();
            $goal->save();

            // Create initial tracking record
            $goalService = new GoalService();
            $goalService->updateGoalTracking($goal->id);

            CreateGoal::dispatch($request, $goal);
        });

        return redirect()->route('goal.goals.index')->with('success', __('The goal has been created successfully.'));
    }

    public function update(UpdateGoalRequest $request, Goal $goal)
    {
        if (!Auth::user()->can('edit-goals') || !$this->assertBelongsToTenant($goal)) {
            return redirect()->route('goal.goals.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $goal, $validated) {
            $goal->goal_name = $validated['goal_name'];
            $goal->goal_description = $validated['goal_description'] ?? null;
            $goal->category_id = $validated['category_id'];
            $goal->goal_type = $validated['goal_type'];
            $goal->target_amount = $validated['target_amount'];

            // Preserve current_amount unless explicit positive value supplied
            if (isset($validated['current_amount']) && $validated['current_amount'] !== null) {
                $goal->current_amount = $validated['current_amount'];
            }

            $goal->start_date = $validated['start_date'];
            $goal->target_date = $validated['target_date'];
            $goal->ethiopian_fiscal_year = $validated['ethiopian_fiscal_year'] ?? null;
            $goal->ethiopian_quarter = $validated['ethiopian_quarter'] ?? null;
            $goal->priority = $validated['priority'];
            $goal->status = $validated['status'];
            $goal->account_id = $validated['account_id'] ?? null;
            $goal->bank_account_id = $validated['bank_account_id'] ?? null;
            $goal->save();

            // Recalculate tracking in case target amount or target date changed
            $goalService = new GoalService();
            $goalService->updateGoalTracking($goal->id);

            UpdateGoal::dispatch($request, $goal);
        });

        return back()->with('success', __('The goal details are updated successfully.'));
    }

    public function show(Goal $goal)
    {
        if (!Auth::user()->can('view-goals') || !$this->assertBelongsToTenant($goal)) {
            return redirect()->route('goal.goals.index')->with('error', __('Permission denied'));
        }

        $goal->load(['category', 'account', 'bankAccount', 'milestones', 'contributions']);

        return Inertia::render('Goal/Goals/View', [
            'goal' => $goal,
        ]);
    }

    public function active(Goal $goal)
    {
        if (!Auth::user()->can('active-goals') || !$this->assertBelongsToTenant($goal)) {
            return back()->with('error', __('Permission denied'));
        }

        $newStatus = ($goal->status === 'active') ? 'paused' : 'active';
        $goal->update(['status' => $newStatus]);

        return back()->with('success', __('Goal status updated successfully.'));
    }

    public function destroy(Goal $goal)
    {
        if (!Auth::user()->can('delete-goals') || !$this->assertBelongsToTenant($goal)) {
            return redirect()->route('goal.goals.index')->with('error', __('Permission denied'));
        }

        DB::transaction(function () use ($goal) {
            DestroyGoal::dispatch($goal);
            $goal->milestones()->delete();
            $goal->contributions()->delete();
            $goal->tracking()->delete();
            $goal->delete();
        });

        return redirect()->back()->with('success', __('The goal has been deleted.'));
    }
}
