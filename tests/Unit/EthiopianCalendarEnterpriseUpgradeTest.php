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

    public function test_all_module_date_helpers_resolve_null_fiscal_year_and_dual_dates(): void
    {
        $traitsAndMethods = [
            [\Workdo\Account\Traits\AccountDateHelpers::class, 'resolveAccountDateRange', 'formatDualAccountDate'],
            [\Workdo\AIAssistant\Traits\AIAssistantDateHelpers::class, 'resolveAIAssistantDateRange', 'formatDualAIAssistantDate'],
            [\Workdo\Calendar\Traits\CalendarDateHelpers::class, 'resolveCalendarDateRange', 'formatDualCalendarDate'],
            [\Workdo\Chatter\Traits\ChatterDateHelpers::class, 'resolveChatterDateRange', 'formatDualChatterDate'],
            [\Workdo\EIMS\Traits\EimsDateHelpers::class, 'resolveEimsDateRange', 'formatDualEimsDate'],
            [\Workdo\ERPMigrate\Traits\ERPMigrateDateHelpers::class, 'resolveERPMigrateDateRange', 'formatDualERPMigrateDate'],
            [\Workdo\FormBuilder\Traits\FormBuilderDateHelpers::class, 'resolveFormBuilderDateRange', 'formatDualFormBuilderDate'],
            [\Workdo\GadaaCloudCopilot\Traits\GadaaCloudCopilotDateHelpers::class, 'resolveCopilotDateRange', 'formatDualCopilotDate'],
            [\Workdo\GadaacloudStudio\Traits\GadaacloudStudioDateHelpers::class, 'resolveStudioDateRange', 'formatDualStudioDate'],
            [\Workdo\Goal\Traits\GoalDateHelpers::class, 'resolveGoalDateRange', 'formatDualGoalDate'],
            [\Workdo\LandingPage\Traits\LandingPageDateHelpers::class, 'resolveLandingPageDateRange', 'formatDualLandingPageDate'],
            [\Workdo\ProductService\Traits\ProductServiceDateHelpers::class, 'resolveProductServiceDateRange', 'formatDualProductServiceDate'],
            [\Workdo\SupportTicket\Traits\SupportTicketDateHelpers::class, 'resolveSupportTicketDateRange', 'formatDualSupportTicketDate'],
            [\Workdo\Timesheet\Traits\TimesheetDateHelpers::class, 'resolveTimesheetDateRange', 'formatDualTimesheetDate'],
            [\Workdo\Training\Traits\TrainingDateHelpers::class, 'resolveTrainingDateRange', 'formatDualTrainingDate'],
            [\Workdo\Webhook\Traits\WebhookDateHelpers::class, 'resolveWebhookDateRange', 'formatDualWebhookDate'],
            [\Workdo\ZoomMeeting\Traits\ZoomMeetingDateHelpers::class, 'resolveZoomMeetingDateRange', 'formatDualZoomMeetingDate'],
        ];

        $map = [
            0 => new class { use \Workdo\Account\Traits\AccountDateHelpers; },
            1 => new class { use \Workdo\AIAssistant\Traits\AIAssistantDateHelpers; },
            2 => new class { use \Workdo\Calendar\Traits\CalendarDateHelpers; },
            3 => new class { use \Workdo\Chatter\Traits\ChatterDateHelpers; },
            4 => new class { use \Workdo\EIMS\Traits\EimsDateHelpers; },
            5 => new class { use \Workdo\ERPMigrate\Traits\ERPMigrateDateHelpers; },
            6 => new class { use \Workdo\FormBuilder\Traits\FormBuilderDateHelpers; },
            7 => new class { use \Workdo\GadaaCloudCopilot\Traits\GadaaCloudCopilotDateHelpers; },
            8 => new class { use \Workdo\GadaacloudStudio\Traits\GadaacloudStudioDateHelpers; },
            9 => new class { use \Workdo\Goal\Traits\GoalDateHelpers; },
            10 => new class { use \Workdo\LandingPage\Traits\LandingPageDateHelpers; },
            11 => new class { use \Workdo\ProductService\Traits\ProductServiceDateHelpers; },
            12 => new class { use \Workdo\SupportTicket\Traits\SupportTicketDateHelpers; },
            13 => new class { use \Workdo\Timesheet\Traits\TimesheetDateHelpers; },
            14 => new class { use \Workdo\Training\Traits\TrainingDateHelpers; },
            15 => new class { use \Workdo\Webhook\Traits\WebhookDateHelpers; },
            16 => new class { use \Workdo\ZoomMeeting\Traits\ZoomMeetingDateHelpers; },
        ];

        foreach ($traitsAndMethods as $idx => [$traitClass, $resolveMethod, $dualMethod]) {
            $helper = $map[$idx];
            $rangeNull = $helper->{$resolveMethod}(null);
            $this->assertArrayHasKey('start_date', $rangeNull, $traitClass);
            $this->assertArrayHasKey('end_date', $rangeNull, $traitClass);

            $dual = $helper->{$dualMethod}('2025-01-07');
            $this->assertStringStartsWith('2025-01-07 / ', $dual, $traitClass);
        }

        $ethController = file_get_contents(base_path('packages/workdo/EthiopianCalendar/src/Http/Controllers/EthiopianCalendarController.php'));
        $this->assertStringContainsString('DB::transaction', $ethController);
    }
}
