<?php

namespace Workdo\Hrm\Http\Controllers;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Workdo\Hrm\Models\LeaveApplication;
use Workdo\Hrm\Http\Requests\StoreLeaveApplicationRequest;
use Workdo\Hrm\Http\Requests\UpdateLeaveApplicationRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use Workdo\Hrm\Models\LeaveType;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Events\CreateLeaveApplication;
use Workdo\Hrm\Events\UpdateLeaveApplication;
use Workdo\Hrm\Events\DestroyLeaveApplication;
use Workdo\Hrm\Events\UpdateLeaveStatus;
use Workdo\Hrm\Models\Holiday;

class LeaveApplicationController extends Controller
{
    use HrmControllerHelpers;
    public function index()
    {
        if (Auth::user()->can('manage-leave-applications')) {
            $leaveapplications = LeaveApplication::query()
                ->with(['employee', 'leave_type', 'approved_by'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-leave-applications')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-leave-applications')) {
                        $q->where('creator_id', Auth::id())->orWhere('employee_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('reason'), function ($q) {
                    $q->where(function ($query) {
                        $query
                            ->where('reason', 'like', '%' . request('reason') . '%')
                            ->orWhereHas('employee', function ($subQuery) {
                                $subQuery->where('name', 'like', '%' . request('reason') . '%');
                            })
                            ->orWhereHas('leave_type', function ($subQuery) {
                                $subQuery->where('name', 'like', '%' . request('reason') . '%');
                            });
                    });
                })
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('employee_id'), fn($q) => $q->where('employee_id', request('employee_id')))
                ->when(request('leave_type_id'), fn($q) => $q->where('leave_type_id', request('leave_type_id')))
                ->when(request('start_date'), fn($q) => $q->whereDate('start_date', '>=', request('start_date')))
                ->when(request('end_date'), fn($q) => $q->whereDate('end_date', '<=', request('end_date')))
                ->when(true, fn($q) => $this->applySafeSort($q))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            return Inertia::render('Hrm/LeaveApplications/Index', [
                'leaveapplications' => $leaveapplications,
                'employees' => $this->getFilteredEmployees(),
                'leavetypes' => LeaveType::where('created_by', creatorId())->select('id', 'name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    /**
     * Validate and count chargeable working leave days across the requested date span,
     * excluding non-working days and company holidays (Ethiopian Labor Proc. No. 1156/2019).
     */
    private function countChargeableLeaveDays(string $startDateStr, string $endDateStr, int $tenantId): int
    {
        $start = \Carbon\Carbon::parse($startDateStr)->startOfDay();
        $end = \Carbon\Carbon::parse($endDateStr)->startOfDay();
        if ($end->lt($start)) {
            return 0;
        }

        $workingDaysJson = getCompanyAllSetting($tenantId)['working_days'] ?? '';
        $workingDaysArray = json_decode($workingDaysJson, true);
        $hasWorkingDaysConfig = is_array($workingDaysArray) && count($workingDaysArray) > 0;

        $holidays = Holiday::where('created_by', $tenantId)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        $chargeable = 0;
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($hasWorkingDaysConfig && !in_array($cursor->dayOfWeek, $workingDaysArray, false)) {
                continue;
            }

            $isHoliday = $holidays->contains(function ($h) use ($cursor) {
                return \Carbon\Carbon::parse($h->start_date)->lte($cursor)
                    && \Carbon\Carbon::parse($h->end_date)->gte($cursor);
            });
            if ($isHoliday) {
                continue;
            }

            $chargeable++;
        }

        return $chargeable;
    }

    public function store(StoreLeaveApplicationRequest $request)
    {
        if (Auth::user()->can('create-leave-applications')) {
            $validated = $request->validated();
            $tenantId = (int) creatorId();

            // Get leave type details scoped to tenant
            $leaveType = LeaveType::where('created_by', $tenantId)->find($validated['leave_type_id']);
            if (!$leaveType) {
                return redirect()
                    ->back()
                    ->withErrors(['leave_type_id' => __('Invalid leave type selected.')]);
            }

            // Count working days actually covered by the requested leave range
            $totalDays = $this->countChargeableLeaveDays($validated['start_date'], $validated['end_date'], $tenantId);
            if ($totalDays <= 0) {
                return redirect()->back()->with('error', __('Leave cannot be created exclusively on non-working days or holidays.'));
            }

            // Calculate used leaves for this employee, leave type and current fiscal/calendar year
            $usedLeavesQuery = LeaveApplication::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->whereIn('status', ['approved', 'pending']);

            if (function_exists('ethiopian_calendar_active') && ethiopian_calendar_active() && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
                $ethSvc = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
                $ethFy = $ethSvc->getCurrentFiscalYear($validated['start_date']);
                $fyRange = $ethSvc->getFiscalYearRange($ethFy);
                $usedLeaves = $usedLeavesQuery->whereBetween('start_date', [$fyRange['start_date'], $fyRange['end_date']])->sum('total_days');
            } else {
                $currentYear = (int) \Carbon\Carbon::parse($validated['start_date'])->year;
                $usedLeaves = $usedLeavesQuery->whereYear('start_date', $currentYear)->sum('total_days');
            }

            // Check for overlapping leave applications
            $overlappingLeave = LeaveApplication::where('employee_id', $validated['employee_id'])
                ->where(function ($query) use ($validated) {
                    $query
                        ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])->where('end_date', '>=', $validated['end_date']);
                        });
                })
                ->whereIn('status', ['approved', 'pending'])
                ->first();

            if ($overlappingLeave) {
                $startDate = \Carbon\Carbon::parse($overlappingLeave->start_date)->format('Y-m-d');
                $endDate = \Carbon\Carbon::parse($overlappingLeave->end_date)->format('Y-m-d');

                return redirect()
                    ->back()
                    ->withErrors([
                        'start_date' => "Leave already applied for overlapping dates from {$startDate} to {$endDate}",
                    ]);
            }

            // Check if requested days exceed available balance
            $availableLeaves = $leaveType->max_days_per_year - $usedLeaves;
            if ($totalDays > $availableLeaves) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'start_date' => __('Insufficient leave balance. Available: :available days, Requested: :requested days', [
                            'available' => $availableLeaves,
                            'requested' => $totalDays,
                        ]),
                    ]);
            }

            $leaveapplication = new LeaveApplication();
            $leaveapplication->start_date = $validated['start_date'];
            $leaveapplication->end_date = $validated['end_date'];
            $leaveapplication->total_days = $totalDays;
            $leaveapplication->reason = $validated['reason'];
            $leaveapplication->attachment = $validated['attachment'] ?? null;
            $leaveapplication->status = 'pending';
            $leaveapplication->employee_id = $validated['employee_id'];
            $leaveapplication->leave_type_id = $validated['leave_type_id'];

            $leaveapplication->creator_id = Auth::id();
            $leaveapplication->created_by = creatorId();
            $leaveapplication->save();

            CreateLeaveApplication::dispatch($request, $leaveapplication);

            return redirect()->route('hrm.leave-applications.index')->with('success', __('The leaveapplication has been created successfully.'));
        } else {
            return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateLeaveApplicationRequest $request, LeaveApplication $leaveapplication)
    {
        if (Auth::user()->can('edit-leave-applications')) {
            $tenantId = (int) creatorId();
            if (!Auth::user()->hasRole('superadmin') && (int) $leaveapplication->created_by !== $tenantId) {
                return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
            }

            $validated = $request->validated();

            $leaveType = LeaveType::where('created_by', $tenantId)->find($validated['leave_type_id']);
            if (!$leaveType) {
                return redirect()
                    ->back()
                    ->withErrors(['leave_type_id' => __('Invalid leave type selected.')]);
            }

            $totalDays = $this->countChargeableLeaveDays($validated['start_date'], $validated['end_date'], $tenantId);
            if ($totalDays <= 0) {
                return redirect()->back()->with('error', __('Leave cannot be scheduled exclusively on non-working days or holidays.'));
            }

            $currentYear = (int) \Carbon\Carbon::parse($validated['start_date'])->year;

            // Calculate used leaves for this employee, leave type and current year (excluding current application)
            $usedLeaves = LeaveApplication::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->whereIn('status', ['approved', 'pending'])
                ->whereYear('start_date', $currentYear)
                ->where('id', '!=', $leaveapplication->id)
                ->sum('total_days');

            // Check for overlapping leave applications (excluding current application)
            $overlappingLeave = LeaveApplication::where('employee_id', $validated['employee_id'])
                ->where('id', '!=', $leaveapplication->id)
                ->where(function ($query) use ($validated) {
                    $query
                        ->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])->where('end_date', '>=', $validated['end_date']);
                        });
                })
                ->whereIn('status', ['approved', 'pending'])
                ->first();

            if ($overlappingLeave) {
                $startDate = \Carbon\Carbon::parse($overlappingLeave->start_date)->format('Y-m-d');
                $endDate = \Carbon\Carbon::parse($overlappingLeave->end_date)->format('Y-m-d');

                return redirect()
                    ->back()
                    ->withErrors([
                        'start_date' => "Leave already applied for overlapping dates from {$startDate} to {$endDate}",
                    ]);
            }

            // Check if requested days exceed available balance
            $availableLeaves = $leaveType->max_days_per_year - $usedLeaves;
            if ($totalDays > $availableLeaves) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'start_date' => __('Insufficient leave balance. Available: :available days, Requested: :requested days', [
                            'available' => $availableLeaves,
                            'requested' => $totalDays,
                        ]),
                    ]);
            }

            $leaveapplication->employee_id = $validated['employee_id'];
            $leaveapplication->leave_type_id = $validated['leave_type_id'];
            $leaveapplication->start_date = $validated['start_date'];
            $leaveapplication->end_date = $validated['end_date'];
            $leaveapplication->total_days = $totalDays;
            $leaveapplication->reason = $validated['reason'];
            $leaveapplication->attachment = $validated['attachment'] ?? null;

            $leaveapplication->save();

            UpdateLeaveApplication::dispatch($request, $leaveapplication);

            return redirect()->back()->with('success', __('The leaveapplication details are updated successfully.'));
        } else {
            return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(LeaveApplication $leaveapplication)
    {
        if (Auth::user()->can('delete-leave-applications')) {
            if (!Auth::user()->hasRole('superadmin') && (int) $leaveapplication->created_by !== (int) creatorId()) {
                return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
            }
            DestroyLeaveApplication::dispatch($leaveapplication);
            $leaveapplication->delete();

            return redirect()->back()->with('success', __('The leaveapplication has been deleted.'));
        } else {
            return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(Request $request, LeaveApplication $leaveapplication)
    {
        if (Auth::user()->can('manage-leave-status')) {
            if (!Auth::user()->hasRole('superadmin') && (int) $leaveapplication->created_by !== (int) creatorId()) {
                return redirect()->route('hrm.leave-applications.index')->with('error', __('Permission denied'));
            }
            $request->validate([
                'status' => 'required|in:pending,approved,rejected',
                'approver_comment' => 'nullable|string',
            ]);

            $leaveapplication->status = $request->status;
            $leaveapplication->approver_comment = $request->approver_comment;

            if ($request->status === 'approved') {
                $leaveapplication->approved_by = Auth::id();
                $leaveapplication->approved_at = now();
            }

            $leaveapplication->save();
            UpdateLeaveStatus::dispatch($request, $leaveapplication);

            return redirect()->back()->with('success', __('Leave status updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function getLeaveBalance($employeeId, $leaveTypeId)
    {
        if (Auth::user()->can('view-leave-applications')) {
            $leaveType = LeaveType::find($leaveTypeId);
            if (!$leaveType) {
                return response()->json(['error' => 'Invalid leave type'], 404);
            }

            $currentYear = date('Y');
            $baseQuery = LeaveApplication::where('employee_id', $employeeId)->where('leave_type_id', $leaveTypeId)->whereYear('start_date', $currentYear);

            // Exclude current leave application if editing
            if (request('exclude_id')) {
                $baseQuery->where('id', '!=', request('exclude_id'));
            }

            $approvedLeaves = (clone $baseQuery)->where('status', 'approved')->sum('total_days');
            $pendingLeaves = (clone $baseQuery)->where('status', 'pending')->sum('total_days');
            $usedLeaves = $approvedLeaves + $pendingLeaves;
            $availableLeaves = $leaveType->max_days_per_year - $usedLeaves;

            return response()->json([
                'total_leaves' => $leaveType->max_days_per_year,
                'approved_leaves' => $approvedLeaves,
                'pending_leaves' => $pendingLeaves,
                'used_leaves' => $usedLeaves,
                'available_leaves' => $availableLeaves,
            ]);
        } else {
            return response()->json([], 403);
        }
    }

    public function getLeaveTypesByEmployee($employeeId)
    {
        if (Auth::user()->can('view-leave_types')) {
            $leave_types = LeaveType::where('employee_id', $employeeId)->where('created_by', creatorId())->select('id', 'name')->get();

            return response()->json($leave_types);
        } else {
            return response()->json([], 403);
        }
    }

    private function getFilteredEmployees()
    {
        $employeeQuery = Employee::where('created_by', creatorId());

        if (Auth::user()->can('manage-own-leave-applications') && !Auth::user()->can('manage-any-leave-applications')) {
            $employeeQuery->where(function ($q) {
                $q->where('creator_id', Auth::id())->orWhere('user_id', Auth::id());
            });
        }

        return User::emp()->where('created_by', creatorId())
            ->whereIn('id', $employeeQuery->pluck('user_id'))
            ->select('id', 'name')->get();
    }
}
