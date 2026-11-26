<?php

namespace Tests\Feature;

use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\TradeLc;
use App\Models\TradeShipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Traits\ControllerHelpers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;

class ImportExportAndInventoryIntegrationTest extends TestCase
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
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('trade_lcs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('lc_number')->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->string('issuing_bank');
            $table->decimal('amount', 15, 4);
            $table->string('currency', 10)->default('USD');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tolerance_percent', 5, 2)->default(0);
            $table->date('expiry_date');
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('trade_shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('lc_id')->nullable();
            $table->string('shipping_line');
            $table->string('vessel_name');
            $table->string('bill_of_lading')->unique();
            $table->string('status')->default('on_port');
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->string('adjustment_type');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->string('reason');
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('batch_number', 120);
            $table->decimal('initial_quantity', 15, 4)->default(0);
            $table->decimal('remaining_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 2501,
            'name' => 'Trade Tenant One',
            'email' => 'trade1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2501,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 2502,
            'name' => 'Trade Tenant Two',
            'email' => 'trade2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2502,
        ]);
    }

    public function test_trade_and_inventory_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $wh1 = Warehouse::create(['name' => 'Modjo Dry Port WH']);
        $lc1 = TradeLc::create([
            'lc_number' => 'LC-2501',
            'vendor_id' => 2501,
            'issuing_bank' => 'CBE',
            'amount' => 50000,
            'expiry_date' => '2026-12-31',
        ]);
        $ship1 = TradeShipment::create([
            'lc_id' => $lc1->id,
            'shipping_line' => 'ESLSE',
            'vessel_name' => 'MV Gibe',
            'bill_of_lading' => 'BL-2501',
        ]);
        $adj1 = InventoryAdjustment::create([
            'warehouse_id' => $wh1->id,
            'product_id' => 1,
            'adjustment_type' => 'addition',
            'quantity' => 10,
            'reason' => 'Opening stock',
        ]);
        $batch1 = InventoryBatch::create([
            'warehouse_id' => $wh1->id,
            'product_id' => 1,
            'batch_number' => 'ECX-YRG-01',
            'initial_quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost' => 450,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $wh2 = Warehouse::create(['name' => 'Adama WH']);
        $lc2 = TradeLc::create([
            'lc_number' => 'LC-2502',
            'vendor_id' => 2502,
            'issuing_bank' => 'Awash Bank',
            'amount' => 75000,
            'expiry_date' => '2026-12-31',
        ]);
        $ship2 = TradeShipment::create([
            'lc_id' => $lc2->id,
            'shipping_line' => 'Maersk',
            'vessel_name' => 'MV Red Sea',
            'bill_of_lading' => 'BL-2502',
        ]);
        $adj2 = InventoryAdjustment::create([
            'warehouse_id' => $wh2->id,
            'product_id' => 2,
            'adjustment_type' => 'addition',
            'quantity' => 20,
            'reason' => 'Receipt',
        ]);
        $batch2 = InventoryBatch::create([
            'warehouse_id' => $wh2->id,
            'product_id' => 2,
            'batch_number' => 'ECX-JIM-02',
            'initial_quantity' => 200,
            'remaining_quantity' => 200,
            'unit_cost' => 380,
        ]);

        $this->assertEquals([$wh2->id], Warehouse::pluck('id')->all());
        $this->assertEquals([$lc2->id], TradeLc::pluck('id')->all());
        $this->assertEquals([$ship2->id], TradeShipment::pluck('id')->all());
        $this->assertEquals([$adj2->id], InventoryAdjustment::pluck('id')->all());
        $this->assertEquals([$batch2->id], InventoryBatch::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$wh1->id], Warehouse::pluck('id')->all());
        $this->assertEquals([$lc1->id], TradeLc::pluck('id')->all());
        $this->assertEquals([$ship1->id], TradeShipment::pluck('id')->all());
        $this->assertEquals([$adj1->id], InventoryAdjustment::pluck('id')->all());
        $this->assertEquals([$batch1->id], InventoryBatch::pluck('id')->all());

        $helper = new class {
            use ControllerHelpers;
        };
        $this->assertTrue($helper->assertBelongsToTenant($lc1));
        $this->assertFalse($helper->assertBelongsToTenant($lc2));
    }
}
