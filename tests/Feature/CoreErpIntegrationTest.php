<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Traits\ControllerHelpers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Once;
use Tests\TestCase;

class CoreErpIntegrationTest extends TestCase
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

        $this->company1 = User::forceCreate([
            'id' => 1201,
            'name' => 'Core Tenant One',
            'email' => 'core1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1201,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1202,
            'name' => 'Core Tenant Two',
            'email' => 'core2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1202,
        ]);
    }

    public function test_core_warehouses_and_tenant_exists_isolate_tenants(): void
    {
        Auth::login($this->company1);
        Once::flush();
        $wh1 = Warehouse::create(['name' => 'Core WH 1', 'created_by' => 1201]);

        Auth::login($this->company2);
        Once::flush();
        $wh2 = Warehouse::create(['name' => 'Core WH 2', 'created_by' => 1202]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertContains($wh1->id, Warehouse::pluck('id')->toArray());
        $this->assertNotContains($wh2->id, Warehouse::pluck('id')->toArray());

        $validator = Validator::make(
            ['warehouse_id' => $wh2->id],
            ['warehouse_id' => ['required', ControllerHelpers::tenantExists('warehouses', 'id')]]
        );
        $this->assertTrue($validator->fails());
    }
}
