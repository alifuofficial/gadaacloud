<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Calendar\Http\Controllers\CalendarController;
use Workdo\Calendar\Http\Controllers\CalendarSettingsController;
use Workdo\Calendar\Models\GoogleCalendarSetting;
use Workdo\Calendar\Traits\CalendarControllerHelpers;
use Workdo\Calendar\Traits\CalendarDateHelpers;

class CalendarEnterpriseUpgradeTest extends TestCase
{
    public function test_google_calendar_setting_uses_tenant_scoped_trait(): void
    {
        $traits = class_uses_recursive(GoogleCalendarSetting::class);
        $this->assertArrayHasKey(
            \App\Traits\TenantScoped::class,
            $traits,
            'GoogleCalendarSetting must use App\\Traits\\TenantScoped trait'
        );
    }

    public function test_calendar_controllers_wire_enterprise_traits(): void
    {
        foreach ([CalendarController::class, CalendarSettingsController::class] as $controller) {
            $traits = class_uses_recursive($controller);
            $this->assertArrayHasKey(CalendarControllerHelpers::class, $traits);
            $this->assertArrayHasKey(CalendarDateHelpers::class, $traits);
        }
    }

    public function test_calendar_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use CalendarDateHelpers;
        };

        $resolved = $helper->resolveCalendarDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_calendar_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use CalendarControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => 0]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = GoogleCalendarSetting::query();
        $helper->applySafeSort($query, ['calendar_id', 'created_at'], 'injected_col; DROP TABLE users;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
