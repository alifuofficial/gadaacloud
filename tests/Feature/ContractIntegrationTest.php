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
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractType;
use Workdo\Contract\Models\ContractRenewal;
use Workdo\Contract\Traits\ContractControllerHelpers;

class ContractIntegrationTest extends TestCase
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

        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->decimal('value', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default('0');
            $table->string('source_type')->default('contract');
            $table->string('template_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('value', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id'         => 701,
            'name'       => 'Contract Company 1',
            'email'      => 'contract_company1@test.local',
            'password'   => bcrypt('secret'),
            'type'       => 'company',
            'created_by' => 701,
        ]);

        $this->company2 = User::forceCreate([
            'id'         => 702,
            'name'       => 'Contract Company 2',
            'email'      => 'contract_company2@test.local',
            'password'   => bcrypt('secret'),
            'type'       => 'company',
            'created_by' => 702,
        ]);
    }

    public function test_contracts_and_types_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $type1 = ContractType::create([
            'name'       => 'Service Agreement',
            'is_active'  => true,
            'creator_id' => 701,
            'created_by' => 701,
        ]);

        Contract::create([
            'subject'     => 'Web Dev Contract 701',
            'value'       => 50000,
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-12-31',
            'status'      => 'active',
            'source_type' => 'contract',
            'type_id'     => $type1->id,
            'user_id'     => 701,
            'creator_id'  => 701,
            'created_by'  => 701,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $type2 = ContractType::create([
            'name'       => 'Procurement',
            'is_active'  => true,
            'creator_id' => 702,
            'created_by' => 702,
        ]);

        Contract::create([
            'subject'     => 'Supply Contract 702',
            'value'       => 120000,
            'start_date'  => '2026-02-01',
            'end_date'    => '2026-11-30',
            'status'      => 'draft',
            'source_type' => 'contract',
            'type_id'     => $type2->id,
            'user_id'     => 702,
            'creator_id'  => 702,
            'created_by'  => 702,
        ]);

        // Company 2 sees only their own
        $this->assertEquals(1, Contract::count());
        $this->assertEquals('Supply Contract 702', Contract::first()->subject);
        $this->assertEquals(1, ContractType::count());
        $this->assertEquals('Procurement', ContractType::first()->name);

        Auth::login($this->company1);
        Once::flush();

        // Company 1 sees only their own
        $this->assertEquals(1, Contract::count());
        $this->assertEquals('Web Dev Contract 701', Contract::first()->subject);
        $this->assertEquals(1, ContractType::count());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_contract_access(): void
    {
        $helper = new class {
            use ContractControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $contract2 = Contract::create([
            'subject'     => 'Cross-Tenant Test 702',
            'value'       => 10000,
            'start_date'  => '2026-03-01',
            'end_date'    => '2026-09-30',
            'status'      => 'draft',
            'source_type' => 'contract',
            'creator_id'  => 702,
            'created_by'  => 702,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($contract2));
    }

    public function test_contract_renewal_is_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $contract = Contract::create([
            'subject'     => 'Renewable Contract 701',
            'value'       => 30000,
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-12-31',
            'status'      => 'expired',
            'source_type' => 'contract',
            'creator_id'  => 701,
            'created_by'  => 701,
        ]);

        ContractRenewal::create([
            'contract_id' => $contract->id,
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-12-31',
            'value'       => 35000,
            'status'      => 'approved',
            'creator_id'  => 701,
            'created_by'  => 701,
        ]);

        Auth::login($this->company2);
        Once::flush();

        // Company 2 should see zero renewals
        $this->assertEquals(0, ContractRenewal::count());

        Auth::login($this->company1);
        Once::flush();

        // Company 1 sees their renewal
        $this->assertEquals(1, ContractRenewal::count());
        $this->assertEquals(35000.0, (float) ContractRenewal::first()->value);
    }
}
