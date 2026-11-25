<?php

namespace Workdo\Hrm\Http\Controllers;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Hrm\Events\CreatePayroll;
use Workdo\Hrm\Events\DestroyPayroll;
use Workdo\Hrm\Events\DestroySalarySlip;
use Workdo\Hrm\Events\PaySalary;
use Workdo\Hrm\Events\UpdatePayroll;
use Workdo\Hrm\Http\Requests\StorePayrollRequest;
use Workdo\Hrm\Http\Requests\UpdatePayrollRequest;
use Workdo\Hrm\Models\Allowance;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\Deduction;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\LeaveApplication;
use Workdo\Hrm\Models\Loan;
use Workdo\Hrm\Models\Overtime;
use Workdo\Hrm\Models\Payroll;
use Workdo\Hrm\Models\PayrollEntry;
use Workdo\Hrm\Models\Resignation;
use Workdo\Hrm\Models\Termination;
use Workdo\Hrm\Services\EthiopianPayrollService;

class PayrollController extends Controller
{
    use HrmControllerHelpers;
    public function __construct(private EthiopianPayrollService $ethiopianPayrollService)
    {
    }

    private function checkPayrollAccess(Payroll $payroll): bool
    {
        if (Auth::user()?->hasRole('superadmin')) {
            return true;
        }
        if (Auth::user()->can('manage-any-payrolls')) {
            return (int) $payroll->created_by === (int) creatorId();
        } elseif (Auth::user()->can('manage-own-payrolls')) {
            return (int) $payroll->creator_id === (int) Auth::id() && (int) $payroll->created_by === (int) creatorId();
        }
        return false;
    }

    private function resolvePeriodSuffix(Payroll $payroll): string
    {
        $suffix = date('Y-m-d');
        if (
            function_exists('ethiopian_calendar_active')
            && ethiopian_calendar_active()
            && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)
            && $payroll->pay_period_start
        ) {
            try {
                $ethSvc = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
                $ethPeriod = $ethSvc->toEthiopian($payroll->pay_period_start);
                if ($ethPeriod) {
                    $suffix = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ethPeriod['month_name'] . '_' . $ethPeriod['year'] . '_EC');
                }
            } catch (\Throwable $e) {
                // fallback to date('Y-m-d')
            }
        }
        return $suffix;
    }

    private function applyLoanRepaymentOnPay(PayrollEntry $payrollEntry): void
    {
        $payroll = $payrollEntry->payroll;
        if (!$payroll || (float) $payrollEntry->total_loans <= 0) {
            return;
        }

        $startDate = Carbon::parse($payroll->pay_period_start)->format('Y-m-d');
        $endDate = Carbon::parse($payroll->pay_period_end)->format('Y-m-d');
        $companyId = (int) ($payrollEntry->created_by ?? creatorId());

        $loans = Loan::where('employee_id', $payrollEntry->employee_id)
            ->where('created_by', $companyId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                    });
            })
            ->get();

        $remainingToApply = (float) $payrollEntry->total_loans;
        foreach ($loans as $loan) {
            if ($remainingToApply <= 0) {
                break;
            }
            $totalAmount = (float) $loan->amount;
            $paidAlready = (float) ($loan->paid_amount ?? 0);
            $loanRemaining = max(0.0, $totalAmount - $paidAlready);
            if ($loanRemaining <= 0) {
                continue;
            }
            $applied = min($remainingToApply, $loanRemaining);
            $newPaid = round($paidAlready + $applied, 2);
            $loan->paid_amount = $newPaid;
            if ($newPaid >= $totalAmount) {
                $loan->status = 'completed';
            }
            $loan->save();
            $remainingToApply -= $applied;
        }
    }

    public function index()
    {
        if (Auth::user()->can('manage-payrolls')) {
            $payrolls = Payroll::query()
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-payrolls')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-payrolls')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('title'), function ($q) {
                    $q->where(function ($query) {
                        $query->where('title', 'like', '%' . request('title') . '%');
                    });
                })
                ->when(request('payroll_frequency'), fn ($q) => $q->where('payroll_frequency', request('payroll_frequency')))
                ->when(request('status'), fn ($q) => $q->where('status', request('status')))
                ->when(true, fn($q) => $this->applySafeSort($q))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            return Inertia::render('Hrm/Payrolls/Index', [
                'payrolls' => $payrolls,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function store(StorePayrollRequest $request)
    {
        if (Auth::user()->can('create-payrolls')) {
            $validated = $request->validated();
            $payroll = new Payroll();
            $payroll->title = $validated['title'];
            $payroll->payroll_frequency = $validated['payroll_frequency'];
            $payroll->pay_period_start = $validated['pay_period_start'];
            $payroll->pay_period_end = $validated['pay_period_end'];
            $payroll->pay_date = $validated['pay_date'];
            $payroll->notes = $validated['notes'];

            if (
                function_exists('ethiopian_calendar_active')
                && ethiopian_calendar_active()
                && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)
            ) {
                try {
                    $eth = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)->toEthiopian($validated['pay_period_start']);
                    if ($eth) {
                        $payroll->ethiopian_period = $eth['month_name'] . ' ' . $eth['year'] . ' E.C.';
                    }
                } catch (\Throwable $e) {
                    // ignore optional calendar label
                }
            }

            $payroll->creator_id = Auth::id();
            $payroll->created_by = creatorId();
            $payroll->save();

            CreatePayroll::dispatch($request, $payroll);

            return redirect()->route('hrm.payrolls.index')->with('success', __('The payroll has been created successfully.'));
        }

        return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll)
    {
        if (Auth::user()->can('edit-payrolls')) {
            if (!$this->checkPayrollAccess($payroll)) {
                return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
            }

            $validated = $request->validated();
            $payroll->title = $validated['title'];
            $payroll->payroll_frequency = $validated['payroll_frequency'];
            $payroll->pay_period_start = $validated['pay_period_start'];
            $payroll->pay_period_end = $validated['pay_period_end'];
            $payroll->pay_date = $validated['pay_date'];
            $payroll->notes = $validated['notes'];
            $payroll->status = $validated['status'];
            $payroll->is_payroll_paid = $validated['is_payroll_paid'] ?? 'unpaid';
            $payroll->save();

            UpdatePayroll::dispatch($request, $payroll);

            return redirect()->back()->with('success', __('The payroll details are updated successfully.'));
        }

        return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
    }

    public function show(Payroll $payroll)
    {
        if (Auth::user()->can('view-payrolls')) {
            if (!$this->checkPayrollAccess($payroll)) {
                return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
            }

            $payroll->load(['payrollEntries' => function ($query) {
                if (Auth::user()->can('view-any-payrolls')) {
                    $query->with('employee.user')->where('created_by', creatorId());
                } elseif (Auth::user()->can('view-own-payrolls')) {
                    $query->with('employee.user')->where('employee_id', Auth::id());
                } else {
                    $query->whereRaw('1 = 0');
                }
            }]);

            return Inertia::render('Hrm/Payrolls/Show', [
                'payroll' => $payroll,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Payroll $payroll)
    {
        if (Auth::user()->can('delete-payrolls')) {
            if (!$this->checkPayrollAccess($payroll)) {
                return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
            }

            DestroyPayroll::dispatch($payroll);
            $payroll->delete();

            return redirect()->back()->with('success', __('The payroll has been deleted.'));
        }

        return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
    }

    public function runPayroll(Payroll $payroll)
    {
        if (Auth::user()->can('run-payrolls')) {
            if (!$this->checkPayrollAccess($payroll)) {
                return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
            }

            try {
                $payroll->update(['status' => 'processing']);

                $tenantId = (int) creatorId();
                $globalSettings = getCompanyAllSetting($tenantId);
                $workingDaysIndices = json_decode($globalSettings['working_days'] ?? '[]', true);

                if (empty($workingDaysIndices)) {
                    $payroll->update(['status' => 'draft']);
                    return redirect()->back()->with('error', __('Please configure working days first.'));
                }

                $startDate = new \DateTime($payroll->pay_period_start);
                $endDate = new \DateTime($payroll->pay_period_end);
                $workingDaysCount = 0;

                for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                    $dayIndex = (int) $date->format('w');
                    if (in_array($dayIndex, $workingDaysIndices)) {
                        $workingDaysCount++;
                    }
                }

                $periodStartStr = $startDate->format('Y-m-d');

                // Exclude disabled users and already-terminated / resigned staff prior to pay period start
                $terminatedIds = Termination::where('created_by', $tenantId)
                    ->where('status', 'approved')
                    ->whereDate('termination_date', '<', $periodStartStr)
                    ->pluck('employee_id')
                    ->toArray();

                $resignedIds = Resignation::where('created_by', $tenantId)
                    ->where('status', 'approved')
                    ->whereDate('last_working_date', '<', $periodStartStr)
                    ->pluck('employee_id')
                    ->toArray();

                $excludedUserIds = array_unique(array_merge($terminatedIds, $resignedIds));

                $employees = Employee::with('user')
                    ->where('created_by', $tenantId)
                    ->when(!empty($excludedUserIds), fn ($q) => $q->whereNotIn('user_id', $excludedUserIds))
                    ->whereHas('user', fn ($q) => $q->where('is_disable', 0))
                    ->get();

                $newEntriesCount = 0;
                $existingEmployeeIds = $payroll->payrollEntries()->pluck('employee_id')->all();

                foreach ($employees as $employee) {
                    if (!in_array($employee->user_id, $existingEmployeeIds, true)) {
                        $this->processEmployeePayroll($payroll, $employee, $workingDaysCount, $startDate, $endDate);
                        $newEntriesCount++;
                    }
                }

                $entries = $payroll->payrollEntries()->get();
                $payroll->update([
                    'status' => 'completed',
                    'total_gross_pay' => $entries->sum('gross_pay'),
                    'total_deductions' => $entries->sum('total_deductions'),
                    'total_net_pay' => $entries->sum('net_pay'),
                    'employee_count' => $entries->count(),
                ]);

                if ($newEntriesCount > 0) {
                    return redirect()->back()->with('success', __('Payroll processed successfully. New payslips created for :new employees. Total employees: :total', [
                        'new' => $newEntriesCount,
                        'total' => $entries->count(),
                    ]));
                }

                return redirect()->back()->with('error', __('Payroll already processed. All active employee payslips are created. Total employees: :count', [
                    'count' => $entries->count(),
                ]));
            } catch (\Exception $e) {
                $payroll->update(['status' => 'draft']);
                return redirect()->back()->with('error', __('Failed to process payroll: :error', ['error' => $e->getMessage()]));
            }
        }

        return redirect()->back()->with('error', __('Permission denied'));
    }

    private function processEmployeePayroll($payroll, $employee, $workingDaysCount, $startDate, $endDate)
    {
        $basicSalary = (float) ($employee->basic_salary ?? 0);
        $perDaySalary = $workingDaysCount > 0 ? $basicSalary / $workingDaysCount : 0;

        $allowanceData = $this->calculateAllowances($employee, $basicSalary);
        $deductionData = $this->calculateDeductions($employee, $basicSalary);
        $manualOvertimeData = $this->calculateOvertimes($employee, $basicSalary, $startDate, $endDate);
        $loanData = $this->calculateLoans($employee, $basicSalary, $startDate, $endDate);

        $allowancesBreakdown = $allowanceData['breakdown'];
        $totalAllowances = $allowanceData['total'];

        $deductionsBreakdown = $deductionData['breakdown'];
        $totalDeductions = $deductionData['total'];

        $manualOvertimesBreakdown = $manualOvertimeData['breakdown'];
        $totalManualOvertimes = $manualOvertimeData['total'];
        $totalManualOvertimeHour = $manualOvertimeData['totalManualOvertimeHour'];

        $loansBreakdown = $loanData['breakdown'];
        $totalLoans = $loanData['total'];

        $attendanceData = $this->calculateAttendance($employee, $startDate, $endDate);
        $presentDays = $attendanceData['present_days'];
        $halfDays = $attendanceData['half_days'];
        $absentDays = $attendanceData['absent_days'];
        $overtimeHours = $attendanceData['overtime_hours'];
        $overtimeAmount = $attendanceData['overtime_amount'];

        $leaveData = $this->calculateLeave($employee, $startDate, $endDate);
        $paidLeaveDays = $leaveData['paid_leave_days'];
        $unpaidLeaveDays = $leaveData['unpaid_leave_days'];

        $totalAllOverTimeHours = $totalManualOvertimeHour + $overtimeHours;
        $totalEarnings = $basicSalary + $totalAllowances + $totalManualOvertimes;
        $halfDayDeduction = $perDaySalary * ($halfDays * 0.5);
        $absentDayDeduction = $perDaySalary * $absentDays;
        $unpaidLeaveDeduction = $perDaySalary * $unpaidLeaveDays;
        $totalLeaveSalaryDeductions = $unpaidLeaveDeduction + $halfDayDeduction + $absentDayDeduction;
        $grossPay = max(0.0, $totalEarnings - $totalLeaveSalaryDeductions + $overtimeAmount);

        $settings = getCompanyAllSetting($payroll->created_by);
        $companyCurrency = $settings['defult_currancy'] ?? $settings['site_currency'] ?? 'USD';
        $useEthiopianTax = ($settings['useEthiopianTax'] ?? 'off') === 'on' || strtoupper($companyCurrency) === 'ETB';

        $employeePension = 0.0;
        $employerPension = 0.0;
        $incomeTax = 0.0;

        if ($useEthiopianTax) {
            $taxableBaseEarnings = max(0.0, $basicSalary - $totalLeaveSalaryDeductions);
            $totalOvertimeEarned = $totalManualOvertimes + $overtimeAmount;
            $isPensionApplicable = $employee->is_pension_applicable ?? true;
            $isTaxExempt = (bool) ($employee->is_tax_exempt ?? false);

            $ethTax = $this->ethiopianPayrollService->calculatePayrollTaxes(
                $basicSalary,
                $taxableBaseEarnings,
                $allowancesBreakdown,
                $totalOvertimeEarned,
                $isPensionApplicable,
                $isTaxExempt,
                $settings
            );

            $employeePension = $ethTax['employee_pension'];
            $employerPension = $ethTax['employer_pension'];
            $incomeTax = $ethTax['income_tax'];

            if ($employeePension > 0) {
                $deductionsBreakdown['Employee Pension (7%) / የሰራተኛ ጡረታ'] = $employeePension;
            }
            if ($incomeTax > 0) {
                $deductionsBreakdown['Income Tax (PIT) / የስራ ግብር'] = $incomeTax;
            }
            if ($employerPension > 0) {
                $deductionsBreakdown['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] = $employerPension;
            }

            $totalDeductions += $employeePension + $incomeTax;
        }

        $totalAllDeductions = $totalDeductions + $totalLoans;
        $netPay = max(0.0, round($grossPay - $totalAllDeductions, 2));

        PayrollEntry::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->user_id,
            'basic_salary' => $basicSalary,
            'total_allowances' => $totalAllowances,
            'total_deductions' => $totalDeductions,
            'employee_pension' => $employeePension,
            'employer_pension' => $employerPension,
            'income_tax' => $incomeTax,
            'total_loans' => $totalLoans,
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
            'per_day_salary' => $perDaySalary,
            'working_days' => $workingDaysCount,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'half_day_deduction' => $halfDayDeduction,
            'absent_days' => $absentDays,
            'absent_day_deduction' => $absentDayDeduction,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'manual_overtime_hours' => $totalManualOvertimeHour,
            'total_manual_overtimes' => $totalManualOvertimes,
            'attendance_overtime_hours' => $overtimeHours,
            'attendance_overtime_rate' => $employee->rate_per_hour ?? 0,
            'attendance_overtime_amount' => $overtimeAmount,
            'overtime_hours' => $totalAllOverTimeHours,
            'allowances_breakdown' => $allowancesBreakdown,
            'deductions_breakdown' => $deductionsBreakdown,
            'manual_overtimes_breakdown' => $manualOvertimesBreakdown,
            'loans_breakdown' => $loansBreakdown,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);
    }

    private function calculateAllowances($employee, $basicSalary)
    {
        $allowances = Allowance::with('allowanceType')
            ->where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->get();
        $breakdown = [];
        $total = 0;

        foreach ($allowances as $allowance) {
            $name = $allowance->allowanceType->name ?? 'Allowance';
            $amount = $allowance->type === 'percentage'
                ? ($basicSalary * $allowance->amount) / 100
                : $allowance->amount;

            $breakdown[$name] = ($breakdown[$name] ?? 0) + (float) $amount;
            $total += (float) $amount;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    private function calculateDeductions($employee, $basicSalary)
    {
        $deductions = Deduction::with('deductionType')
            ->where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->get();
        $breakdown = [];
        $total = 0;

        foreach ($deductions as $deduction) {
            $name = $deduction->deductionType->name ?? 'Deduction';
            $amount = $deduction->type === 'percentage'
                ? ($basicSalary * $deduction->amount) / 100
                : $deduction->amount;

            $breakdown[$name] = ($breakdown[$name] ?? 0) + (float) $amount;
            $total += (float) $amount;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    private function calculateAttendance($employee, $startDate, $endDate)
    {
        $attendances = Attendance::where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $presentDays = $attendances->where('status', 'present')->count();
        $halfDays = $attendances->where('status', 'half day')->count();
        $absentDays = $attendances->where('status', 'absent')->count();
        $overtimeHours = $attendances->sum('overtime_hours') ?? 0;

        $attendanceOvertimeRate = $employee->rate_per_hour ?? 0;
        $overtimeAmount = $overtimeHours * $attendanceOvertimeRate;

        return [
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount
        ];
    }

    private function calculateLeave($employee, $startDate, $endDate)
    {
        $periodStart = Carbon::instance($startDate)->startOfDay();
        $periodEnd = Carbon::instance($endDate)->startOfDay();

        $leaveApplications = LeaveApplication::with('leave_type')
            ->where('created_by', creatorId())
            ->where('employee_id', $employee->user_id)
            ->where('status', 'approved')
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where('start_date', '<=', $periodEnd->toDateString())
                    ->where('end_date', '>=', $periodStart->toDateString());
            })
            ->get();

        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;

        foreach ($leaveApplications as $leave) {
            // Clamp leave interval strictly inside this payroll period to prevent multi-month double deduction
            $clampedStart = Carbon::parse($leave->start_date)->startOfDay()->max($periodStart);
            $clampedEnd = Carbon::parse($leave->end_date)->startOfDay()->min($periodEnd);
            if ($clampedEnd->lt($clampedStart)) {
                continue;
            }
            $leaveDays = $clampedStart->diffInDays($clampedEnd) + 1;

            if ($leave->leave_type && $leave->leave_type->is_paid) {
                $paidLeaveDays += $leaveDays;
            } else {
                $unpaidLeaveDays += $leaveDays;
            }
        }

        return [
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays
        ];
    }

    private function calculateOvertimes($employee, $basicSalary, $startDate, $endDate)
    {
        $overtimes = Overtime::where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->where('status', 'active')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })
            ->get();

        $breakdown = [];
        $total = 0;
        $totalManualOvertimeHour = 0;

        $standardHourlyRate = ($employee->rate_per_hour && (float) $employee->rate_per_hour > 0)
            ? (float) $employee->rate_per_hour
            : round($basicSalary / 208, 2);

        foreach ($overtimes as $overtime) {
            $name = $overtime->title ?? 'Manual Overtime';
            $hours = (float) $overtime->hours;
            $rate = (float) $overtime->rate;

            if ($rate <= 0 || $rate <= $standardHourlyRate) {
                $lowerTitle = strtolower($name);
                if (str_contains($lowerTitle, 'holiday') || str_contains($lowerTitle, 'የበዓል')) {
                    $rate = round($standardHourlyRate * 2.5, 2);
                } elseif (str_contains($lowerTitle, 'rest') || str_contains($lowerTitle, 'sunday') || str_contains($lowerTitle, 'የእረፍት')) {
                    $rate = round($standardHourlyRate * 2.0, 2);
                } elseif (str_contains($lowerTitle, 'night') || str_contains($lowerTitle, 'የሌሊት')) {
                    $rate = round($standardHourlyRate * 1.75, 2);
                } elseif ($rate <= 0) {
                    $rate = round($standardHourlyRate * 1.5, 2);
                }
            }

            $amount = $hours * $rate;
            $breakdown[$name] = $amount;
            $total += $amount;
            $totalManualOvertimeHour += $hours;
        }

        return ['breakdown' => $breakdown, 'total' => $total, 'totalManualOvertimeHour' => $totalManualOvertimeHour];
    }

    private function calculateLoans($employee, $basicSalary, $startDate, $endDate)
    {
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        $loans = Loan::with('loanType')
            ->where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->where(function ($query) use ($startStr, $endStr) {
                $query->whereBetween('start_date', [$startStr, $endStr])
                    ->orWhereBetween('end_date', [$startStr, $endStr])
                    ->orWhere(function ($q) use ($startStr, $endStr) {
                        $q->where('start_date', '<=', $startStr)->where('end_date', '>=', $endStr);
                    });
            })
            ->get();

        $breakdown = [];
        $total = 0;

        foreach ($loans as $loan) {
            $totalLoanAmt = (float) $loan->amount;
            $paidAmt = (float) ($loan->paid_amount ?? 0);
            $remainingBalance = max(0.0, $totalLoanAmt - $paidAmt);
            if ($remainingBalance <= 0) {
                continue;
            }

            $name = $loan->loanType->name ?? ($loan->title ?: 'Loan');
            $installment = $loan->type === 'percentage'
                ? ($basicSalary * (float) $loan->amount) / 100
                : $remainingBalance;

            $deducted = round(min($installment, $remainingBalance), 2);
            if ($deducted <= 0) {
                continue;
            }

            $breakdown[$name] = ($breakdown[$name] ?? 0) + $deducted;
            $total += $deducted;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    public function destroyEntry(PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('delete-payslip')) {
            $payroll = Payroll::findOrFail($payrollEntry->payroll_id);
            if (!$this->checkPayrollAccess($payroll)) {
                return redirect()->back()->with('error', __('Permission denied'));
            }

            DestroySalarySlip::dispatch($payrollEntry);
            $payrollEntry->delete();

            $entries = $payroll->payrollEntries()->get();
            $payroll->update([
                'total_gross_pay' => $entries->sum('gross_pay'),
                'total_deductions' => $entries->sum('total_deductions'),
                'total_net_pay' => $entries->sum('net_pay'),
                'employee_count' => $entries->count()
            ]);

            return redirect()->back()->with('success', __('Payroll entry deleted successfully.'));
        }

        return redirect()->back()->with('error', __('Permission denied'));
    }

    public function printPayslip(PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('download-payslip')) {
            if (!Auth::user()->hasRole('superadmin') && (int) $payrollEntry->created_by !== (int) creatorId()) {
                return redirect()->back()->with('error', __('Permission denied'));
            }

            $payrollEntry->load(['employee.user', 'employee.designation', 'payroll']);

            return Inertia::render('Hrm/Payrolls/payslip/Payslip', [
                'payrollEntry' => $payrollEntry,
            ]);
        }

        return redirect()->back()->with('error', __('Permission denied'));
    }

    public function paySalary(Request $request, PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('pay-payslip')) {
            if (!Auth::user()->hasRole('superadmin') && (int) $payrollEntry->created_by !== (int) creatorId()) {
                return redirect()->back()->with('error', __('Permission denied'));
            }
            if ($payrollEntry->status === 'paid') {
                return redirect()->back()->with('error', __('This payslip has already been marked as paid.'));
            }

            try {
                PaySalary::dispatch($request, $payrollEntry);
            } catch (\Throwable $th) {
                return redirect()->back()->with('error', $th->getMessage());
            }

            $payrollEntry->update(['status' => 'paid']);
            $this->applyLoanRepaymentOnPay($payrollEntry);

            $payroll = $payrollEntry->payroll;
            if ($payroll && $payroll->payrollEntries()->where('status', '!=', 'paid')->count() === 0) {
                $payroll->update(['is_payroll_paid' => 'paid']);
            }

            return redirect()->back()->with('success', __('Payment status updated successfully.'));
        }

        return redirect()->back()->with('error', __('Permission denied'));
    }

    /**
     * Pay all unpaid entries in a payroll run cleanly in one action.
     */
    public function bulkPay(Request $request, Payroll $payroll)
    {
        if (!Auth::user()->can('pay-payslip') || !$this->checkPayrollAccess($payroll)) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $unpaid = $payroll->payrollEntries()->where('status', '!=', 'paid')->get();
        if ($unpaid->isEmpty()) {
            return redirect()->back()->with('info', __('All payslips in this payroll are already paid.'));
        }

        $paidCount = 0;
        foreach ($unpaid as $entry) {
            try {
                PaySalary::dispatch($request, $entry);
                $entry->update(['status' => 'paid']);
                $this->applyLoanRepaymentOnPay($entry);
                $paidCount++;
            } catch (\Throwable $th) {
                return redirect()->back()->with('error', __('Stopped after :count payslips: :err', [
                    'count' => $paidCount,
                    'err' => $th->getMessage(),
                ]));
            }
        }

        if ($payroll->payrollEntries()->where('status', '!=', 'paid')->count() === 0) {
            $payroll->update(['is_payroll_paid' => 'paid']);
        }

        return redirect()->back()->with('success', __(':count payslips disbursed and posted to accounting successfully.', ['count' => $paidCount]));
    }

    /**
     * Export Ministry of Revenues (MOR) Ethiopian Monthly Tax Declaration CSV.
     */
    public function exportEthiopianTaxDeclaration(Payroll $payroll)
    {
        if (!$this->checkPayrollAccess($payroll)) {
            return back()->with('error', __('Permission denied'));
        }

        $payroll->load(['payrollEntries']);
        $tenantId = (int) ($payroll->created_by ?? creatorId());
        $employees = Employee::with('user')->where('created_by', $tenantId)->get()->keyBy('user_id');

        $filename = 'MOR_Tax_Declaration_Payroll_' . $payroll->id . '_' . $this->resolvePeriodSuffix($payroll) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($payroll, $employees) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No.',
                'Employee ID',
                'Full Name',
                'TIN Number',
                'Basic Salary (ETB)',
                'Non-Taxable Transport Allowance',
                'Taxable Allowances',
                'Overtime Pay',
                'Gross Pay',
                'Employee Pension (7%)',
                'Employer Pension (11%)',
                'Total Pension (18%)',
                'Taxable Income',
                'Employment Income Tax (PIT)',
                'Total Deductions',
                'Net Salary Payable (ETB)',
            ]);

            $i = 1;
            foreach ($payroll->payrollEntries as $entry) {
                $employee = $employees->get($entry->employee_id);
                $user = $employee?->user;
                $deductions = is_array($entry->deductions_breakdown) ? $entry->deductions_breakdown : (json_decode($entry->deductions_breakdown, true) ?? []);
                $allowances = is_array($entry->allowances_breakdown) ? $entry->allowances_breakdown : (json_decode($entry->allowances_breakdown, true) ?? []);

                $empPension = ($entry->employee_pension ?? 0) > 0
                    ? (float) $entry->employee_pension
                    : (float) ($deductions['Employee Pension (7%) / የሰራተኛ ጡረታ'] ?? $deductions['Pension Contribution (7%)'] ?? 0);
                $emprPension = ($entry->employer_pension ?? 0) > 0
                    ? (float) $entry->employer_pension
                    : (float) ($deductions['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] ?? $deductions['Employer Pension (11% - Cost)'] ?? 0);
                $pit = ($entry->income_tax ?? 0) > 0
                    ? (float) $entry->income_tax
                    : (float) ($deductions['Income Tax (PIT) / የስራ ግብር'] ?? $deductions['Employment Income Tax (PIT)'] ?? 0);

                $split = $this->ethiopianPayrollService->splitTransportAllowance($allowances, (float) $entry->basic_salary);
                $exemptTransport = $split['exempt_transport'];
                $taxableAllowances = max(0.0, (float) $entry->total_allowances - $exemptTransport);
                $taxableIncome = max(0.0, (float) $entry->gross_pay - $exemptTransport - $empPension);

                fputcsv($handle, [
                    $i++,
                    $employee->employee_id ?? "EMP-{$entry->employee_id}",
                    $user->name ?? 'N/A',
                    $employee->tax_payer_id ?? 'N/A',
                    number_format((float) $entry->basic_salary, 2, '.', ''),
                    number_format($exemptTransport, 2, '.', ''),
                    number_format($taxableAllowances, 2, '.', ''),
                    number_format((float) ($entry->total_manual_overtimes + $entry->attendance_overtime_amount), 2, '.', ''),
                    number_format((float) $entry->gross_pay, 2, '.', ''),
                    number_format($empPension, 2, '.', ''),
                    number_format($emprPension, 2, '.', ''),
                    number_format($empPension + $emprPension, 2, '.', ''),
                    number_format($taxableIncome, 2, '.', ''),
                    number_format($pit, 2, '.', ''),
                    number_format((float) ($entry->total_deductions + $entry->total_loans), 2, '.', ''),
                    number_format((float) $entry->net_pay, 2, '.', ''),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Ethiopian POESSA / PSSSA Social Security Pension Contribution CSV (7% + 11% = 18%).
     */
    public function exportPoessaPension(Payroll $payroll)
    {
        if (!$this->checkPayrollAccess($payroll)) {
            return back()->with('error', __('Permission denied'));
        }

        $payroll->load(['payrollEntries']);
        $tenantId = (int) ($payroll->created_by ?? creatorId());
        $employees = Employee::with('user')->where('created_by', $tenantId)->get()->keyBy('user_id');

        $filename = 'POESSA_Pension_Declaration_' . $payroll->id . '_' . $this->resolvePeriodSuffix($payroll) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payroll, $employees) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No.',
                'Employee ID',
                'Full Name',
                'TIN Number',
                'Pension Eligible',
                'Basic Salary (ETB)',
                'Employee Share (7%)',
                'Employer Share (11%)',
                'Total Pension Contribution (18%)',
            ]);

            $i = 1;
            foreach ($payroll->payrollEntries as $entry) {
                $employee = $employees->get($entry->employee_id);
                $deductions = is_array($entry->deductions_breakdown) ? $entry->deductions_breakdown : (json_decode($entry->deductions_breakdown, true) ?? []);

                $empPension = ($entry->employee_pension ?? 0) > 0
                    ? (float) $entry->employee_pension
                    : (float) ($deductions['Employee Pension (7%) / የሰራተኛ ጡረታ'] ?? 0);
                $emprPension = ($entry->employer_pension ?? 0) > 0
                    ? (float) $entry->employer_pension
                    : (float) ($deductions['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] ?? 0);

                fputcsv($handle, [
                    $i++,
                    $employee->employee_id ?? "EMP-{$entry->employee_id}",
                    $employee?->user?->name ?? 'N/A',
                    $employee->tax_payer_id ?? 'N/A',
                    ($employee?->is_pension_applicable ?? true) ? 'Yes' : 'Exempt',
                    number_format((float) $entry->basic_salary, 2, '.', ''),
                    number_format($empPension, 2, '.', ''),
                    number_format($emprPension, 2, '.', ''),
                    number_format($empPension + $emprPension, 2, '.', ''),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export Bank / CBE Salary Transfer Disbursement Advice CSV.
     */
    public function exportBankAdvice(Payroll $payroll)
    {
        if (!$this->checkPayrollAccess($payroll)) {
            return back()->with('error', __('Permission denied'));
        }

        $payroll->load(['payrollEntries']);
        $tenantId = (int) ($payroll->created_by ?? creatorId());
        $employees = Employee::with('user')->where('created_by', $tenantId)->get()->keyBy('user_id');

        $filename = 'Bank_Salary_Transfer_Advice_' . $payroll->id . '_' . $this->resolvePeriodSuffix($payroll) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payroll, $employees) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No.',
                'Employee ID',
                'Employee Name',
                'Account Holder Name',
                'Bank Name',
                'Branch',
                'BIC / Bank Code',
                'Account Number',
                'Net Salary (ETB)',
                'Payment Reference',
                'Status',
            ]);

            $i = 1;
            foreach ($payroll->payrollEntries as $entry) {
                $employee = $employees->get($entry->employee_id);
                $userName = $employee?->user?->name ?? 'N/A';

                fputcsv($handle, [
                    $i++,
                    $employee->employee_id ?? "EMP-{$entry->employee_id}",
                    $userName,
                    $employee->account_holder_name ?? $userName,
                    $employee->bank_name ?? 'N/A',
                    $employee->bank_branch ?? 'N/A',
                    $employee->bank_identifier_code ?? 'N/A',
                    $employee->account_number ?? 'N/A',
                    number_format((float) $entry->net_pay, 2, '.', ''),
                    "PAYROLL-{$payroll->id}-{$entry->id}",
                    strtoupper((string) ($entry->status ?? 'unpaid')),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
