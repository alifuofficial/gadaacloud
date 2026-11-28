<?php

namespace Workdo\Hrm\Http\Controllers;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use App\Models\User;
use Workdo\Hrm\Models\Resignation;
use Workdo\Hrm\Http\Requests\StoreResignationRequest;
use Workdo\Hrm\Http\Requests\UpdateResignationRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\Hrm\Events\UpdateResignaionStatus;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Services\EmployeeLifecycleService;
use Illuminate\Support\Facades\DB;

class ResignationController extends Controller
{
    use HrmControllerHelpers;
    public function index()
    {
        if (Auth::user()->can('manage-resignations')) {
            $resignations = Resignation::with([
                'employee:id,name',
                'approvedBy:id,name'
            ])->where(function ($q) {
                if (Auth::user()->can('manage-any-resignations')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-resignations')) {
                    $q->where('creator_id', Auth::id())->orWhere('employee_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
                ->when(request('name'), function ($q) {
                    $q->whereHas('employee', function ($query) {
                        $query->where('name', 'like', '%' . request('name') . '%');
                    });
                })
                ->when(request('employee_id'), fn($q) => $q->where('employee_id', request('employee_id')))

                ->when(true, fn($q) => $this->applySafeSort($q))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            return Inertia::render('Hrm/Resignations/Index', [
                'resignations' => $resignations,
                'employees' => $this->getFilteredEmployees(),
                'users' => User::where('created_by', creatorId())->select('id', 'name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreResignationRequest $request)
    {
        if (Auth::user()->can('create-resignations')) {
            $validated = $request->validated();
            $resignation = new Resignation();
            $resignation->employee_id = $validated['employee_id'];
            $resignation->last_working_date = $validated['last_working_date'];
            $resignation->reason = $validated['reason'];
            $resignation->description = isset($validated['description']) ? (\App\Support\HtmlSanitizer::sanitize($validated['description']) ?? '') : null;
            $resignation->document = $validated['document'];

            $resignation->creator_id = Auth::id();
            $resignation->created_by = creatorId();
            $resignation->save();

            return redirect()->route('hrm.resignations.index')->with('success', __('The resignation has been created successfully.'));
        } else {
            return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateResignationRequest $request, Resignation $resignation)
    {
        if (Auth::user()->can('edit-resignations')) {
            $validated = $request->validated();



            $resignation->employee_id = $validated['employee_id'];
            $resignation->last_working_date = $validated['last_working_date'];
            $resignation->reason = $validated['reason'];
            $resignation->description = isset($validated['description']) ? (\App\Support\HtmlSanitizer::sanitize($validated['description']) ?? '') : null;
            $resignation->document = $validated['document'];

            $resignation->save();

            return redirect()->back()->with('success', __('The resignation details are updated successfully.'));
        } else {
            return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Resignation $resignation)
    {
        if (Auth::user()->can('delete-resignations')) {
            if (!$this->assertBelongsToTenant($resignation)) {
                return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
            }

            $wasAccepted = $resignation->status === 'accepted';
            $empUserId = (int) $resignation->employee_id;
            $tenantId = (int) $resignation->created_by;

            $resignation->delete();

            if ($wasAccepted) {
                app(EmployeeLifecycleService::class)->handleReversal($empUserId, $tenantId);
            }

            return redirect()->back()->with('success', __('The resignation has been deleted.'));
        } else {
            return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(Request $request, Resignation $resignation, $status)
    {
        if (Auth::user()->can('manage-resignation-status')) {
            if (!$this->assertBelongsToTenant($resignation)) {
                return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
            }

            if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
                return redirect()->back()->with('error', __('Invalid resignation status.'));
            }

            $previousStatus = $resignation->status;
            $lifecycleResult = null;
            $reversalResult = null;
            $lifecycleService = app(EmployeeLifecycleService::class);

            DB::transaction(function () use ($request, $resignation, $status, $previousStatus, $lifecycleService, &$lifecycleResult, &$reversalResult) {
                $resignation->status = $status;
                $resignation->approved_by = $status === 'accepted' ? Auth::id() : null;
                $resignation->save();

                if ($status === 'accepted') {
                    $effectiveDate = $resignation->last_working_date ?? $resignation->resignation_date;
                    $lifecycleResult = $lifecycleService->handleSeparation(
                        (int) $resignation->employee_id,
                        (int) $resignation->created_by,
                        'resigned',
                        $effectiveDate
                    );
                } elseif ($previousStatus === 'accepted') {
                    $reversalResult = $lifecycleService->handleReversal(
                        (int) $resignation->employee_id,
                        (int) $resignation->created_by
                    );
                }

                UpdateResignaionStatus::dispatch($request, $resignation);
            });

            if ($lifecycleResult && ($lifecycleResult['mode'] ?? '') === 'deactivated') {
                $openCount = (int) ($lifecycleResult['impact']['total_open_items'] ?? 0);
                $msg = $openCount > 0
                    ? __('Resignation accepted and employee account deactivated. Warning: :count open item(s) across projects/CRM/tickets/approvals require handover.', ['count' => $openCount])
                    : __('Resignation accepted and employee user account has been deactivated.');

                return redirect()->back()->with('success', $msg);
            }

            if ($lifecycleResult && ($lifecycleResult['mode'] ?? '') === 'notice_period') {
                $openCount = (int) ($lifecycleResult['impact']['total_open_items'] ?? 0);
                $msg = $openCount > 0
                    ? __('Resignation accepted. Employee placed on notice period until :date (:count open work item(s) pending handover).', [
                        'date' => $lifecycleResult['separation_date'],
                        'count' => $openCount,
                    ])
                    : __('Resignation accepted. Employee placed on notice period until :date.', [
                        'date' => $lifecycleResult['separation_date'],
                    ]);

                return redirect()->back()->with('success', $msg);
            }

            if ($reversalResult && !($reversalResult['restored'] ?? true) && ($reversalResult['reason'] ?? '') === 'plan_limit') {
                return redirect()->back()->with('warning', __('Resignation status updated, but user login could not be auto-enabled because your plan seat limit is reached.'));
            }

            return redirect()->back()->with('success', __('The resignation status has been updated.'));
        } else {
            return redirect()->route('hrm.resignations.index')->with('error', __('Permission denied'));
        }
    }

    public function getOffboardingImpact($employeeId)
    {
        if (!Auth::user()->can('manage-resignation-status') && !Auth::user()->can('manage-termination-status')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $tenantId = (int) creatorId();
        $impact = app(EmployeeLifecycleService::class)->getOffboardingImpactSummary((int) $employeeId, $tenantId);

        return response()->json($impact);
    }

    private function getFilteredUsers()
    {
        return User::activeEmp()->where('created_by', creatorId())
            ->when(!Auth::user()->can('manage-any-resignations'), function ($q) {
                if (Auth::user()->can('manage-own-resignations')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')->get();
    }


    private function getFilteredEmployees()
    {
        $employeeQuery = Employee::where('created_by', creatorId())->active();

        if (Auth::user()->can('manage-own-resignations') && !Auth::user()->can('manage-any-resignations')) {
            $employeeQuery->where(function ($q) {
                $q->where('creator_id', Auth::id())->orWhere('user_id', Auth::id());
            });
        }

        return User::activeEmp()->where('created_by', creatorId())
            ->whereIn('id', $employeeQuery->pluck('user_id'))
            ->select('id', 'name')->get();
    }
}
