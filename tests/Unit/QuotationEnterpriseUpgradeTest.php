<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;
use Workdo\Quotation\Models\SalesQuotationItemTax;
use Workdo\Quotation\Traits\QuotationControllerHelpers;
use Workdo\Quotation\Traits\QuotationDateHelpers;

class QuotationEnterpriseUpgradeTest extends TestCase
{
    public function test_all_quotation_models_use_tenant_scoped_trait(): void
    {
        $models = [
            SalesQuotation::class,
            SalesQuotationItem::class,
            SalesQuotationItemTax::class,
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

    public function test_quotation_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Quotation/src/Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_quotation_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use QuotationDateHelpers;
        };

        $resolved = $helper->resolveQuotationDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }

    public function test_quotation_controller_helpers_bounds_per_page(): void
    {
        $helper = new class {
            use QuotationControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));
    }
}
