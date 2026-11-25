<?php

namespace Workdo\DoubleEntry\Traits;

use Carbon\Carbon;
use Workdo\EthiopianCalendar\Services\EthiopianDateService;

trait DoubleEntryDateHelpers
{
    /**
     * Resolve effective Gregorian from/to dates from Gregorian or Ethiopian fiscal parameters.
     */
    public function resolveDateRange(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $ethFiscalYear = null,
        ?string $ethQuarter = null
    ): array {
        $resolvedFrom = $fromDate;
        $resolvedTo = $toDate;
        $ethYear = null;
        $quarterKey = !empty($ethQuarter) ? strtolower(trim($ethQuarter)) : null;
        $fiscalYearLabel = null;

        if (!empty($ethFiscalYear) && class_exists(EthiopianDateService::class)) {
            try {
                $service = app(EthiopianDateService::class);
                $parsedYear = (int) preg_replace('/[^0-9]/', '', $ethFiscalYear);

                if ($parsedYear >= 1900 && $parsedYear <= 2200) {
                    $ethYear = $parsedYear;
                    $range = $service->getFiscalYearRange($parsedYear);
                    $fiscalYearLabel = $range['label'] ?? "FY {$parsedYear} E.C.";

                    if ($quarterKey && !empty($range['quarters'])) {
                        foreach ($range['quarters'] as $q) {
                            if (strtolower($q['key']) === $quarterKey) {
                                $resolvedFrom = $q['start_date'];
                                $resolvedTo = $q['end_date'];
                                $fiscalYearLabel = $q['label'] ?? "{$fiscalYearLabel} ({$quarterKey})";
                                break;
                            }
                        }
                    } else {
                        $resolvedFrom = $range['start_date'];
                        $resolvedTo = $range['end_date'];
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to standard gregorian defaults
            }
        }

        $currentYear = date('Y');
        $resolvedFrom = $resolvedFrom ?: "$currentYear-01-01";
        $resolvedTo = $resolvedTo ?: "$currentYear-12-31";

        return [
            'from_date' => $resolvedFrom,
            'to_date' => $resolvedTo,
            'ethiopian_fiscal_year' => $ethYear,
            'ethiopian_quarter' => $quarterKey,
            'fiscal_year_label' => $fiscalYearLabel,
            'dual_from' => $this->formatDualDate($resolvedFrom),
            'dual_to' => $this->formatDualDate($resolvedTo),
        ];
    }

    /**
     * Format a date into dual Ethiopian & Gregorian representation.
     */
    public function formatDualDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        if (class_exists(EthiopianDateService::class)) {
            try {
                return app(EthiopianDateService::class)->formatDual($date);
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Get the current Ethiopian Fiscal Year number.
     */
    public function currentEthiopianFiscalYear(): ?int
    {
        if (class_exists(EthiopianDateService::class)) {
            try {
                return app(EthiopianDateService::class)->getCurrentFiscalYear();
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
