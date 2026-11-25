<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Events\CreateEmployeeGoal;
use Workdo\Performance\Events\DestroyEmployeeGoal;
use Workdo\Performance\Events\UpdateEmployeeGoal;
use Workdo\Performance\Http\Requests\StoreEmployeeGoalRequest;
use Workdo\Performance\Http\Requests\UpdateEmployeeGoalRequest;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceGoalType;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class EmployeeGoalController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(private PerformanceErpIntegrationService $erpService) {}

    public function index()
    {
        if (!Auth::user()->can('manage-employee-goals')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = PerformanceEmployeeGoal::with(['employee', 'goalType', 'reviewCycle'])
            ->where('performance_employee_goals.created_by', creatorId())
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-employee-goals')) {
                    // Already scoped to creatorId()
                } elseif (Auth::user()->can('manage-own-employee-goals')) {
                    $q->where(function ($sub) {
                        $sub->where('performance_employee_goals.creator_id', Auth::id())
                            ->orWhere('performance_employee_goals.employee_id', Auth::id());
                    });
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('title'), fn($q) => $q->where('performance_employee_goals.title', 'like', '%' . request('title') . '%'))
            ->when(request('employee_id'), fn($q) => $q->where('performance_employee_goals.employee_id', request('employee_id')))
            ->when(request('goal_type_id'), fn($q) => $q->where('performance_employee_goals.goal_type_id', request('goal_type_id')))
            ->when(request('review_cycle_id'), fn($q) => $q->where('performance_employee_goals.review_cycle_id', request('review_cycle_id')))
            ->when(request('status'), fn($q) => $q->where('performance_employee_goals.status', request('status')));

        $goals = $this->applySafeSort($query, [
            'title' => 'performance_employee_goals.title',
            'start_date' => 'performance_employee_goals.start_date',
            'end_date' => 'performance_employee_goals.end_date',
            'progress' => 'performance_employee_goals.progress',
            'status' => 'performance_employee_goals.status',
            'created_at' => 'performance_employee_goals.created_at',
            'goal_type' => function ($q, $direction) {
                return $q->join('performance_goal_types', 'performance_employee_goals.goal_type_id', '=', 'performance_goal_types.id')
                    ->orderBy('performance_goal_types.name', $direction)
                    ->select('performance_employee_goals.*');
            },
        ])
            ->paginate($this->safePerPage())
            ->withQueryString();

        return Inertia::render('Performance/EmployeeGoals/Index', [
            'goals' => $goals,
            'employees' => $this->getFilteredEmployees(),
            'goalTypes' => $this->getFilteredGoalTypes(),
            'reviewCycles' => PerformanceReviewCycle::where('created_by', creatorId())->where('status', 'active')->select('id', 'name')->get(),
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
            'kpiSources' => $this->getAvailableKpiSources(),
        ]);
    }

    public function store(StoreEmployeeGoalRequest $request)
    {
        if (!Auth::user()->can('create-employee-goals')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $targetNumeric = $validated['target_numeric'] ?? PerformanceEmployeeGoal::parseNumericTarget($validated['target']);
        $currentValue = isset($validated['current_value']) ? (float) $validated['current_value'] : null;

        $progress = (float) ($validated['progress'] ?? 0);
        if ($currentValue !== null && $targetNumeric !== null && $targetNumeric > 0) {
            $progress = round(min(100, max(0, ($currentValue / $targetNumeric) * 100)), 2);
        }

        $employeeGoal = new PerformanceEmployeeGoal();
        $employeeGoal->employee_id = $validated['employee_id'];
        $employeeGoal->department_id = $validated['department_id'] ?? null;
        $employeeGoal->goal_type_id = $validated['goal_type_id'];
        $employeeGoal->review_cycle_id = $validated['review_cycle_id'] ?? null;
        $employeeGoal->title = $validated['title'];
        $employeeGoal->description = $validated['description'];
        $employeeGoal->start_date = $validated['start_date'];
        $employeeGoal->end_date = $validated['end_date'];
        $employeeGoal->target = $validated['target'];
        $employeeGoal->target_numeric = $targetNumeric;
        $employeeGoal->current_value = $currentValue;
        $employeeGoal->unit = $validated['unit'] ?? null;
        $employeeGoal->progress = $progress;
        $employeeGoal->weight = $validated['weight'] ?? 0;
        $employeeGoal->status = $validated['status'] ?? 'not_started';
        $employeeGoal->kpi_source = $validated['kpi_source'] ?? 'manual';
        $employeeGoal->auto_sync_enabled = (bool) ($validated['auto_sync_enabled'] ?? false);
        $employeeGoal->creator_id = Auth::id();
        $employeeGoal->created_by = creatorId();
        $employeeGoal->save();

        if ($employeeGoal->auto_sync_enabled && $employeeGoal->kpi_source !== 'manual') {
            $this->erpService->syncGoalFromErpSource($employeeGoal);
        }

        CreateEmployeeGoal::dispatch($request, $employeeGoal);

        return back()->with('success', __('The employee goal has been created successfully.'));
    }

    public function update(UpdateEmployeeGoalRequest $request, PerformanceEmployeeGoal $employeeGoal)
    {
        if (!Auth::user()->can('edit-employee-goals')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessGoal($employeeGoal)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $targetNumeric = $validated['target_numeric'] ?? PerformanceEmployeeGoal::parseNumericTarget($validated['target']);
        $currentValue = isset($validated['current_value']) ? (float) $validated['current_value'] : null;

        $progress = (float) $validated['progress'];
        if ($currentValue !== null && $targetNumeric !== null && $targetNumeric > 0) {
            $progress = round(min(100, max(0, ($currentValue / $targetNumeric) * 100)), 2);
        }

        $employeeGoal->employee_id = $validated['employee_id'];
        $employeeGoal->department_id = $validated['department_id'] ?? null;
        $employeeGoal->goal_type_id = $validated['goal_type_id'];
        $employeeGoal->review_cycle_id = $validated['review_cycle_id'] ?? null;
        $employeeGoal->title = $validated['title'];
        $employeeGoal->description = $validated['description'];
        $employeeGoal->start_date = $validated['start_date'];
        $employeeGoal->end_date = $validated['end_date'];
        $employeeGoal->target = $validated['target'];
        $employeeGoal->target_numeric = $targetNumeric;
        $employeeGoal->current_value = $currentValue;
        $employeeGoal->unit = $validated['unit'] ?? null;
        $employeeGoal->progress = $progress;
        $employeeGoal->weight = $validated['weight'] ?? 0;
        $employeeGoal->status = $validated['status'];
        $employeeGoal->kpi_source = $validated['kpi_source'] ?? 'manual';
        $employeeGoal->auto_sync_enabled = (bool) ($validated['auto_sync_enabled'] ?? false);
        $employeeGoal->save();

        UpdateEmployeeGoal::dispatch($request, $employeeGoal);

        return back()->with('success', __('The employee goal details are updated successfully.'));
    }

    public function syncKpi(PerformanceEmployeeGoal $employeeGoal)
    {
        if (!Auth::user()->can('edit-employee-goals') || !$this->canAccessGoal($employeeGoal)) {
            return back()->with('error', __('Permission denied'));
        }

        $result = $this->erpService->syncGoalFromErpSource($employeeGoal);

        return back()->with($result['synced'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(PerformanceEmployeeGoal $employeeGoal)
    {
        if (!Auth::user()->can('delete-employee-goals')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessGoal($employeeGoal)) {
            return back()->with('error', __('Permission denied'));
        }

        DestroyEmployeeGoal::dispatch($employeeGoal);
        $employeeGoal->delete();

        return back()->with('success', __('The employee goal has been deleted.'));
    }

    private function canAccessGoal(PerformanceEmployeeGoal $goal): bool
    {
        if ((int) $goal->created_by !== (int) creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-employee-goals')) {
            return true;
        }
        if (Auth::user()->can('manage-own-employee-goals')) {
            return (int) $goal->creator_id === (int) Auth::id() || (int) $goal->employee_id === (int) Auth::id();
        }
        return false;
    }

    private function getFilteredEmployees()
    {
        return User::emp()->where('created_by', creatorId())
            ->when(!Auth::user()->can('manage-any-users'), function ($q) {
                if (Auth::user()->can('manage-own-users')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')
            ->get();
    }

    private function getFilteredGoalTypes()
    {
        return PerformanceGoalType::where('created_by', creatorId())
            ->where('status', 'active')
            ->when(!Auth::user()->can('manage-any-goal-types'), function ($q) {
                if (Auth::user()->can('manage-own-goal-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')
            ->get();
    }

    private function getAvailableKpiSources(): array
    {
        $tenantId = (int) creatorId();
        $sources = [['value' => 'manual', 'label' => __('Manual Tracking')]];
        if ($this->erpService->isModuleAvailable('Hrm', $tenantId)) {
            $sources[] = ['value' => 'hrm_attendance', 'label' => __('HRM Attendance Rate (%)')];
        }
        if ($this->erpService->isModuleAvailable('Taskly', $tenantId)) {
            $sources[] = ['value' => 'taskly_on_time', 'label' => __('Project Tasks Completed (%)')];
        }
        if ($this->erpService->isModuleAvailable('Timesheet', $tenantId)) {
            $sources[] = ['value' => 'timesheet_hours', 'label' => __('Timesheet Logged Hours')];
        }
        if ($this->erpService->isModuleAvailable('Lead', $tenantId)) {
            $sources[] = ['value' => 'crm_deals', 'label' => __('CRM Won Deals Value')];
        }
        return $sources;
    }
}
