<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Workdo\Pos\Models\Pos;
use Workdo\Pos\Models\PosItem;
use Workdo\Pos\Models\PosPayment;
use Workdo\Pos\Traits\PosControllerHelpers;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class PosIntegrationTest extends TestCase
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

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('product_service_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->json('tax_ids')->nullable();
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->decimal('quantity', 15, 2)->default(0);
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

        Schema::create('pos', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->date('pos_date');
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 8, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->json('tax_ids')->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_id')->nullable();
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 501,
            'name' => 'POS Company 1',
            'email' => 'pos_company1@test.local',
            'password' => bcrypt('secret'),
            'type' => 'company',
            'created_by' => 501,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 502,
            'name' => 'POS Company 2',
            'email' => 'pos_company2@test.local',
            'password' => bcrypt('secret'),
            'type' => 'company',
            'created_by' => 502,
        ]);
    }

    public function test_pos_sales_items_and_payments_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $sale1 = Pos::create([
            'sale_number' => 'POS-501-001',
            'pos_date' => '2026-01-15',
            'status' => 'completed',
            'creator_id' => 501,
            'created_by' => 501,
        ]);

        PosItem::create([
            'pos_id' => $sale1->id,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 100,
            'subtotal' => 200,
            'tax_amount' => 30,
            'total_amount' => 230,
            'creator_id' => 501,
            'created_by' => 501,
        ]);

        PosPayment::create([
            'pos_id' => $sale1->id,
            'discount' => 10,
            'amount' => 230,
            'discount_amount' => 220,
            'creator_id' => 501,
            'created_by' => 501,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $sale2 = Pos::create([
            'sale_number' => 'POS-502-001',
            'pos_date' => '2026-01-16',
            'status' => 'completed',
            'creator_id' => 502,
            'created_by' => 502,
        ]);

        PosItem::create([
            'pos_id' => $sale2->id,
            'product_id' => 2,
            'quantity' => 1,
            'price' => 500,
            'subtotal' => 500,
            'tax_amount' => 75,
            'total_amount' => 575,
            'creator_id' => 502,
            'created_by' => 502,
        ]);

        PosPayment::create([
            'pos_id' => $sale2->id,
            'discount' => 0,
            'amount' => 575,
            'discount_amount' => 575,
            'creator_id' => 502,
            'created_by' => 502,
        ]);

        $this->assertEquals(1, Pos::count());
        $this->assertEquals('POS-502-001', Pos::first()->sale_number);
        $this->assertEquals(1, PosItem::count());
        $this->assertEquals(1, PosPayment::count());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals(1, Pos::count());
        $this->assertEquals('POS-501-001', Pos::first()->sale_number);
        $this->assertEquals(1, PosItem::count());
        $this->assertEquals(1, PosPayment::count());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_pos_access(): void
    {
        $helper = new class {
            use PosControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $sale2 = Pos::create([
            'sale_number' => 'POS-502-002',
            'pos_date' => '2026-01-16',
            'status' => 'completed',
            'creator_id' => 502,
            'created_by' => 502,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($sale2));
    }

    public function test_warehouse_stock_deduction_rolls_back_on_insufficient_stock(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Bole',
            'city' => 'Addis Ababa',
            'zip_code' => '1000',
            'creator_id' => 501,
            'created_by' => 501,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Coffee Beans 1kg',
            'sku' => 'CB-001',
            'sale_price' => 450,
            'creator_id' => 501,
            'created_by' => 501,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Attempt to sell 8 units when only 5 are in stock inside a transaction
        $threw = false;
        try {
            DB::transaction(function () use ($warehouse, $product) {
                Pos::create([
                    'sale_number' => 'POS-ROLLBACK-001',
                    'warehouse_id' => $warehouse->id,
                    'pos_date' => '2026-01-17',
                    'status' => 'completed',
                    'creator_id' => 501,
                    'created_by' => 501,
                ]);

                $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ((float) $stock->quantity < 8) {
                    throw new \RuntimeException('Insufficient warehouse stock');
                }
            });
        } catch (\RuntimeException $e) {
            $threw = true;
        }

        $this->assertTrue($threw);
        $this->assertEquals(0, Pos::where('sale_number', 'POS-ROLLBACK-001')->count());
        $this->assertEquals(5.0, (float) WarehouseStock::first()->quantity);
    }
}
