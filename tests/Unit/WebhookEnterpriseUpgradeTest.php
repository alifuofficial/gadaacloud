<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Webhook\Models\Webhook;
use Workdo\Webhook\Traits\WebhookControllerHelpers;
use Workdo\Webhook\Traits\WebhookDateHelpers;

class WebhookEnterpriseUpgradeTest extends TestCase
{
    public function test_webhook_model_uses_tenant_scoped_trait(): void
    {
        $traits = class_uses_recursive(Webhook::class);
        $this->assertArrayHasKey(
            \App\Traits\TenantScoped::class,
            $traits,
            'Webhook must use App\\Traits\\TenantScoped trait'
        );
    }

    public function test_webhook_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use WebhookDateHelpers;
        };

        $resolved = $helper->resolveWebhookDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_webhook_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use WebhookControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = Webhook::query();
        $helper->applySafeSort($query, ['method', 'url', 'is_active'], 'injected_col; DROP TABLE webhooks;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
