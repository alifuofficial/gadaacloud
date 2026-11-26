<?php

namespace Workdo\GadaacloudStudio\Traits;

use Carbon\Carbon;

trait GadaacloudStudioDateHelpers
{
    /**
     * Resolve a Gregorian date range from an Ethiopian fiscal year string (e.g. "2017").
     *
     * @return array{start_date: string, end_date: string, quarters: array}
     */
    public function resolveStudioDateRange(?string $ethFiscalYear = null): array
    {
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $year = $ethFiscalYear ?? $service->getCurrentEthiopianYear();
            return $service->getFiscalYearRange((int) $year);
        }

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
    public function formatDualStudioDate($date): string
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
