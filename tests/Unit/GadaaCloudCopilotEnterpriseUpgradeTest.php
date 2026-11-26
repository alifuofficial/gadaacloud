<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\GadaaCloudCopilot\Models\CopilotAutomation;
use Workdo\GadaaCloudCopilot\Models\CopilotInsight;
use Workdo\GadaaCloudCopilot\Models\CopilotLearning;
use Workdo\GadaaCloudCopilot\Models\CopilotMemory;
use Workdo\GadaaCloudCopilot\Models\CopilotPrediction;
use Workdo\GadaaCloudCopilot\Models\CopilotTokenUsage;
use Workdo\GadaaCloudCopilot\Traits\GadaaCloudCopilotControllerHelpers;
use Workdo\GadaaCloudCopilot\Traits\GadaaCloudCopilotDateHelpers;

class GadaaCloudCopilotEnterpriseUpgradeTest extends TestCase
{
    public function test_all_copilot_models_use_tenant_scoped_trait(): void
    {
        $models = [
            CopilotAutomation::class,
            CopilotInsight::class,
            CopilotLearning::class,
            CopilotMemory::class,
            CopilotPrediction::class,
            CopilotTokenUsage::class,
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

    public function test_copilot_token_usage_fillable_includes_created_by(): void
    {
        $model = new CopilotTokenUsage();
        $this->assertContains('created_by', $model->getFillable());
    }

    public function test_copilot_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use GadaaCloudCopilotDateHelpers;
        };

        $resolved = $helper->resolveCopilotDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
        $this->assertStringContainsString('/', $helper->formatDualCopilotDate('2025-01-15'));
    }

    public function test_copilot_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use GadaaCloudCopilotControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = CopilotInsight::query();
        $helper->applySafeSort($query, ['title', 'status'], 'injected_col; DROP TABLE copilot_insights;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
