<?php

namespace Tests\Unit;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesProposal;
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
            PurchaseInvoice::class,
            SalesProposal::class,
            PurchaseReturn::class,
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
        $files = [
            app_path('Http/Requests/StoreSalesInvoiceRequest.php'),
            app_path('Http/Requests/UpdateSalesInvoiceRequest.php'),
            app_path('Http/Requests/StorePurchaseInvoiceRequest.php'),
            app_path('Http/Requests/UpdatePurchaseInvoiceRequest.php'),
            app_path('Http/Requests/StoreSalesProposalRequest.php'),
            app_path('Http/Requests/UpdateSalesProposalRequest.php'),
            app_path('Http/Requests/StoreSalesReturnRequest.php'),
            app_path('Http/Requests/StorePurchaseReturnRequest.php'),
            app_path('Http/Requests/StoreTransferRequest.php'),
        ];

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
