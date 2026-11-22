<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Warehouse;
use Workdo\Account\Models\Customer;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsAuditLog;
use App\Events\PostSalesInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

class EimsAccountingIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_invoice_posting_triggers_eims_clearance_and_loads_for_printing(): void
    {
        // 1. Use company user
        $company = User::where('type', 'company')->first() ?? User::find(2);
        $this->actingAs($company);

        // 2. Set up EIMS tenant settings
        $setting = EimsTenantSetting::updateOrCreate(
            ['created_by' => $company->id],
            [
                'tin' => '0098765432',
                'vat_number' => 'VAT0098765432',
                'legal_name' => 'Gadaa Trading PLC',
                'trade_name' => 'Gadaa Commerce',
                'branch_code' => 'MAIN',
                'device_id' => 'POS-01',
                'environment' => 'sandbox',
                'api_secret' => 'test-hmac-secret-xyz',
                'offline_resiliency_enabled' => true,
                'auto_clear_on_post' => true,
            ]
        );

        // 3. Create a Customer with 10-digit TIN
        $customerUser = User::factory()->create([
            'type' => 'client',
            'name' => 'Ethiopian Airlines Group',
            'email' => 'finance@ethiopianairlines.com',
            'created_by' => $company->id,
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_code' => 'CUST-0099',
            'company_name' => 'Ethiopian Airlines Group',
            'contact_person_name' => 'Finance Director',
            'contact_person_email' => 'finance@ethiopianairlines.com',
            'contact_person_mobile' => '+251911000000',
            'tax_number' => '0011223344', // 10-digit TIN (B2B)
            'billing_address' => [
                'city' => 'Addis Ababa',
                'sub_city' => 'Bole',
                'woreda' => '02',
            ],
            'created_by' => $company->id,
        ]);

        // 4. Create Product
        $product = ProductServiceItem::create([
            'name' => 'Commercial ERP License',
            'sku' => 'SW-ERP-01',
            'sale_price' => 1000.00,
            'type' => 'service',
            'is_active' => true,
            'created_by' => $company->id,
        ]);

        // 5. Create Sales Invoice
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-TEST-EIMS-01',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'customer_id' => $customerUser->id,
            'subtotal' => 1000.00,
            'tax_amount' => 150.00,
            'discount_amount' => 0.00,
            'total_amount' => 1150.00,
            'paid_amount' => 1150.00,
            'balance_amount' => 0.00,
            'status' => 'draft',
            'type' => 'service',
            'created_by' => $company->id,
            'creator_id' => $company->id,
        ]);

        SalesInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'tax_percentage' => 15.00,
            'tax_amount' => 150.00,
            'discount_amount' => 0.00,
            'total_amount' => 1150.00,
        ]);

        // 6. Dispatch PostSalesInvoice event (same as SalesInvoiceController@post)
        event(new PostSalesInvoice($invoice));

        // 7. Assert EimsInvoice was created and cleared
        $eimsInv = EimsInvoice::where('sales_invoice_id', $invoice->id)->first();
        $this->assertNotNull($eimsInv, 'EimsInvoice record must be created upon posting.');
        $this->assertEquals(EimsInvoice::STATUS_CLEARED, $eimsInv->status);
        $this->assertEquals(64, strlen($eimsInv->irn));
        $this->assertEquals('B2B', $eimsInv->buyer_type);
        $this->assertEquals('0011223344', $eimsInv->buyer_tin);
        $this->assertNotEmpty($eimsInv->qr_code_image);
        $this->assertStringContainsString('<svg', $eimsInv->qr_code_image);

        // 8. Assert relationship works on SalesInvoice model
        $reloaded = SalesInvoice::with('eimsInvoice')->find($invoice->id);
        $this->assertNotNull($reloaded->eimsInvoice);
        $this->assertEquals($eimsInv->irn, $reloaded->eimsInvoice->irn);

        // 9. Assert INSA cryptographic audit log was recorded
        $audit = EimsAuditLog::where('eims_invoice_id', $eimsInv->id)->where('action', 'REGISTER')->first();
        $this->assertNotNull($audit, 'INSA audit log must exist for clearance.');
        $this->assertEquals(64, strlen($audit->signature));
        $this->assertEquals(64, strlen($audit->payload_hash));
    }
}
