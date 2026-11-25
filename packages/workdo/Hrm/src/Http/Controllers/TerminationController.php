<?php

namespace Workdo\Hrm\Http\Controllers;

use Workdo\Hrm\Models\Termination;
use Workdo\Hrm\Http\Requests\StoreTerminationRequest;
use Workdo\Hrm\Http\Requests\UpdateTerminationRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\TerminationType;
use Workdo\Hrm\Models\Loan;
use Carbon\Carbon;
use Workdo\Hrm\Events\CreateTermination;
use Workdo\Hrm\Events\DestroyTermination;
use Workdo\Hrm\Events\UpdateTermination;
use Workdo\Hrm\Events\UpdateTerminationStatus;

class TerminationController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-terminations')) {
            $terminations = Termination::query()
                ->with(['employee', 'terminationType', 'approvedBy:id,name'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-terminations')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-terminations')) {
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
                ->when(request('employee_id') && request('employee_id') !== 'all', fn($q) => $q->where('employee_id', request('employee_id')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Hrm/Terminations/Index', [
                'terminations' => $terminations,
                'users' => $this->getFilteredEmployees(),
                'terminationtypes' => TerminationType::where('created_by', creatorId())->select('id', 'termination_type')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreTerminationRequest $request)
    {
        if (Auth::user()->can('create-terminations')) {
            $validated = $request->validated();



            $termination = new Termination();
            $termination->notice_date = $validated['notice_date'];
            $termination->termination_date = $validated['termination_date'];
            $termination->reason = $validated['reason'];
            $termination->description = $validated['description'];
            $termination->document = $validated['document'];
            $termination->employee_id = $validated['employee_id'];
            $termination->termination_type_id = $validated['termination_type_id'];
            $termination->status = 'pending';

            $termination->creator_id = Auth::id();
            $termination->created_by = creatorId();
            $termination->save();

            CreateTermination::dispatch($request, $termination);

            return redirect()->route('hrm.terminations.index')->with('success', __('The termination has been created successfully.'));
        } else {
            return redirect()->route('hrm.terminations.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateTerminationRequest $request, Termination $termination)
    {
        if (Auth::user()->can('edit-terminations')) {
            $validated = $request->validated();



            $termination->notice_date = $validated['notice_date'];
            $termination->termination_date = $validated['termination_date'];
            $termination->reason = $validated['reason'];
            $termination->description = $validated['description'];
            $termination->document = $validated['document'];
            $termination->employee_id = $validated['employee_id'];
            $termination->termination_type_id = $validated['termination_type_id'];

            $termination->save();

            UpdateTermination::dispatch($request, $termination);

            return redirect()->back()->with('success', __('The termination details are updated successfully.'));
        } else {
            return redirect()->route('hrm.terminations.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Termination $termination)
    {
        if (Auth::user()->can('delete-terminations')) {
            DestroyTermination::dispatch($termination);
            $termination->delete();

            return redirect()->back()->with('success', __('The termination has been deleted.'));
        } else {
            return redirect()->route('hrm.terminations.index')->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(\Illuminate\Http\Request $request, Termination $termination)
    {
        if (Auth::user()->can('manage-termination-status')) {
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected'
            ]);

            $termination->status = $validated['status'];

            if ($validated['status'] === 'approved') {
                $termination->approved_by = Auth::id();
            }

            $termination->save();
            UpdateTerminationStatus::dispatch($request, $termination);

            return redirect()->back()->with('success', __('Termination status updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }    
    private function getFilteredEmployees()
    {
        $employeeQuery = Employee::where('created_by', creatorId());

        if (Auth::user()->can('manage-own-terminations') && !Auth::user()->can('manage-any-terminations')) {
            $employeeQuery->where(function ($q) {
                $q->where('creator_id', Auth::id())->orWhere('user_id', Auth::id());
            });
        }

        return User::emp()->where('created_by', creatorId())
            ->whereIn('id', $employeeQuery->pluck('user_id'))
            ->select('id', 'name')->get();
    }

    /**
     * Unified Ethiopian Statutory Severance, Notice & Accrued Annual Leave Settlement Calculator (Proc. No. 1156/2019).
     */
    private function computeStatutorySettlementData(Employee $employee, Carbon $terminationDate, ?Carbon $noticeDate = null): array
    {
        $joinDate = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : Carbon::today();
        $diff = $joinDate->diff($terminationDate);
        $totalYears = $diff->y;
        $totalMonths = $diff->m;
        $totalDays = $diff->d;

        $basicSalary = (float) ($employee->basic_salary ?? 0);
        $dailyWage = round($basicSalary / 30, 2);

        // Article 35: Statutory Notice Period
        if ($totalYears < 1) {
            $requiredNoticeDays = 30;
        } elseif ($totalYears <= 9) {
            $requiredNoticeDays = 60;
        } else {
            $requiredNoticeDays = 90;
        }

        $actualNoticeDays = $noticeDate ? $noticeDate->diffInDays($terminationDate) : 0;
        $noticeShortfallDays = max(0, $requiredNoticeDays - $actualNoticeDays);
        $noticeCompensation = round($dailyWage * $noticeShortfallDays, 2);

        // Article 39: Statutory Severance Pay (1st year = 1 month; subsequent = 1/3 month per year; capped at 12 months)
        if ($totalYears < 1) {
            $severancePay = round(($totalMonths / 12) * $basicSalary, 2);
        } else {
            $firstYearPay = $basicSalary;
            $subsequentYearsPay = ($totalYears - 1) * ($basicSalary / 3);
            $proratedMonthsPay = ($totalMonths / 12) * ($basicSalary / 3);
            $severancePay = round($firstYearPay + $subsequentYearsPay + $proratedMonthsPay, 2);
        }
        $severancePay = min($severancePay, $basicSalary * 12);

        // Article 77: Statutory Annual Leave (16 working days Year 1 + 1 day per 2 years service), prorated unused leave cashout
        $annualEntitlementDays = 16 + intdiv($totalYears, 2);
        $usedAnnualDays = 0;
        if (class_exists(\Workdo\Hrm\Models\LeaveApplication::class)) {
            $usedAnnualDays = (float) \Workdo\Hrm\Models\LeaveApplication::where('created_by', $employee->created_by)
                ->where('employee_id', $employee->user_id)
                ->where('status', 'approved')
                ->whereYear('start_date', $terminationDate->year)
                ->whereHas('leave_type', fn ($q) => $q->where('is_paid', true))
                ->sum('total_days');
        }
        $unusedLeaveDays = max(0, $annualEntitlementDays - $usedAnnualDays);
        $leaveEncashment = round($unusedLeaveDays * $dailyWage, 2);

        // Unpaid / Active Loans deduction (remaining principal = amount - paid_amount)
        $outstandingLoans = (float) Loan::where('employee_id', $employee->user_id)
            ->where('created_by', $employee->created_by)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->get()
            ->sum(fn ($l) => max(0, (float) $l->amount - (float) ($l->paid_amount ?? 0)));

        $netFinalSettlement = max(0, ($severancePay + $noticeCompensation + $leaveEncashment) - $outstandingLoans);

        return [
            'joinDate' => $joinDate,
            'serviceDuration' => "{$totalYears} Years, {$totalMonths} Months, {$totalDays} Days",
            'basicSalary' => $basicSalary,
            'dailyWage' => $dailyWage,
            'requiredNoticeDays' => $requiredNoticeDays,
            'actualNoticeDays' => $actualNoticeDays,
            'noticeShortfallDays' => $noticeShortfallDays,
            'noticeCompensation' => $noticeCompensation,
            'severancePay' => $severancePay,
            'unusedLeaveDays' => $unusedLeaveDays,
            'leaveEncashment' => $leaveEncashment,
            'outstandingLoans' => round($outstandingLoans, 2),
            'netFinalSettlement' => round($netFinalSettlement, 2),
        ];
    }

    /**
     * Calculate Ethiopian Statutory Severance Pay & Notice Settlement (Labour Proclamation No. 1156/2019).
     */
    public function calculateStatutorySettlement(\Illuminate\Http\Request $request, $employeeId)
    {
        if (!Auth::user()->can('manage-terminations')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $employee = Employee::where('user_id', $employeeId)
            ->where('created_by', creatorId())
            ->firstOrFail();

        $terminationDate = $request->termination_date ? Carbon::parse($request->termination_date) : Carbon::today();
        $noticeDate = $request->notice_date ? Carbon::parse($request->notice_date) : null;
        $calc = $this->computeStatutorySettlementData($employee, $terminationDate, $noticeDate);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name ?? 'Employee',
                'joining_date' => $calc['joinDate']->format('Y-m-d'),
                'termination_date' => $terminationDate->format('Y-m-d'),
                'service_duration' => $calc['serviceDuration'],
                'basic_salary' => $calc['basicSalary'],
                'daily_wage' => $calc['dailyWage'],
            ],
            'settlement' => [
                'required_notice_days' => $calc['requiredNoticeDays'],
                'actual_notice_days' => $calc['actualNoticeDays'],
                'notice_shortfall_days' => $calc['noticeShortfallDays'],
                'notice_shortfall_compensation' => $calc['noticeCompensation'],
                'severance_pay' => $calc['severancePay'],
                'unused_leave_days' => $calc['unusedLeaveDays'],
                'unused_leave_encashment' => $calc['leaveEncashment'],
                'outstanding_loan_deductions' => $calc['outstandingLoans'],
                'net_final_settlement' => $calc['netFinalSettlement'],
                'legal_reference' => 'FDRE Labour Proclamation No. 1156/2019 (Articles 35, 39, 40 & 77)'
            ]
        ]);
    }

    /**
     * Render / Print Official Ethiopian Final Settlement & Clearance Voucher (የስራ መልቀቂያና የክሊራንስ ማስረጃ).
     */
    public function settlementVoucher(Termination $termination)
    {
        if (!Auth::user()->can('manage-terminations')) {
            return back()->with('error', __('Permission denied'));
        }

        if (!Auth::user()->hasRole('superadmin') && (int) $termination->created_by !== (int) creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $termination->load(['employee', 'terminationType']);
        $employeeModel = Employee::where('user_id', $termination->employee_id)
            ->where('created_by', $termination->created_by)
            ->first();

        $termDate = $termination->termination_date ? Carbon::parse($termination->termination_date) : Carbon::today();
        $noticeDate = $termination->notice_date ? Carbon::parse($termination->notice_date) : null;

        $calc = $employeeModel
            ? $this->computeStatutorySettlementData($employeeModel, $termDate, $noticeDate)
            : [
                'serviceDuration' => '0 Years, 0 Months, 0 Days',
                'basicSalary' => 0.0,
                'severancePay' => 0.0,
                'noticeShortfallDays' => 0,
                'noticeCompensation' => 0.0,
                'unusedLeaveDays' => 0,
                'leaveEncashment' => 0.0,
                'outstandingLoans' => 0.0,
                'netFinalSettlement' => 0.0,
            ];

        $settings = getCompanyAllSetting($termination->created_by);
        $companyName = $settings['company_name'] ?? config('app.name', 'GadaaCloud');
        $companyVat = $settings['vat_number'] ?? $settings['tax_number'] ?? 'N/A';

        return response()->view('terminations.settlement_voucher', [
            'termination' => $termination,
            'employee' => $employeeModel,
            'serviceDuration' => $calc['serviceDuration'],
            'basicSalary' => $calc['basicSalary'],
            'severancePay' => $calc['severancePay'],
            'noticeShortfallDays' => $calc['noticeShortfallDays'],
            'noticeCompensation' => $calc['noticeCompensation'],
            'unusedLeaveDays' => $calc['unusedLeaveDays'],
            'leaveEncashment' => $calc['leaveEncashment'],
            'outstandingLoans' => $calc['outstandingLoans'],
            'netSettlement' => $calc['netFinalSettlement'],
            'companyName' => $companyName,
            'companyVat' => $companyVat,
        ]);
    }
}
