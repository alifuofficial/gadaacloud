<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;
use Workdo\ProductService\Traits\ProductServiceControllerHelpers;

class ProductServiceIntegrationTest extends TestCase
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

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('product_service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#000000');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('product_service_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->json('tax_ids')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('action_type')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty_delta', 15, 2)->default(0);
            $table->decimal('final_qty', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1101,
            'name' => 'PS Tenant One',
            'email' => 'ps1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1101,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1102,
            'name' => 'PS Tenant Two',
            'email' => 'ps2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1102,
        ]);
    }

    public function test_product_service_items_and_warehouse_stocks_are_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $wh1 = Warehouse::create(['name' => 'Addis Hub 1', 'created_by' => 1101]);
        $item1 = ProductServiceItem::create(['name' => 'Teff 50kg', 'sku' => 'TF-01', 'sale_price' => 5000, 'purchase_price' => 4200]);
        $stock1 = WarehouseStock::create(['warehouse_id' => $wh1->id, 'product_id' => $item1->id, 'quantity' => 25]);

        Auth::login($this->company2);
        Once::flush();

        $wh2 = Warehouse::create(['name' => 'Adama Hub 2', 'created_by' => 1102]);
        $item2 = ProductServiceItem::create(['name' => 'Wheat 50kg', 'sku' => 'WH-02', 'sale_price' => 4000, 'purchase_price' => 3500]);
        $stock2 = WarehouseStock::create(['warehouse_id' => $wh2->id, 'product_id' => $item2->id, 'quantity' => 40]);

        Auth::login($this->company1);
        Once::flush();
        $this->assertContains($item1->id, ProductServiceItem::pluck('id')->toArray());
        $this->assertNotContains($item2->id, ProductServiceItem::pluck('id')->toArray());
        $this->assertContains($stock1->id, WarehouseStock::pluck('id')->toArray());
        $this->assertNotContains($stock2->id, WarehouseStock::pluck('id')->toArray());

        Auth::login($this->company2);
        Once::flush();
        $this->assertContains($stock2->id, WarehouseStock::pluck('id')->toArray());
        $this->assertNotContains($stock1->id, WarehouseStock::pluck('id')->toArray());
    }

    public function test_tenant_exists_blocks_cross_tenant_category_reference(): void
    {
        Auth::login($this->company2);
        Once::flush();

        $cat2 = ProductServiceCategory::create([
            'name' => 'Private Grains',
            'color' => '#222222',
            'created_by' => 1102,
            'creator_id' => 1102,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['category_id' => $cat2->id],
            ['category_id' => ['required', ProductServiceControllerHelpers::tenantExists('product_service_categories', 'id')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_product_access(): void
    {
        $helper = new class {
            use ProductServiceControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $item2 = ProductServiceItem::create(['name' => 'Secret SKU', 'sku' => 'SEC-02', 'sale_price' => 100, 'purchase_price' => 80]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($item2));
    }
}
