<?php

namespace Workdo\EthiopianCalendar\Services;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Pure-PHP Ethiopic (Ge'ez) <-> Gregorian conversion, fiscal calendar, and statutory period service.
 *
 * Uses exact integer Julian Day Number (JDN) arithmetic matching Reingold & Dershowitz / ICU / Kenat.
 */
class EthiopianDateService
{
    public const ETHIOPIC_EPOCH = 1723856;

    public const MONTHS_EN = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yekatit',
        7 => 'Megabit',
        8 => 'Miazia',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagume',
    ];

    public const MONTHS_AM = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ኅዳር',
        4 => 'ታኅሣሥ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜን',
    ];

    public const MONTHS_SHORT = [
        1 => 'Mes',
        2 => 'Tik',
        3 => 'Hid',
        4 => 'Tah',
        5 => 'Tir',
        6 => 'Yek',
        7 => 'Meg',
        8 => 'Mia',
        9 => 'Gin',
        10 => 'Sen',
        11 => 'Ham',
        12 => 'Neh',
        13 => 'Pag',
    ];

    /**
     * Determine whether a given Ethiopian year is a leap year (Pagume has 6 days).
     */
    public function isEthiopianLeapYear(int $ethYear): bool
    {
        return ($ethYear % 4) === 3 || ($ethYear % 4) === -1;
    }

    /**
     * Get the number of days in an Ethiopian month (1-13).
     */
    public function daysInMonth(int $ethYear, int $ethMonth): int
    {
        if ($ethMonth < 1 || $ethMonth > 13) {
            throw new \InvalidArgumentException("Invalid Ethiopian month: {$ethMonth}");
        }

        if ($ethMonth === 13) {
            return $this->isEthiopianLeapYear($ethYear) ? 6 : 5;
        }

        return 30;
    }

    /**
     * Convert a Gregorian calendar date to Julian Day Number.
     */
    public function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $yy = $year + 4800 - $a;
        $mm = $month + 12 * $a - 3;

        return $day
            + intdiv(153 * $mm + 2, 5)
            + 365 * $yy
            + intdiv($yy, 4)
            - intdiv($yy, 100)
            + intdiv($yy, 400)
            - 32045;
    }

    /**
     * Convert Julian Day Number to Gregorian [year, month, day].
     */
    public function jdnToGregorian(int $jdn): array
    {
        $l = $jdn + 68569;
        $n = intdiv(4 * $l, 146097);
        $l = $l - intdiv(146097 * $n + 3, 4);
        $i = intdiv(4000 * ($l + 1), 1461001);
        $l = $l - intdiv(1461 * $i, 4) + 31;
        $j = intdiv(80 * $l, 2447);
        $day = $l - intdiv(2447 * $j, 80);
        $l = intdiv($j, 11);
        $month = $j + 2 - 12 * $l;
        $year = 100 * ($n - 49) + $i + $l;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'iso' => sprintf('%04d-%02d-%02d', $year, $month, $day),
        ];
    }

    /**
     * Convert Julian Day Number to Ethiopian [year, month, day].
     */
    public function jdnToEthiopian(int $jdn): array
    {
        $r = ($jdn - self::ETHIOPIC_EPOCH) % 1461;
        if ($r < 0) {
            $r += 1461;
        }
        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $year = 4 * intdiv($jdn - self::ETHIOPIC_EPOCH, 1461)
            + intdiv($r, 365)
            - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'month_name' => self::MONTHS_EN[$month] ?? '',
            'month_name_am' => self::MONTHS_AM[$month] ?? '',
            'month_short' => self::MONTHS_SHORT[$month] ?? '',
        ];
    }

    /**
     * Convert Ethiopian calendar date to Julian Day Number.
     */
    public function ethiopianToJdn(int $ethYear, int $ethMonth, int $ethDay): int
    {
        $maxDay = $this->daysInMonth($ethYear, $ethMonth);
        if ($ethDay < 1 || $ethDay > $maxDay) {
            throw new \InvalidArgumentException("Invalid Ethiopian date: {$ethYear}-{$ethMonth}-{$ethDay}");
        }

        return (self::ETHIOPIC_EPOCH + 365)
            + 365 * ($ethYear - 1)
            + intdiv($ethYear, 4)
            + 30 * $ethMonth
            + $ethDay
            - 31;
    }

    /**
     * Convert a Gregorian DateTime/string into Ethiopian date components.
     */
    public function toEthiopian($date): ?array
    {
        $carbon = $this->parseDate($date);
        if (!$carbon) {
            return null;
        }

        $jdn = $this->gregorianToJdn((int) $carbon->year, (int) $carbon->month, (int) $carbon->day);

        return $this->jdnToEthiopian($jdn);
    }

    /**
     * Convert an Ethiopian date (Y, M, D) into Gregorian date components and ISO string.
     */
    public function toGregorian(int $ethYear, int $ethMonth, int $ethDay): array
    {
        $jdn = $this->ethiopianToJdn($ethYear, $ethMonth, $ethDay);

        return $this->jdnToGregorian($jdn);
    }

    /**
     * Format a date into Ethiopian Calendar representation.
     *
     * @param mixed $date DateTimeInterface, date string, or null
     * @param string $format Tenant dateFormat ('Y-m-d', 'd/m/Y', etc.)
     * @param string $style 'numeric' | 'month_name' | 'amharic'
     * @param bool $includeEra Append 'E.C.' / 'ዓ.ም.'
     */
    public function format($date, string $format = 'Y-m-d', string $style = 'numeric', bool $includeEra = false): string
    {
        $eth = $this->toEthiopian($date);
        if (!$eth) {
            return '';
        }

        if ($style === 'amharic') {
            $base = sprintf('%s %d, %d', $eth['month_name_am'], $eth['day'], $eth['year']);
            return $includeEra ? "{$base} ዓ.ም." : $base;
        }

        if ($style === 'month_name') {
            $base = sprintf('%s %d, %d', $eth['month_name'], $eth['day'], $eth['year']);
            return $includeEra ? "{$base} E.C." : $base;
        }

        $y = (string) $eth['year'];
        $m = str_pad((string) $eth['month'], 2, '0', STR_PAD_LEFT);
        $d = str_pad((string) $eth['day'], 2, '0', STR_PAD_LEFT);

        $base = str_replace(['Y', 'm', 'd'], [$y, $m, $d], $format ?: 'Y-m-d');

        return $includeEra ? "{$base} E.C." : $base;
    }

    /**
     * Format Ethiopian 12-hour local time (where 06:00 Gregorian = 12:00 Day Ethiopian).
     */
    public function formatEthiopianTime($date): string
    {
        $carbon = $this->parseDate($date);
        if (!$carbon) {
            return '';
        }

        $gHour = (int) $carbon->hour;
        $minute = str_pad((string) $carbon->minute, 2, '0', STR_PAD_LEFT);
        $shifted = ($gHour + 18) % 24;
        $ethHour = $shifted % 12;
        if ($ethHour === 0) {
            $ethHour = 12;
        }

        if ($gHour >= 6 && $gHour < 18) {
            $period = $gHour < 12 ? 'ጠዋት / Morning' : 'ከሰዓት / Afternoon';
        } else {
            $period = ($gHour >= 18) ? 'ምሽት / Evening' : 'ሌሊት / Night';
        }

        return sprintf('%d:%s (%s)', $ethHour, $minute, $period);
    }

    /**
     * Format date with both Ethiopian and Gregorian values for official receipts & waybills.
     */
    public function formatDual($date, string $format = 'd/m/Y'): string
    {
        $carbon = $this->parseDate($date);
        if (!$carbon) {
            return '';
        }

        $eth = $this->format($carbon, $format, 'month_name', true);
        $greg = $carbon->format($format);

        return "{$eth} ({$greg})";
    }

    /**
     * Compute Ethiopian Fiscal Year bounds (Hamle 1 to Sene 30).
     *
     * By Ethiopian MoF/MoR convention, Fiscal Year `FY N E.C.` begins on Hamle 1 of Ethiopian Year `N - 1`
     * (around July 8) and ends on Sene 30 of Ethiopian Year `N` (around July 7).
     */
    public function getFiscalYearRange(int $ethFiscalYear): array
    {
        $startGreg = $this->toGregorian($ethFiscalYear - 1, 11, 1); // Hamle 1
        $endGreg = $this->toGregorian($ethFiscalYear, 10, 30);      // Sene 30

        return [
            'fiscal_year' => $ethFiscalYear,
            'label' => "FY {$ethFiscalYear} E.C. (Hamle 1, " . ($ethFiscalYear - 1) . " - Sene 30, {$ethFiscalYear})",
            'start_date' => $startGreg['iso'],
            'end_date' => $endGreg['iso'],
            'quarters' => [
                [
                    'key' => 'q1',
                    'label' => "Q1 {$ethFiscalYear} E.C. (Hamle - Meskerem)",
                    'start_date' => $startGreg['iso'],
                    'end_date' => $this->toGregorian($ethFiscalYear, 1, 30)['iso'],
                ],
                [
                    'key' => 'q2',
                    'label' => "Q2 {$ethFiscalYear} E.C. (Tikimt - Tahsas)",
                    'start_date' => $this->toGregorian($ethFiscalYear, 2, 1)['iso'],
                    'end_date' => $this->toGregorian($ethFiscalYear, 4, 30)['iso'],
                ],
                [
                    'key' => 'q3',
                    'label' => "Q3 {$ethFiscalYear} E.C. (Tir - Megabit)",
                    'start_date' => $this->toGregorian($ethFiscalYear, 5, 1)['iso'],
                    'end_date' => $this->toGregorian($ethFiscalYear, 7, 30)['iso'],
                ],
                [
                    'key' => 'q4',
                    'label' => "Q4 {$ethFiscalYear} E.C. (Miazia - Sene)",
                    'start_date' => $this->toGregorian($ethFiscalYear, 8, 1)['iso'],
                    'end_date' => $endGreg['iso'],
                ],
            ],
        ];
    }

    /**
     * Get the current Ethiopian Fiscal Year number based on today's date.
     */
    public function getCurrentFiscalYear($date = null): int
    {
        $eth = $this->toEthiopian($date ?? Carbon::now());
        if (!$eth) {
            return 2019;
        }

        // Months 11 (Hamle), 12 (Nehase), 13 (Pagume) open the upcoming Ethiopian budget year (ethYear + 1).
        return $eth['month'] >= 11 ? ($eth['year'] + 1) : $eth['year'];
    }

    /**
     * Generate Ethiopian monthly statutory / payroll period ranges for a given Ethiopian calendar year.
     * Note: Nehase (Month 12) normally spans through Pagume (Month 13) for unified 12-cycle payroll,
     * while Pagume is also included separately for audit clarity.
     */
    public function getMonthlyCyclesForYear(int $ethYear): array
    {
        $cycles = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = $this->toGregorian($ethYear, $month, 1);
            if ($month === 12) {
                $pagumeDays = $this->daysInMonth($ethYear, 13);
                $end = $this->toGregorian($ethYear, 13, $pagumeDays);
                $label = self::MONTHS_EN[$month] . ' & Pagume ' . $ethYear . ' E.C.';
            } else {
                $end = $this->toGregorian($ethYear, $month, 30);
                $label = self::MONTHS_EN[$month] . ' ' . $ethYear . ' E.C.';
            }

            $cycles[] = [
                'month' => $month,
                'year' => $ethYear,
                'name' => self::MONTHS_EN[$month],
                'name_am' => self::MONTHS_AM[$month],
                'label' => $label,
                'start_date' => $start['iso'],
                'end_date' => $end['iso'],
            ];
        }

        return $cycles;
    }

    /**
     * Compute Fixed + Bahire Hasab Ethiopian Orthodox movable public holidays for a given Ethiopian year.
     */
    public function getNationalHolidaysForYear(int $ethYear): array
    {
        $fixed = [
            ['name' => 'Ethiopian New Year (Enkutatash)', 'month' => 1, 'day' => 1, 'desc' => 'Meskerem 1 E.C. - New Year Day'],
            ['name' => 'Finding of the True Cross (Meskel)', 'month' => 1, 'day' => 17, 'desc' => 'Meskerem 17 E.C. - Meskel'],
            ['name' => "Nations, Nationalities & Peoples' Day", 'month' => 3, 'day' => 29, 'desc' => 'Hidar 29 E.C. - Federal Constitutional Day'],
            ['name' => 'Ethiopian Christmas (Genna)', 'month' => 4, 'day' => 29, 'desc' => 'Tahsas 29 E.C. - Ethiopian Christmas'],
            ['name' => 'Ethiopian Epiphany (Timkat)', 'month' => 5, 'day' => 11, 'desc' => 'Tir 11 E.C. - Ethiopian Epiphany'],
            ['name' => 'Victory of Adwa', 'month' => 6, 'day' => 23, 'desc' => 'Yekatit 23 E.C. - Battle of Adwa Victory Day'],
            ['name' => 'International Labour Day', 'month' => 8, 'day' => 23, 'desc' => 'Miazia 23 E.C. - May 1 International Labour Day'],
            ['name' => "Patriots' Victory Day", 'month' => 8, 'day' => 27, 'desc' => 'Miazia 27 E.C. - Ethiopian Patriots Day'],
            ['name' => 'Derg Downfall Day (Ginbot 20)', 'month' => 9, 'day' => 20, 'desc' => 'Ginbot 20 E.C. - National Day'],
        ];

        $holidays = [];
        foreach ($fixed as $item) {
            $greg = $this->toGregorian($ethYear, $item['month'], $item['day']);
            $holidays[] = [
                'name' => $item['name'],
                'start_date' => $greg['iso'],
                'end_date' => $greg['iso'],
                'eth_date' => sprintf('%s %d, %d E.C.', self::MONTHS_EN[$item['month']], $item['day'], $ethYear),
                'description' => $item['desc'],
                'is_paid' => true,
            ];
        }

        // Movable Christian holidays computed via Julian/Orthodox Paschal Computus
        $gregYear = $ethYear + 8;
        $a = $gregYear % 4;
        $b = $gregYear % 7;
        $c = $gregYear % 19;
        $d = (19 * $c + 15) % 30;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;

        // Julian Easter -> Gregorian offset (+13 days for 1900-2099)
        $julianJdn = $day + intdiv(153 * ($month + 12 * intdiv(14 - $month, 12) - 3) + 2, 5)
            + 365 * ($gregYear + 4800 - intdiv(14 - $month, 12))
            + intdiv($gregYear + 4800 - intdiv(14 - $month, 12), 4)
            - 32083;
        $easterGreg = $this->jdnToGregorian($julianJdn);
        $easterEth = $this->jdnToEthiopian($julianJdn);
        $goodFridayGreg = $this->jdnToGregorian($julianJdn - 2);
        $goodFridayEth = $this->jdnToEthiopian($julianJdn - 2);

        $holidays[] = [
            'name' => 'Ethiopian Good Friday (Siklet)',
            'start_date' => $goodFridayGreg['iso'],
            'end_date' => $goodFridayGreg['iso'],
            'eth_date' => sprintf('%s %d, %d E.C.', $goodFridayEth['month_name'], $goodFridayEth['day'], $goodFridayEth['year']),
            'description' => 'Bahire Hasab Movable Public Holiday - Siklet',
            'is_paid' => true,
        ];

        $holidays[] = [
            'name' => 'Ethiopian Easter (Fasika)',
            'start_date' => $easterGreg['iso'],
            'end_date' => $easterGreg['iso'],
            'eth_date' => sprintf('%s %d, %d E.C.', $easterEth['month_name'], $easterEth['day'], $easterEth['year']),
            'description' => 'Bahire Hasab Movable Public Holiday - Tinsae / Fasika',
            'is_paid' => true,
        ];

        usort($holidays, fn ($x, $y) => strcmp($x['start_date'], $y['start_date']));

        return $holidays;
    }

    private function parseDate($date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        try {
            if ($date instanceof DateTimeInterface) {
                return Carbon::instance($date);
            }

            return Carbon::parse((string) $date);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
