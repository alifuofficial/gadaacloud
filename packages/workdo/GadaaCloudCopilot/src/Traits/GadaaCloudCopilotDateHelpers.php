<?php

namespace Workdo\GadaaCloudCopilot\Traits;

use Carbon\Carbon;

trait GadaaCloudCopilotDateHelpers
{
    public function resolveCopilotDateRange(?string $ethFiscalYear = null): array
    {
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $year = $ethFiscalYear ?? $service->getCurrentEthiopianYear();
            return $service->getFiscalYearRange((int) $year);
        }
        $year = (int) ($ethFiscalYear ?? date('Y'));
        return ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'quarters' => []];
    }

    public function formatDualCopilotDate($date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            return $carbon->format('Y-m-d') . ' / ' . implode('-', $service->toEthiopian($carbon->year, $carbon->month, $carbon->day));
        }
        return $carbon->format('Y-m-d');
    }
}
