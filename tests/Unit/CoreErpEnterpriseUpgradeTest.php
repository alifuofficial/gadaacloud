<?php

namespace Tests\Unit;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceItemTax;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnItemTax;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceItemTax;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesInvoiceReturnItem;
use App\Models\SalesInvoiceReturnItemTax;
use App\Models\SalesProposal;
use App\Models\SalesProposalItem;
use App\Models\SalesProposalItemTax;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Traits\ControllerHelpers;
use Tests\TestCase;

class CoreErpEnterpriseUpgradeTest extends TestCase
{
    public function test_core_business_models_use_tenant_scoped_trait(): void
    {
        $models = [
            SalesInvoice::class,
            SalesInvoiceItem::class,
            SalesInvoiceItemTax::class,
            PurchaseInvoice::class,
            PurchaseInvoiceItem::class,
            PurchaseInvoiceItemTax::class,
            SalesProposal::class,
            SalesProposalItem::class,
            SalesProposalItemTax::class,
            SalesInvoiceReturn::class,
            SalesInvoiceReturnItem::class,
            SalesInvoiceReturnItemTax::class,
            PurchaseReturn::class,
            PurchaseReturnItem::class,
            PurchaseReturnItemTax::class,
            Transfer::class,
            Warehouse::class,
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

    public function test_core_erp_tenant_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $files = glob(app_path('Http/Requests/*.php'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_core_controller_helpers_bounds_per_page_and_safe_sort(): void
    {
        $helper = new class {
            use ControllerHelpers;
        };

        request()->merge(['per_page' => 5000]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => 0]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = SalesInvoice::query();
        $helper->applySafeSort($query, ['invoice_number', 'invoice_date', 'total_amount'], 'malicious; --', 'BAD_DIR');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
