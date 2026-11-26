<?php

namespace Workdo\LandingPage\Traits;

use Carbon\Carbon;

trait LandingPageDateHelpers
{
    public function resolveLandingPageDateRange(?string $ethFiscalYear = null): array
    {
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $year = $ethFiscalYear ?? $service->getCurrentFiscalYear();
            return $service->getFiscalYearRange((int) $year);
        }
        $year = (int) ($ethFiscalYear ?? date('Y'));
        return ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'quarters' => []];
    }

    public function formatDualLandingPageDate($date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $eth = $service->toEthiopian($carbon);
            if ($eth) {
                return sprintf('%s / %04d-%02d-%02d', $carbon->format('Y-m-d'), $eth['year'], $eth['month'], $eth['day']);
            }
        }
        return $carbon->format('Y-m-d');
    }
}
