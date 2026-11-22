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
        $joinDate = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : Carbon::today();

        $diff = $joinDate->diff($terminationDate);
        $totalYears = $diff->y;
        $totalMonths = $diff->m;
        $totalDays = $diff->d;

        $basicSalary = (float) $employee->basic_salary;
        $dailyWage = round($basicSalary / 30, 2);

        // Statutory Notice Period (Article 35)
        if ($totalYears < 1) {
            $requiredNoticeDays = 30; // 1 month
        } elseif ($totalYears <= 9) {
            $requiredNoticeDays = 60; // 2 months
        } else {
            $requiredNoticeDays = 90; // 3 months
        }

        $actualNoticeDays = $noticeDate ? $noticeDate->diffInDays($terminationDate) : 0;
        $noticeShortfallDays = max(0, $requiredNoticeDays - $actualNoticeDays);
        $noticeCompensation = round($dailyWage * $noticeShortfallDays, 2);

        // Statutory Severance Pay (Article 39)
        if ($totalYears < 1) {
            $severancePay = round(($totalMonths / 12) * $basicSalary, 2);
        } else {
            $firstYearPay = $basicSalary;
            $subsequentYearsPay = ($totalYears - 1) * ($basicSalary / 3);
            $proratedMonthsPay = ($totalMonths / 12) * ($basicSalary / 3);
            $severancePay = round($firstYearPay + $subsequentYearsPay + $proratedMonthsPay, 2);
        }

        // Maximum statutory severance ceiling: 12 months basic wage (Article 39(2))
        $maxSeveranceCap = $basicSalary * 12;
        $severancePay = min($severancePay, $maxSeveranceCap);

        // Outstanding Loans deduction
        $outstandingLoans = Loan::where('employee_id', $employeeId)
            ->where('created_by', creatorId())
            ->sum('amount');

        $netFinalSettlement = max(0, ($severancePay + $noticeCompensation) - $outstandingLoans);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name ?? 'Employee',
                'joining_date' => $joinDate->format('Y-m-d'),
                'termination_date' => $terminationDate->format('Y-m-d'),
                'service_duration' => "{$totalYears} Years, {$totalMonths} Months, {$totalDays} Days",
                'basic_salary' => $basicSalary,
                'daily_wage' => $dailyWage,
            ],
            'settlement' => [
                'required_notice_days' => $requiredNoticeDays,
                'actual_notice_days' => $actualNoticeDays,
                'notice_shortfall_days' => $noticeShortfallDays,
                'notice_shortfall_compensation' => $noticeCompensation,
                'severance_pay' => $severancePay,
                'outstanding_loan_deductions' => (float) $outstandingLoans,
                'net_final_settlement' => $netFinalSettlement,
                'legal_reference' => 'FDRE Labour Proclamation No. 1156/2019 (Articles 35, 39 & 40)'
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

        if (!Auth::user()->hasRole('superadmin') && $termination->created_by !== creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $termination->load(['employee', 'terminationType']);
        $employeeModel = Employee::where('user_id', $termination->employee_id)->first();

        $joinDate = $employeeModel && $employeeModel->date_of_joining ? Carbon::parse($employeeModel->date_of_joining) : Carbon::today();
        $termDate = $termination->termination_date ? Carbon::parse($termination->termination_date) : Carbon::today();
        $diff = $joinDate->diff($termDate);

        $basicSalary = $employeeModel ? (float) $employeeModel->basic_salary : 0;
        $totalYears = $diff->y;
        $totalMonths = $diff->m;

        // Severance calculation
        if ($totalYears < 1) {
            $severancePay = round(($totalMonths / 12) * $basicSalary, 2);
        } else {
            $firstYearPay = $basicSalary;
            $subsequentYearsPay = ($totalYears - 1) * ($basicSalary / 3);
            $proratedMonthsPay = ($totalMonths / 12) * ($basicSalary / 3);
            $severancePay = round($firstYearPay + $subsequentYearsPay + $proratedMonthsPay, 2);
        }
        $severancePay = min($severancePay, $basicSalary * 12);

        $outstandingLoans = Loan::where('employee_id', $termination->employee_id)
            ->where('created_by', creatorId())
            ->sum('amount');

        $netSettlement = max(0, $severancePay - $outstandingLoans);

        $settings = getCompanyAllSetting($termination->created_by);
        $companyName = $settings['company_name'] ?? config('app.name', 'GadaaCloud');
        $companyVat = $settings['vat_number'] ?? $settings['tax_number'] ?? 'N/A';

        return response()->view('terminations.settlement_voucher', [
            'termination' => $termination,
            'employee' => $employeeModel,
            'serviceDuration' => "{$diff->y} Years, {$diff->m} Months, {$diff->d} Days",
            'basicSalary' => $basicSalary,
            'severancePay' => $severancePay,
            'outstandingLoans' => $outstandingLoans,
            'netSettlement' => $netSettlement,
            'companyName' => $companyName,
            'companyVat' => $companyVat,
        ]);
    }
}
