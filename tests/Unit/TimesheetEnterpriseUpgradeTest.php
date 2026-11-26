<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Timesheet\Models\Timesheet;
use Workdo\Timesheet\Traits\TimesheetControllerHelpers;
use Workdo\Timesheet\Traits\TimesheetDateHelpers;

class TimesheetEnterpriseUpgradeTest extends TestCase
{
    public function test_all_timesheet_models_use_tenant_scoped_trait(): void
    {
        $traits = class_uses_recursive(Timesheet::class);
        $this->assertArrayHasKey(
            \App\Traits\TenantScoped::class,
            $traits,
            Timesheet::class . ' must use App\\Traits\\TenantScoped trait'
        );
    }

    public function test_timesheet_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Timesheet/src/Http/Requests/*.php'));
        $this->assertNotEmpty($requestFiles);

        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_timesheet_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use TimesheetDateHelpers;
        };

        $resolved = $helper->resolveTimesheetDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_timesheet_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use TimesheetControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = Timesheet::query();
        $helper->applySafeSort($query, ['date', 'hours', 'minutes'], 'invalid_col; --', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
