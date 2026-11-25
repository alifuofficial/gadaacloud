<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;
use Workdo\Quotation\Models\SalesQuotationItemTax;
use Workdo\Quotation\Traits\QuotationControllerHelpers;

class QuotationIntegrationTest extends TestCase
{
    protected User $company1;
    protected User $company2;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('type')->default('company');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number');
            $table->integer('revision_number')->default(1);
            $table->unsignedBigInteger('parent_quotation_id')->nullable();
            $table->date('quotation_date');
            $table->date('due_date');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->boolean('converted_to_invoice')->default(false);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_quotation_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('tax_name');
            $table->decimal('tax_rate', 5, 2);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 601,
            'name' => 'Quotation Company 1',
            'email' => 'quotation_company1@test.local',
            'password' => bcrypt('secret'),
            'type' => 'company',
            'created_by' => 601,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 602,
            'name' => 'Quotation Company 2',
            'email' => 'quotation_company2@test.local',
            'password' => bcrypt('secret'),
            'type' => 'company',
            'created_by' => 602,
        ]);
    }

    public function test_quotations_items_and_taxes_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $q1 = SalesQuotation::create([
            'quotation_number' => 'QT-601-001',
            'quotation_date' => '2026-01-10',
            'due_date' => '2026-01-20',
            'customer_id' => 601,
            'subtotal' => 1000,
            'tax_amount' => 150,
            'discount_amount' => 0,
            'total_amount' => 1150,
            'status' => 'draft',
            'creator_id' => 601,
            'created_by' => 601,
        ]);

        $item1 = SalesQuotationItem::create([
            'quotation_id' => $q1->id,
            'product_id' => 1,
            'quantity' => 2,
            'unit_price' => 500,
            'discount_percentage' => 0,
            'tax_percentage' => 15,
            'creator_id' => 601,
            'created_by' => 601,
        ]);

        SalesQuotationItemTax::create([
            'item_id' => $item1->id,
            'tax_name' => 'VAT 15%',
            'tax_rate' => 15,
            'creator_id' => 601,
            'created_by' => 601,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $q2 = SalesQuotation::create([
            'quotation_number' => 'QT-602-001',
            'quotation_date' => '2026-01-11',
            'due_date' => '2026-01-21',
            'customer_id' => 602,
            'subtotal' => 2000,
            'tax_amount' => 300,
            'discount_amount' => 0,
            'total_amount' => 2300,
            'status' => 'sent',
            'creator_id' => 602,
            'created_by' => 602,
        ]);

        $item2 = SalesQuotationItem::create([
            'quotation_id' => $q2->id,
            'product_id' => 2,
            'quantity' => 4,
            'unit_price' => 500,
            'discount_percentage' => 0,
            'tax_percentage' => 15,
            'creator_id' => 602,
            'created_by' => 602,
        ]);

        SalesQuotationItemTax::create([
            'item_id' => $item2->id,
            'tax_name' => 'VAT 15%',
            'tax_rate' => 15,
            'creator_id' => 602,
            'created_by' => 602,
        ]);

        $this->assertEquals(1, SalesQuotation::count());
        $this->assertEquals('QT-602-001', SalesQuotation::first()->quotation_number);
        $this->assertEquals(1, SalesQuotationItem::count());
        $this->assertEquals(1, SalesQuotationItemTax::count());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals(1, SalesQuotation::count());
        $this->assertEquals('QT-601-001', SalesQuotation::first()->quotation_number);
        $this->assertEquals(1, SalesQuotationItem::count());
        $this->assertEquals(1, SalesQuotationItemTax::count());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_quotation_access(): void
    {
        $helper = new class {
            use QuotationControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $q2 = SalesQuotation::create([
            'quotation_number' => 'QT-602-002',
            'quotation_date' => '2026-01-12',
            'due_date' => '2026-01-22',
            'customer_id' => 602,
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500,
            'status' => 'draft',
            'creator_id' => 602,
            'created_by' => 602,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($q2));
    }

    public function test_sales_quotation_item_calculates_amounts_accurately(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $q1 = SalesQuotation::create([
            'quotation_number' => 'QT-601-CALC',
            'quotation_date' => '2026-01-13',
            'due_date' => '2026-01-23',
            'customer_id' => 601,
            'creator_id' => 601,
            'created_by' => 601,
        ]);

        $item = SalesQuotationItem::create([
            'quotation_id' => $q1->id,
            'product_id' => 1,
            'quantity' => 10,
            'unit_price' => 100,
            'discount_percentage' => 10,
            'tax_percentage' => 15,
            'creator_id' => 601,
            'created_by' => 601,
        ]);

        $this->assertEquals(100.0, (float) $item->discount_amount);
        $this->assertEquals(135.0, (float) $item->tax_amount);
        $this->assertEquals(1035.0, (float) $item->total_amount);
    }
}
