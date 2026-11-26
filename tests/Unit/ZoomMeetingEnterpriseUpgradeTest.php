<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\ZoomMeeting\Models\ZoomMeeting;
use Workdo\ZoomMeeting\Traits\ZoomMeetingControllerHelpers;
use Workdo\ZoomMeeting\Traits\ZoomMeetingDateHelpers;

class ZoomMeetingEnterpriseUpgradeTest extends TestCase
{
    public function test_zoom_meeting_model_uses_tenant_scoped_trait(): void
    {
        $traits = class_uses_recursive(ZoomMeeting::class);
        $this->assertArrayHasKey(
            \App\Traits\TenantScoped::class,
            $traits,
            'ZoomMeeting must use App\\Traits\\TenantScoped trait'
        );
    }

    public function test_zoom_meeting_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/ZoomMeeting/src/Http/Requests/*.php'));
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

    public function test_zoom_meeting_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use ZoomMeetingDateHelpers;
        };

        $resolved = $helper->resolveZoomMeetingDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_zoom_meeting_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use ZoomMeetingControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = ZoomMeeting::query();
        $helper->applySafeSort($query, ['title', 'start_time', 'status'], 'injected_col; DROP TABLE zoom_meetings;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
