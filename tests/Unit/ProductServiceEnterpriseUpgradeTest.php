<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;
use Workdo\ProductService\Traits\ProductServiceControllerHelpers;
use Workdo\ProductService\Traits\ProductServiceDateHelpers;

class ProductServiceEnterpriseUpgradeTest extends TestCase
{
    public function test_all_product_service_models_use_tenant_scoped_trait(): void
    {
        $models = [
            ProductServiceCategory::class,
            ProductServiceItem::class,
            ProductServiceTax::class,
            ProductServiceUnit::class,
            WarehouseStock::class,
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

    public function test_product_service_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/ProductService/src/Http/Requests/*.php'));
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

    public function test_product_service_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use ProductServiceDateHelpers;
        };

        $resolved = $helper->resolveProductServiceDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_product_service_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use ProductServiceControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = ProductServiceItem::query();
        $helper->applySafeSort($query, ['name', 'sku', 'sale_price'], '1; DROP TABLE product_service_items;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
