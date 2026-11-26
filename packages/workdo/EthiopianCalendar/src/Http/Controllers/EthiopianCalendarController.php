<?php

namespace Workdo\EthiopianCalendar\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\EthiopianCalendar\Services\EthiopianDateService;

class EthiopianCalendarController extends Controller
{
    public function __construct(private EthiopianDateService $ethService)
    {
    }

    private function canManageCalendar(): bool
    {
        $user = Auth::user();
        return $user && ($user->can('manage-ethiopian-calendar') || $user->can('manage-settings'));
    }

    public function index(Request $request)
    {
        if (!$this->canManageCalendar()) {
            return back()->with('error', __('Permission denied'));
        }

        $todayEth = $this->ethService->toEthiopian(Carbon::now()) ?? ['year' => 2019, 'month' => 1, 'day' => 1];
        $selectedYear = (int) $request->input('year', $todayEth['year']);
        if ($selectedYear < 1900 || $selectedYear > 2200) {
            $selectedYear = $todayEth['year'];
        }

        $fiscalYearNum = $this->ethService->getCurrentFiscalYear(Carbon::now());
        $fiscalYear = $this->ethService->getFiscalYearRange($fiscalYearNum);
        $monthlyCycles = $this->ethService->getMonthlyCyclesForYear($selectedYear);
        $serverHolidays = $this->ethService->getNationalHolidaysForYear($selectedYear);

        $hasHrm = module_is_active('Hrm')
            && class_exists(\Workdo\Hrm\Models\Holiday::class)
            && class_exists(\Workdo\Hrm\Models\HolidayType::class);

        $existingHolidays = [];
        if ($hasHrm) {
            $existingHolidays = \Workdo\Hrm\Models\Holiday::where('created_by', creatorId())
                ->select('id', 'name', 'start_date', 'end_date', 'is_paid')
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'start_date' => $h->start_date ? $h->start_date->format('Y-m-d') : null,
                ])
                ->toArray();
        }

        $settings = getCompanyAllSetting();

        return Inertia::render('EthiopianCalendar/Index', [
            'todayEth' => $todayEth,
            'selectedYear' => $selectedYear,
            'fiscalYear' => $fiscalYear,
            'monthlyCycles' => $monthlyCycles,
            'serverHolidays' => $serverHolidays,
            'hasHrm' => $hasHrm,
            'existingHolidays' => $existingHolidays,
            'calendarSettings' => [
                'useEthiopianCalendar' => $settings['useEthiopianCalendar'] ?? 'off',
                'ethiopianDateStyle' => $settings['ethiopianDateStyle'] ?? 'numeric',
                'showEthiopianEra' => $settings['showEthiopianEra'] ?? 'off',
                'useEthiopianTime' => $settings['useEthiopianTime'] ?? 'off',
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        if (!$this->canManageCalendar()) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'useEthiopianCalendar' => 'required|string|in:on,off',
            'ethiopianDateStyle' => 'required|string|in:numeric,month_name,amharic',
            'showEthiopianEra' => 'required|string|in:on,off',
            'useEthiopianTime' => 'required|string|in:on,off',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated as $key => $value) {
                setSetting($key, $value);
            }
        });

        return redirect()->back()->with('success', __('Ethiopian Calendar preferences updated successfully.'));
    }

    public function syncHrmHolidays(Request $request)
    {
        if (!$this->canManageCalendar()) {
            return back()->with('error', __('Permission denied'));
        }

        if (!module_is_active('Hrm') || !class_exists(\Workdo\Hrm\Models\Holiday::class) || !class_exists(\Workdo\Hrm\Models\HolidayType::class)) {
            return redirect()->back()->with('error', __('HRM module is not active for this workspace.'));
        }

        $validated = $request->validate([
            'ethiopian_year' => 'required|integer|min:1900|max:2200',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.start_date' => 'required|date',
            'holidays.*.description' => 'nullable|string|max:500',
        ]);

        $ethYear = (int) $validated['ethiopian_year'];
        $holidaysToSync = !empty($validated['holidays'])
            ? $validated['holidays']
            : $this->ethService->getNationalHolidaysForYear($ethYear);

        $tenantId = creatorId();
        $userId = Auth::id();

        $createdCount = DB::transaction(function () use ($tenantId, $userId, $holidaysToSync, $ethYear) {
            $holidayType = \Workdo\Hrm\Models\HolidayType::firstOrCreate(
                [
                    'created_by' => $tenantId,
                    'holiday_type' => 'Ethiopian Public Holiday',
                ],
                [
                    'creator_id' => $userId,
                ]
            );

            $createdCount = 0;
            foreach ($holidaysToSync as $item) {
                $startDate = Carbon::parse($item['start_date'])->format('Y-m-d');
                $existing = \Workdo\Hrm\Models\Holiday::where('created_by', $tenantId)
                    ->whereDate('start_date', $startDate)
                    ->where('name', $item['name'])
                    ->first();

                if (!$existing) {
                    \Workdo\Hrm\Models\Holiday::create([
                        'name' => $item['name'],
                        'start_date' => $startDate,
                        'end_date' => $startDate,
                        'holiday_type_id' => $holidayType->id,
                        'description' => $item['description'] ?? "Ethiopian Public Holiday ({$ethYear} E.C.)",
                        'is_paid' => true,
                        'is_sync_google_calendar' => false,
                        'is_sync_outlook_calendar' => false,
                        'creator_id' => $userId,
                        'created_by' => $tenantId,
                    ]);
                    $createdCount++;
                }
            }

            return $createdCount;
        });

        $msg = $createdCount > 0
            ? __(':count Ethiopian public holidays synced to HRM.', ['count' => $createdCount])
            : __('All selected Ethiopian public holidays for :year E.C. already exist in HRM.', ['year' => $ethYear]);

        return redirect()->back()->with('success', $msg);
    }
}
