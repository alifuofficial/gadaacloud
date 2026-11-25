<?php

namespace Workdo\Account\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Models\AccountingPeriod;

class AccountingPeriodController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-accounting-periods') && !Auth::user()->can('manage-account-types')) {
            return back()->with('error', __('Permission denied'));
        }

        $periods = AccountingPeriod::where('created_by', creatorId())
            ->with('lockedByUser:id,name')
            ->when($request->fiscal_year, fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->orderByDesc('start_date')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Account/SystemSetup/AccountingPeriods/Index', [
            'periods'     => $periods,
            'fiscalYears' => AccountingPeriod::where('created_by', creatorId())
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'filters'     => $request->only(['fiscal_year']),
        ]);
    }

    public function generateYear(Request $request)
    {
        if (!Auth::user()->can('manage-accounting-periods') && !Auth::user()->can('manage-account-types')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'fiscal_year'   => 'required|integer|min:1900|max:2100',
            'calendar_mode' => 'required|in:ethiopian,gregorian',
        ]);

        $year = (int) $validated['fiscal_year'];
        $companyId = creatorId();

        DB::transaction(function () use ($year, $validated, $companyId) {
            if ($validated['calendar_mode'] === 'ethiopian'
                && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)
            ) {
                $ethSvc = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
                $fyLabel = "EFY {$year}";

                // Ethiopian Fiscal Year runs Hamle 1 (Year) to Sene 30 (Year + 1)
                $cycles = [
                    [11, $year, 'Hamle (M1)'],
                    [12, $year, 'Nehase (M2)'],
                    [13, $year, 'Pagume (M2b)'],
                    [1,  $year + 1, 'Meskerem (M3)'],
                    [2,  $year + 1, 'Tikimt (M4)'],
                    [3,  $year + 1, 'Hidar (M5)'],
                    [4,  $year + 1, 'Tahsas (M6)'],
                    [5,  $year + 1, 'Tir (M7)'],
                    [6,  $year + 1, 'Yekatit (M8)'],
                    [7,  $year + 1, 'Megabit (M9)'],
                    [8,  $year + 1, 'Miyazia (M10)'],
                    [9,  $year + 1, 'Ginbot (M11)'],
                    [10, $year + 1, 'Sene (M12)'],
                ];

                foreach ($cycles as $idx => [$ethMonth, $ethYear, $label]) {
                    $startGreg = $ethSvc->toGregorian($ethYear, $ethMonth, 1);
                    $lastDay = $ethSvc->daysInMonth($ethYear, $ethMonth);
                    $endGreg = $ethSvc->toGregorian($ethYear, $ethMonth, $lastDay);

                    AccountingPeriod::firstOrCreate(
                        [
                            'created_by'    => $companyId,
                            'fiscal_year'   => $fyLabel,
                            'period_number' => $idx + 1,
                        ],
                        [
                            'name'       => "{$label} {$ethYear}",
                            'start_date' => sprintf('%04d-%02d-%02d', $startGreg['year'], $startGreg['month'], $startGreg['day']),
                            'end_date'   => sprintf('%04d-%02d-%02d', $endGreg['year'], $endGreg['month'], $endGreg['day']),
                            'status'     => 'open',
                            'creator_id' => Auth::id(),
                        ]
                    );
                }
            } else {
                $fyLabel = (string) $year;
                for ($m = 1; $m <= 12; $m++) {
                    $start = Carbon::create($year, $m, 1);
                    AccountingPeriod::firstOrCreate(
                        [
                            'created_by'    => $companyId,
                            'fiscal_year'   => $fyLabel,
                            'period_number' => $m,
                        ],
                        [
                            'name'       => $start->format('F Y'),
                            'start_date' => $start->toDateString(),
                            'end_date'   => $start->copy()->endOfMonth()->toDateString(),
                            'status'     => 'open',
                            'creator_id' => Auth::id(),
                        ]
                    );
                }
            }
        });

        return back()->with('success', __('Accounting periods generated successfully.'));
    }

    public function updateStatus(Request $request, AccountingPeriod $accountingPeriod)
    {
        if ((!Auth::user()->can('manage-accounting-periods') && !Auth::user()->can('manage-account-types'))
            || $accountingPeriod->created_by != creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'status' => 'required|in:open,soft_locked,closed',
            'notes'  => 'nullable|string|max:500',
        ]);

        if ($accountingPeriod->status === 'closed' && $validated['status'] !== 'closed') {
            return back()->with('error', __('Closed periods cannot be reopened directly to protect audit compliance.'));
        }

        $isLocking = in_array($validated['status'], ['soft_locked', 'closed'], true);
        $accountingPeriod->update([
            'status'    => $validated['status'],
            'locked_by' => $isLocking ? Auth::id() : null,
            'locked_at' => $isLocking ? now() : null,
            'notes'     => $validated['notes'] ?? $accountingPeriod->notes,
        ]);

        return back()->with('success', __('Accounting period status updated successfully.'));
    }
}
