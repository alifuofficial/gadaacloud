<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\EthiopianCalendar\Services\EthiopianDateService;

class EthiopianCalendarEnterpriseUpgradeTest extends TestCase
{
    public function test_ethiopian_leap_year_and_pagume_day_counts(): void
    {
        $service = app(EthiopianDateService::class);

        $this->assertTrue($service->isEthiopianLeapYear(2015));
        $this->assertFalse($service->isEthiopianLeapYear(2016));
        $this->assertFalse($service->isEthiopianLeapYear(2017));
        $this->assertTrue($service->isEthiopianLeapYear(2019));

        $this->assertEquals(6, $service->daysInMonth(2015, 13));
        $this->assertEquals(5, $service->daysInMonth(2016, 13));
        $this->assertEquals(30, $service->daysInMonth(2017, 1));
    }

    public function test_bidirectional_kenat_gregorian_round_trip_conversion(): void
    {
        $service = app(EthiopianDateService::class);

        $enkutatashGreg = $service->toGregorian(2017, 1, 1);
        $this->assertEquals('2024-09-11', $enkutatashGreg['iso']);

        $back = $service->toEthiopian($enkutatashGreg['iso']);
        $this->assertNotNull($back);
        $this->assertEquals(2017, $back['year']);
        $this->assertEquals(1, $back['month']);
        $this->assertEquals(1, $back['day']);
        $this->assertEquals('Meskerem', $back['month_name']);
    }

    public function test_fiscal_year_cycles_and_national_holidays_resolve_correctly(): void
    {
        $service = app(EthiopianDateService::class);

        $fy = $service->getFiscalYearRange(2017);
        $this->assertEquals(2017, $fy['fiscal_year']);
        $this->assertStringStartsWith('2024-', $fy['start_date']);
        $this->assertStringStartsWith('2025-', $fy['end_date']);
        $this->assertCount(4, $fy['quarters']);

        $cycles = $service->getMonthlyCyclesForYear(2017);
        $this->assertCount(12, $cycles);
        $this->assertEquals(1, $cycles[0]['month']);
        $this->assertEquals(12, $cycles[11]['month']);

        $holidays = $service->getNationalHolidaysForYear(2017);
        $holidayNames = array_column($holidays, 'name');
        $this->assertContains('Ethiopian New Year (Enkutatash)', $holidayNames);
        $this->assertContains('Finding of the True Cross (Meskel)', $holidayNames);
        $this->assertContains('Ethiopian Easter (Fasika)', $holidayNames);
    }
}
