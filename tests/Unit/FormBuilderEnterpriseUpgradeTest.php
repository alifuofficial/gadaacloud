<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\FormBuilder\Models\Form;
use Workdo\FormBuilder\Models\FormConversion;
use Workdo\FormBuilder\Models\FormField;
use Workdo\FormBuilder\Models\FormResponse;
use Workdo\FormBuilder\Traits\FormBuilderControllerHelpers;
use Workdo\FormBuilder\Traits\FormBuilderDateHelpers;

class FormBuilderEnterpriseUpgradeTest extends TestCase
{
    public function test_all_form_builder_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Form::class,
            FormConversion::class,
            FormField::class,
            FormResponse::class,
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

    public function test_form_builder_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/FormBuilder/src/Http/Requests/*.php'));
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

    public function test_form_builder_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use FormBuilderDateHelpers;
        };

        $resolved = $helper->resolveFormBuilderDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_form_builder_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use FormBuilderControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = Form::query();
        $helper->applySafeSort($query, ['name', 'code', 'is_active'], 'injected_col; DROP TABLE forms;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
