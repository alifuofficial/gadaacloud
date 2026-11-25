<?php

use Workdo\EthiopianCalendar\Services\EthiopianDateService;

if (!function_exists('ethiopian_calendar_active')) {
    function ethiopian_calendar_active($userId = null): bool
    {
        if (!function_exists('module_is_active') || !module_is_active('EthiopianCalendar', $userId)) {
            return false;
        }

        $setting = function_exists('company_setting')
            ? company_setting('useEthiopianCalendar', $userId)
            : null;

        return $setting === 'on';
    }
}

if (!function_exists('to_ethiopian_date')) {
    function to_ethiopian_date($date): ?array
    {
        return app(EthiopianDateService::class)->toEthiopian($date);
    }
}

if (!function_exists('format_ethiopian_date')) {
    function format_ethiopian_date($date, ?string $format = null, ?string $style = null, ?bool $includeEra = null): string
    {
        $service = app(EthiopianDateService::class);
        $resolvedFormat = $format ?? (function_exists('company_setting') ? (company_setting('dateFormat') ?: 'Y-m-d') : 'Y-m-d');
        $resolvedStyle = $style ?? (function_exists('company_setting') ? (company_setting('ethiopianDateStyle') ?: 'numeric') : 'numeric');
        $resolvedEra = $includeEra ?? (function_exists('company_setting') && company_setting('showEthiopianEra') === 'on');

        return $service->format($date, $resolvedFormat, $resolvedStyle, $resolvedEra);
    }
}
