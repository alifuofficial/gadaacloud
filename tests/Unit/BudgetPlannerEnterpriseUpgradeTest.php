<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\BudgetPlanner\Http\Requests\StoreBudgetPeriodRequest;
use Workdo\BudgetPlanner\Http\Requests\UpdateBudgetPeriodRequest;
use Workdo\BudgetPlanner\Models\Budget;
use Workdo\BudgetPlanner\Models\BudgetAllocation;
use Workdo\BudgetPlanner\Models\BudgetMonitoring;
use Workdo\BudgetPlanner\Models\BudgetPeriod;
use Workdo\BudgetPlanner\Traits\BudgetPlannerDateHelpers;

class BudgetPlannerEnterpriseUpgradeTest extends TestCase
{
    public function test_all_budget_planner_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Budget::class,
            BudgetAllocation::class,
            BudgetMonitoring::class,
            BudgetPeriod::class,
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

    public function test_budget_planner_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/BudgetPlanner/src/Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_budget_period_requests_allow_ethiopian_fiscal_year_strings(): void
    {
        $storeRules = (new StoreBudgetPeriodRequest())->rules();
        $updateRules = (new UpdateBudgetPeriodRequest())->rules();

        $this->assertStringContainsString('max:30', $storeRules['financial_year']);
        $this->assertStringContainsString('max:30', $updateRules['financial_year']);
    }

    public function test_budget_planner_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use BudgetPlannerDateHelpers;
        };

        $resolved = $helper->resolveBudgetDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }
}
