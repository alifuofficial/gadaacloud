<?php

namespace Workdo\Account\Traits;

use Carbon\Carbon;

trait AccountDateHelpers
{
    /**
     * Resolve a Gregorian date range from an Ethiopian fiscal year string (e.g. "2017").
     * Falls back to current Ethiopian fiscal year when null.
     *
     * @return array{start_date: string, end_date: string, quarters: array}
     */
    public function resolveAccountDateRange(?string $ethFiscalYear = null): array
    {
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $year = $ethFiscalYear ?? $service->getCurrentEthiopianYear();
            return $service->getFiscalYearRange((int) $year);
        }

        // Graceful degradation — return current Gregorian year boundaries
        $year = (int) ($ethFiscalYear ?? date('Y'));
        return [
            'start_date' => "{$year}-01-01",
            'end_date'   => "{$year}-12-31",
            'quarters'   => [],
        ];
    }

    /**
     * Format a date as "Gregorian / Ethiopian Ge'ez" dual string.
     */
    public function formatDualAccountDate($date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $eth = $service->toEthiopian($carbon->year, $carbon->month, $carbon->day);
            return $carbon->format('Y-m-d') . ' / ' . implode('-', $eth);
        }

        return $carbon->format('Y-m-d');
    }
}
