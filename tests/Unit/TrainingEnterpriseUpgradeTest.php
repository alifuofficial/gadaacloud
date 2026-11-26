<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Training\Models\Trainer;
use Workdo\Training\Models\Training;
use Workdo\Training\Models\TrainingFeedback;
use Workdo\Training\Models\TrainingTask;
use Workdo\Training\Models\TrainingType;
use Workdo\Training\Traits\TrainingControllerHelpers;
use Workdo\Training\Traits\TrainingDateHelpers;

class TrainingEnterpriseUpgradeTest extends TestCase
{
    public function test_all_training_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Trainer::class,
            Training::class,
            TrainingFeedback::class,
            TrainingTask::class,
            TrainingType::class,
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

    public function test_training_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Training/src/Http/Requests/*.php'));
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

    public function test_training_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use TrainingDateHelpers;
        };

        $resolved = $helper->resolveTrainingDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_training_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use TrainingControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = Training::query();
        $helper->applySafeSort($query, ['title', 'status'], 'injected_col; DROP TABLE trainings;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
