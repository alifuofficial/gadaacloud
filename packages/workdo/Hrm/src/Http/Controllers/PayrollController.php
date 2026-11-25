<?php

namespace Workdo\Hrm\Http\Controllers;

use Workdo\Hrm\Models\Payroll;
use Workdo\Hrm\Http\Requests\StorePayrollRequest;
use Workdo\Hrm\Http\Requests\UpdatePayrollRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\Hrm\Models\Allowance;
use Workdo\Hrm\Models\AllowanceType;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\Deduction;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\LeaveApplication;
use Workdo\Hrm\Models\Loan;
use Workdo\Hrm\Models\Overtime;
use Workdo\Hrm\Models\PayrollEntry;
use App\Models\User;
use Workdo\Hrm\Events\CreatePayroll;
use Workdo\Hrm\Events\UpdatePayroll;
use Workdo\Hrm\Events\DestroyPayroll;
use Workdo\Hrm\Events\DestroySalarySlip;
use Workdo\Hrm\Events\PaySalary;

class PayrollController extends Controller
{
    private function checkPayrollAccess(Payroll $payroll)
    {
        if(Auth::user()->can('manage-any-payrolls')) {
            return $payroll->created_by == creatorId();
        } elseif(Auth::user()->can('manage-own-payrolls')) {
            return $payroll->creator_id == Auth::id();
        }
        return false;
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
                ->when(request('payroll_frequency') !== null && request('payroll_frequency') !== '', fn($q) => $q->where('payroll_frequency', request('payroll_frequency')))
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Hrm/Payrolls/Index', [
                'payrolls' => $payrolls,

            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
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

            $payroll->creator_id = Auth::id();
            $payroll->created_by = creatorId();
            $payroll->save();

            CreatePayroll::dispatch($request, $payroll);

            return redirect()->route('hrm.payrolls.index')->with('success', __('The payroll has been created successfully.'));
        } else {
            return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll)
    {
        if (Auth::user()->can('edit-payrolls')) {
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
        } else {
            return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
        }
    }

    public function show(Payroll $payroll)
    {
        if (Auth::user()->can('view-payrolls')) {
            if(!$this->checkPayrollAccess($payroll)) {
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
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(Payroll $payroll)
    {
        if (Auth::user()->can('delete-payrolls')) {
            DestroyPayroll::dispatch($payroll);
            $payroll->delete();

            return redirect()->back()->with('success', __('The payroll has been deleted.'));
        } else {
            return redirect()->route('hrm.payrolls.index')->with('error', __('Permission denied'));
        }
    }

    public function runPayroll(Payroll $payroll)
    {
        if (Auth::user()->can('run-payrolls')) {

            try {
                $payroll->update(['status' => 'processing']);

                // Get working days from settings
                $globalSettings = getCompanyAllSetting();
                $workingDaysIndices = json_decode($globalSettings['working_days'] ?? '[]', true);

                if (empty($workingDaysIndices)) {
                    $payroll->update(['status' => 'draft']);
                    return redirect()->back()->with('error', __('Please configure working days first.'));
                }

                // Calculate working days in pay period
                $startDate = new \DateTime($payroll->pay_period_start);
                $endDate = new \DateTime($payroll->pay_period_end);
                $workingDaysCount = 0;

                for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                    $dayIndex = (int) $date->format('w');
                    if (in_array($dayIndex, $workingDaysIndices)) {
                        $workingDaysCount++;
                    }
                }


                // Get all employees
                $employees = Employee::with('user')->where('created_by', creatorId())->get();
                $newEntriesCount = 0;

                foreach ($employees as $employee) {
                    // Check if payroll entry already exists for this employee
                    $existingEntry = PayrollEntry::where('payroll_id', $payroll->id)
                        ->where('employee_id', $employee->user_id)
                        ->first();

                    if (!$existingEntry) {
                        $this->processEmployeePayroll($payroll, $employee, $workingDaysCount, $startDate, $endDate);
                        $newEntriesCount++;
                    }
                }

                // Calculate totals from entries
                $entries = $payroll->payrollEntries;
                $totalGrossPay = $entries->sum('gross_pay');
                $totalDeductions = $entries->sum('total_deductions');
                $totalNetPay = $entries->sum('net_pay');
                $employeeCount = $entries->count();

                // Update payroll totals
                $payroll->update([
                    'status' => 'completed',
                    'total_gross_pay' => $totalGrossPay,
                    'total_deductions' => $totalDeductions,
                    'total_net_pay' => $totalNetPay,
                    'employee_count' => $employeeCount
                ]);

                if ($newEntriesCount > 0) {
                    return redirect()->back()->with('success', __('Payroll processed successfully. New payslips created for :new employees. Total employees: :total', [
                        'new' => $newEntriesCount,
                        'total' => $entries->count(),
                    ]));
                } else {
                    return redirect()->back()->with('error', __('Payroll already processed. All employee payslips are created. Total employees: :count', [
                        'count' => $entries->count(),
                    ]));
                }
            } catch (\Exception $e) {
                $payroll->update(['status' => 'draft']);
                return redirect()->back()->with('error', __('Failed to process payroll: :error', ['error' => $e->getMessage()]));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    private function processEmployeePayroll($payroll, $employee, $workingDaysCount, $startDate, $endDate)
    {
        // Get employee basic salary
        $basicSalary = $employee->basic_salary ?? 0;
        $perDaySalary = $workingDaysCount > 0 ? $basicSalary / $workingDaysCount : 0;

        // Calculate allowances, deductions, overtimes and loans
        $allowanceData = $this->calculateAllowances($employee, $basicSalary);
        $deductionData = $this->calculateDeductions($employee, $basicSalary);
        $manualOvertimeData = $this->calculateOvertimes($employee, $basicSalary, $startDate, $endDate);
        $loanData = $this->calculateLoans($employee, $basicSalary, $startDate, $endDate);


        // Allowance breakdown
        $allowancesBreakdown = $allowanceData['breakdown'];
        $totalAllowances = $allowanceData['total'];

        // deduction breakdown
        $deductionsBreakdown = $deductionData['breakdown'];
        $totalDeductions = $deductionData['total'];

        // manual overtime breakdown
        $manualOvertimesBreakdown = $manualOvertimeData['breakdown'];
        $totalManualOvertimes = $manualOvertimeData['total'];
        $totalManualOvertimeHour =  $manualOvertimeData['totalManualOvertimeHour'];

        // loan breakdown
        $loansBreakdown = $loanData['breakdown'];
        $totalLoans = $loanData['total'];


        // Calculate attendance data
        $attendanceData = $this->calculateAttendance($employee, $startDate, $endDate);

        $presentDays = $attendanceData['present_days'];
        $halfDays = $attendanceData['half_days'];
        $absentDays = $attendanceData['absent_days'];
        $overtimeHours = $attendanceData['overtime_hours'];
        $overtimeAmount = $attendanceData['overtime_amount'];


        // Calculate leave data
        $leaveData = $this->calculateLeave($employee, $startDate, $endDate);

        $paidLeaveDays = $leaveData['paid_leave_days'];
        $unpaidLeaveDays = $leaveData['unpaid_leave_days'];

        $totalAllOverTimeHours =  $totalManualOvertimeHour + $overtimeHours;
        // Calculate final salary
        $totalEarnings = $basicSalary + $totalAllowances + $totalManualOvertimes;
        $halfDayDeduction = $perDaySalary * ($halfDays * 0.5);
        $absentDayDeduction = $perDaySalary * $absentDays;
        $unpaidLeaveDeduction = $perDaySalary * $unpaidLeaveDays;
        $totalLeaveSalaryDeductions = $unpaidLeaveDeduction + $halfDayDeduction + $absentDayDeduction;
        $grossPay = $totalEarnings - $totalLeaveSalaryDeductions + $overtimeAmount; // overtimeAmount is from attendance

        // Apply Ethiopian Tax System if configured or operating in ETB
        $settings = getCompanyAllSetting($payroll->created_by);
        $companyCurrency = $settings['defult_currancy'] ?? $settings['site_currency'] ?? 'USD';
        $useEthiopianTax = ($settings['useEthiopianTax'] ?? 'off') === 'on' || strtoupper($companyCurrency) === 'ETB';

        if ($useEthiopianTax) {
            $employeePension = round($basicSalary * 0.07, 2);
            $employerPension = round($basicSalary * 0.11, 2);

            // Separate Transport Allowance under Ethiopian Directive No. 02/2008 & Proclamation No. 979/2016
            // Transport allowance is tax-exempt up to the lower of 25% of basic salary or 2,200 ETB
            $transportAllowance = 0;
            $taxableAllowances = 0;
            foreach ($allowancesBreakdown as $allowanceName => $allowanceAmt) {
                if (stripos($allowanceName, 'transport') !== false || stripos($allowanceName, 'የትራንስፖርት') !== false) {
                    $transportAllowance += (float) $allowanceAmt;
                } else {
                    $taxableAllowances += (float) $allowanceAmt;
                }
            }

            $transportExemptionCap = min($basicSalary * 0.25, 2200.0);
            $nonTaxableTransport = min($transportAllowance, $transportExemptionCap);
            $taxableTransport = max(0, $transportAllowance - $transportExemptionCap);

            // Total Overtime earnings (Attendance OT + Manual OT)
            $totalOvertimeEarned = $totalManualOvertimes + $overtimeAmount;

            // Adjusted Gross Pay considering unpaid leaves / absence deductions
            $taxableBaseEarnings = max(0, $basicSalary - $totalLeaveSalaryDeductions);

            // Ethiopian Taxable Income = Basic (after unpaids) + Taxable Transport + Other Allowances + Overtime - 7% Pension
            $taxableIncome = max(0, ($taxableBaseEarnings + $taxableTransport + $taxableAllowances + $totalOvertimeEarned) - $employeePension);

            // Progressive tax rates for Employment Income (PIT) - Proclamation No. 979/2016
            if ($taxableIncome <= 600) {
                $incomeTax = 0;
            } elseif ($taxableIncome <= 1650) {
                $incomeTax = ($taxableIncome * 0.10) - 60;
            } elseif ($taxableIncome <= 3200) {
                $incomeTax = ($taxableIncome * 0.15) - 142.50;
            } elseif ($taxableIncome <= 5250) {
                $incomeTax = ($taxableIncome * 0.20) - 302.50;
            } elseif ($taxableIncome <= 7800) {
                $incomeTax = ($taxableIncome * 0.25) - 565.00;
            } elseif ($taxableIncome <= 10900) {
                $incomeTax = ($taxableIncome * 0.30) - 955.00;
            } else {
                $incomeTax = ($taxableIncome * 0.35) - 1500.00;
            }
            $incomeTax = max(0, round($incomeTax, 2));

            // Record Ethiopian breakdown elements
            $deductionsBreakdown['Employee Pension (7%) / የሰራተኛ ጡረታ'] = $employeePension;
            $deductionsBreakdown['Income Tax (PIT) / የስራ ግብር'] = $incomeTax;
            $deductionsBreakdown['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] = $employerPension;

            if ($nonTaxableTransport > 0) {
                $allowancesBreakdown['Transport (Tax Exempt) / ከቀረጥ ነፃ'] = $nonTaxableTransport;
                if ($taxableTransport > 0) {
                    $allowancesBreakdown['Transport (Taxable Excess) / ግብር የሚከፈልበት'] = $taxableTransport;
                }
            }

            $totalDeductions += $employeePension + $incomeTax;
        }

        $totalAllDeductions = $totalDeductions + $totalLoans;
        $netPay = $grossPay - $totalAllDeductions;

        // Create payroll entry
        PayrollEntry::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->user_id,
            'basic_salary' => $basicSalary,
            'total_allowances' => $totalAllowances,

            'total_deductions' => $totalDeductions,
            'total_loans' => $totalLoans,
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
            'per_day_salary' => $perDaySalary,

            // Days
            'working_days' => $workingDaysCount,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'half_day_deduction' => $halfDayDeduction,
            'absent_days' => $absentDays,
            'absent_day_deduction' => $absentDayDeduction,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,

            // OverTime
            'manual_overtime_hours' => $totalManualOvertimeHour,
            'total_manual_overtimes' => $totalManualOvertimes,
            'attendance_overtime_hours' => $overtimeHours,
            'attendance_overtime_rate' => $employee->rate_per_hour ?? 0,
            'attendance_overtime_amount' => $overtimeAmount,
            'overtime_hours' => $totalAllOverTimeHours,

            // Breakdown JSONs
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
        $allowances = Allowance::where('employee_id', $employee->user_id)->where('created_by', creatorId())->get();
        $breakdown = [];
        $total = 0;

        foreach ($allowances as $allowance) {
            $allowanceType = $allowance->allowanceType;
            $name = $allowanceType->name ?? 'Allowance';

            if ($allowance && $allowance->type === 'percentage') {
                $amount = ($basicSalary * $allowance->amount) / 100;
            } else {
                $amount = $allowance->amount;
            }

            $breakdown[$name] = $amount;
            $total += $amount;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    private function calculateDeductions($employee, $basicSalary)
    {
        $deductions = Deduction::where('employee_id', $employee->user_id)->where('created_by', creatorId())->get();
        $breakdown = [];
        $total = 0;

        foreach ($deductions as $deduction) {
            $deductionType = $deduction->deductionType;
            $name = $deductionType->name ?? 'Deduction';

            if ($deduction && $deduction->type === 'percentage') {
                $amount = ($basicSalary * $deduction->amount) / 100;
            } else {
                $amount = $deduction->amount;
            }

            $breakdown[$name] = $amount;
            $total += $amount;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    private function calculateAttendance($employee, $startDate, $endDate)
    {
        $attendances = Attendance::where('employee_id', $employee->user_id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $presentDays = $attendances->where('status', 'present')->count();
        $halfDays = $attendances->where('status', 'half day')->count();
        $absentDays = $attendances->where('status', 'absent')->count();
        $overtimeHours = $attendances->sum('overtime_hours') ?? 0;

        // Calculate overtime amount using employee overtime rate
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
        $leaveApplications = LeaveApplication::with('leave_type')->where('employee_id', $employee->user_id)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', values: [$startDate, $endDate]);
            })
            ->get();

        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;

        foreach ($leaveApplications as $leave) {
            $leaveDays = max(1, $leave->start_date->diffInDays($leave->end_date) + 1);
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

        // Ethiopian standard working hours: 208 hours/month (48 hrs/week standard, Labour Proclamation No. 1156/2019)
        $standardHourlyRate = ($employee->rate_per_hour && (float) $employee->rate_per_hour > 0)
            ? (float) $employee->rate_per_hour
            : round($basicSalary / 208, 2);

        foreach ($overtimes as $overtime) {
            $name = $overtime->title ?? 'Manual Overtime';
            $hours = (float) $overtime->hours;
            $rate = (float) $overtime->rate;

            // If rate is 0 or matches standard base, auto-apply Ethiopian statutory overtime multipliers
            if ($rate <= 0 || $rate <= $standardHourlyRate) {
                $lowerTitle = strtolower($name);
                if (str_contains($lowerTitle, 'holiday') || str_contains($lowerTitle, 'የበዓል')) {
                    $rate = round($standardHourlyRate * 2.5, 2); // 2.5x Public Holiday
                } elseif (str_contains($lowerTitle, 'rest') || str_contains($lowerTitle, 'sunday') || str_contains($lowerTitle, 'የእረፍት')) {
                    $rate = round($standardHourlyRate * 2.0, 2); // 2.0x Rest Day
                } elseif (str_contains($lowerTitle, 'night') || str_contains($lowerTitle, 'የሌሊት')) {
                    $rate = round($standardHourlyRate * 1.75, 2); // 1.75x Night (10PM - 6AM)
                } elseif ($rate <= 0) {
                    $rate = round($standardHourlyRate * 1.5, 2); // 1.5x Standard Day Overtime (6AM - 10PM)
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
        $loans = Loan::with('loanType')->where('employee_id', $employee->user_id)
            ->where('created_by', creatorId())
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })
            ->get();

        $breakdown = [];
        $total = 0;

        foreach ($loans as $loan) {
            $loanType = $loan->loanType;
            $name = $loanType->name ?? 'Loan';

            if ($loan->type === 'percentage') {
                $amount = ($basicSalary * $loan->amount) / 100;
            } else {
                $amount = $loan->amount;
            }

            $breakdown[$name] = $amount;
            $total += $amount;
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }

    public function destroyEntry(PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('delete-payslip')) {
            $payroll = Payroll::find($payrollEntry->payroll_id);
            DestroySalarySlip::dispatch($payrollEntry);
            // Delete the entry
            $payrollEntry->delete();

            // Recalculate totals from remaining entries
            $entries = $payroll->payrollEntries;
            $totalGrossPay = $entries->sum('gross_pay');
            $totalDeductions = $entries->sum('total_deductions');
            $totalNetPay = $entries->sum('net_pay');
            $employeeCount = $entries->count();

            // Update payroll totals
            $payroll->update([
                'total_gross_pay' => $totalGrossPay,
                'total_deductions' => $totalDeductions,
                'total_net_pay' => $totalNetPay,
                'employee_count' => $employeeCount
            ]);

            return redirect()->back()->with('success', __('Payroll entry deleted successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function printPayslip(PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('download-payslip')) {
            $payrollEntry->load(['employee.user', 'employee.designation', 'payroll']);

            return Inertia::render('Hrm/Payrolls/payslip/Payslip', [
                'payrollEntry' => $payrollEntry,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function paySalary(Request $request, PayrollEntry $payrollEntry)
    {
        if (Auth::user()->can('pay-payslip')) {
            try {
                PaySalary::dispatch($request, $payrollEntry);
            } catch (\Throwable $th) {
                return redirect()->back()->with('error', $th->getMessage());
            }
            $payrollEntry->update(['status' => 'paid']);

            // Check if all payroll entries are paid and update payroll status
            $payroll = $payrollEntry->payroll;
            $unpaidEntries = $payroll->payrollEntries()->where('status', '!=', 'paid')->count();
            if ($unpaidEntries === 0) {
                $payroll->update(['is_payroll_paid' => 'paid']);
            }

            return redirect()->back()->with('success', __('Payment status updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
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

        $periodSuffix = date('Y-m-d');
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
                    $periodSuffix = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ethPeriod['month_name'] . '_' . $ethPeriod['year'] . '_EC');
                }
            } catch (\Throwable $e) {
                // fallback to date('Y-m-d')
            }
        }

        $filename = "MOR_Tax_Declaration_Payroll_" . $payroll->id . "_" . $periodSuffix . ".csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($payroll) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM for Microsoft Excel compatibility with Amharic/Ge'ez text
            fputs($handle, "\xEF\xBB\xBF");

            // Header Row according to Ministry of Revenues format
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
                $employee = Employee::where('user_id', $entry->employee_id)->first();
                $user = $employee ? User::find($employee->user_id) : User::find($entry->employee_id);
                $deductions = is_array($entry->deductions_breakdown) ? $entry->deductions_breakdown : (json_decode($entry->deductions_breakdown, true) ?? []);
                $allowances = is_array($entry->allowances_breakdown) ? $entry->allowances_breakdown : (json_decode($entry->allowances_breakdown, true) ?? []);

                $empPension = $deductions['Employee Pension (7%) / የሰራተኛ ጡረታ'] ?? $deductions['Pension Contribution (7%)'] ?? round($entry->basic_salary * 0.07, 2);
                $emprPension = $deductions['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] ?? $deductions['Employer Pension (11% - Cost)'] ?? round($entry->basic_salary * 0.11, 2);
                $pit = $deductions['Income Tax (PIT) / የስራ ግብር'] ?? $deductions['Employment Income Tax (PIT)'] ?? 0;
                $exemptTransport = $allowances['Transport (Tax Exempt) / ከቀረጥ ነፃ'] ?? 0;

                $taxableAllowances = max(0, $entry->total_allowances - $exemptTransport);
                $taxableIncome = max(0, $entry->gross_pay - $exemptTransport - $empPension);

                fputcsv($handle, [
                    $i++,
                    $employee->employee_id ?? "EMP-{$entry->employee_id}",
                    $user->name ?? 'N/A',
                    $employee->tax_payer_id ?? 'N/A',
                    number_format((float) $entry->basic_salary, 2, '.', ''),
                    number_format((float) $exemptTransport, 2, '.', ''),
                    number_format((float) $taxableAllowances, 2, '.', ''),
                    number_format((float) ($entry->total_manual_overtimes + $entry->attendance_overtime_amount), 2, '.', ''),
                    number_format((float) $entry->gross_pay, 2, '.', ''),
                    number_format((float) $empPension, 2, '.', ''),
                    number_format((float) $emprPension, 2, '.', ''),
                    number_format((float) ($empPension + $emprPension), 2, '.', ''),
                    number_format((float) $taxableIncome, 2, '.', ''),
                    number_format((float) $pit, 2, '.', ''),
                    number_format((float) ($entry->total_deductions + $entry->total_loans), 2, '.', ''),
                    number_format((float) $entry->net_pay, 2, '.', ''),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
