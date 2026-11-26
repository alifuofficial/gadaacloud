<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Chatter\Models\ChatterActivity;
use Workdo\Chatter\Models\ChatterAttachment;
use Workdo\Chatter\Models\ChatterFollower;
use Workdo\Chatter\Models\ChatterMessage;
use Workdo\Chatter\Traits\ChatterControllerHelpers;
use Workdo\Chatter\Traits\ChatterDateHelpers;

class ChatterEnterpriseUpgradeTest extends TestCase
{
    public function test_all_chatter_models_use_tenant_scoped_trait(): void
    {
        $models = [
            ChatterActivity::class,
            ChatterAttachment::class,
            ChatterFollower::class,
            ChatterMessage::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                \App\Traits\TenantScoped::class,
                $traits,
                "{$modelClass} must use App\\Traits\\TenantScoped trait"
            );
        }
    }

    public function test_chatter_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use ChatterDateHelpers;
        };

        $resolved = $helper->resolveChatterDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_chatter_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use ChatterControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = ChatterMessage::query();
        $helper->applySafeSort($query, ['type', 'created_at'], 'injected_col; DROP TABLE chatter_messages;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
