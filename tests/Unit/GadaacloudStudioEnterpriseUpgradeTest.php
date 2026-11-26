<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\GadaacloudStudio\Models\Workflow;
use Workdo\GadaacloudStudio\Models\WorkflowDelegation;
use Workdo\GadaacloudStudio\Models\WorkflowLog;
use Workdo\GadaacloudStudio\Models\WorkflowRequest;
use Workdo\GadaacloudStudio\Models\WorkflowStep;
use Workdo\GadaacloudStudio\Traits\GadaacloudStudioControllerHelpers;
use Workdo\GadaacloudStudio\Traits\GadaacloudStudioDateHelpers;

class GadaacloudStudioEnterpriseUpgradeTest extends TestCase
{
    public function test_all_studio_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Workflow::class,
            WorkflowStep::class,
            WorkflowRequest::class,
            WorkflowLog::class,
            WorkflowDelegation::class,
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

    public function test_studio_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use GadaacloudStudioDateHelpers;
        };

        $resolved = $helper->resolveStudioDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_studio_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use GadaacloudStudioControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = Workflow::query();
        $helper->applySafeSort($query, ['name', 'target_model', 'is_active'], 'bad; --', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
