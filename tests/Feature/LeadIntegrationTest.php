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
use Workdo\Account\Models\Customer;
use Workdo\Contract\Models\Contract;
use Workdo\Lead\Models\ClientDeal;
use Workdo\Lead\Models\Deal;
use Workdo\Lead\Models\DealStage;
use Workdo\Lead\Models\Lead;
use Workdo\Lead\Models\LeadStage;
use Workdo\Lead\Models\Pipeline;
use Workdo\Lead\Services\LeadErpIntegrationService;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Taskly\Models\Project;

class LeadIntegrationTest extends TestCase
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
            $table->string('mobile_no')->nullable();
            $table->text('active_module')->nullable();
            $table->unsignedBigInteger('active_plan')->default(0);
            $table->date('plan_expire_date')->nullable();
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

        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('name');
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->decimal('yearly_price', 8, 2)->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_enable')->default(true);
            $table->boolean('for_admin')->default(false);
            $table->string('package_name')->default('Lead');
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        foreach (['Lead', 'Account', 'Quotation', 'Contract', 'Taskly'] as $mod) {
            DB::table('add_ons')->insert([
                'module'       => $mod,
                'name'         => $mod,
                'is_enable'    => 1,
                'package_name' => $mod,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('modules')->nullable();
            $table->timestamps();
        });

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Enterprise',
            'modules' => json_encode(['Lead', 'Account', 'Quotation', 'Contract', 'Taskly']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('user_active_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pipeline_id');
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pipeline_id');
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name', 150)->nullable();
            $table->string('email');
            $table->string('subject');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('sources')->nullable();
            $table->string('products')->nullable();
            $table->text('notes')->nullable();
            $table->string('labels')->nullable();
            $table->integer('order')->default(0);
            $table->string('phone')->nullable();
            $table->string('tin_number', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('is_converted')->default(0);
            $table->date('date')->nullable();
            $table->string('ethiopian_fiscal_year', 30)->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->text('sources')->nullable();
            $table->text('products')->nullable();
            $table->text('notes')->nullable();
            $table->text('labels')->nullable();
            $table->string('phone')->nullable();
            $table->string('tin_number', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('status')->default('Active');
            $table->string('ethiopian_fiscal_year', 30)->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('client_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('deal_id');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('company_name')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_mobile')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->nullable();
            $table->date('quotation_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->unsignedBigInteger('user_id');
            $table->decimal('value', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('source_type')->default('contract');
            $table->string('contract_number')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('project_scope')->default('client');
            $table->text('description')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('Ongoing');
            $table->boolean('is_client_visible')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::create([
            'id' => 1,
            'name' => 'Company One',
            'email' => 'comp1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'active_module' => 'Lead,Account,Quotation,Contract,Taskly',
            'active_plan' => 1,
            'plan_expire_date' => now()->addYear()->toDateString(),
        ]);
        $this->company1->created_by = 1;
        $this->company1->save();

        $this->company2 = User::create([
            'id' => 2,
            'name' => 'Company Two',
            'email' => 'comp2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
        ]);
        $this->company2->created_by = 2;
        $this->company2->save();
    }

    public function test_multi_tenant_isolation_on_leads_and_deals(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $pipeline = Pipeline::create(['name' => 'Sales', 'creator_id' => 1, 'created_by' => 1]);
        $lStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $pipeline->id, 'order' => 1, 'creator_id' => 1, 'created_by' => 1]);
        $dStage = DealStage::create(['name' => 'Proposal', 'pipeline_id' => $pipeline->id, 'order' => 1, 'creator_id' => 1, 'created_by' => 1]);

        $lead = Lead::create([
            'name' => 'Abebe Kebede',
            'company_name' => 'Abyssinia Tech',
            'email' => 'abebe@abyssinia.et',
            'subject' => 'ERP Implementation',
            'user_id' => 1,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $lStage->id,
            'phone' => '0911234567',
            'tin_number' => '0012345678',
            'creator_id' => 1,
            'created_by' => 1,
        ]);

        $deal = Deal::create([
            'name' => 'Abyssinia ERP Deal',
            'price' => 250000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $dStage->id,
            'tin_number' => '0012345678',
            'creator_id' => 1,
            'created_by' => 1,
        ]);

        $this->assertCount(1, Lead::all());
        $this->assertCount(1, Deal::all());

        // Switch to company 2
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, Lead::all());
        $this->assertCount(0, Deal::all());
        $this->assertNull(Lead::find($lead->id));
        $this->assertNull(Deal::find($deal->id));
    }

    public function test_lead_erp_integration_syncs_client_to_account_customer_with_tin(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $client = User::create([
            'id' => 10,
            'name' => 'Ethio Client',
            'email' => 'client@ethio.et',
            'password' => bcrypt('password'),
            'type' => 'client',
            'created_by' => 1,
        ]);

        $service = new LeadErpIntegrationService();
        $customer = $service->syncClientToAccountCustomer($client, [
            'company_name' => 'Ethio Trading PLC',
            'phone' => '+251911223344',
            'tin_number' => '0098765432',
        ]);

        $this->assertNotNull($customer);
        $this->assertEquals('Ethio Trading PLC', $customer->company_name);
        $this->assertEquals('0098765432', $customer->tax_number);
        $this->assertEquals($client->id, $customer->user_id);
    }

    public function test_deal_conversion_to_quotation_contract_and_project(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $client = User::create([
            'id' => 11,
            'name' => 'Addis Corp',
            'email' => 'info@addiscorp.et',
            'password' => bcrypt('password'),
            'type' => 'client',
            'created_by' => 1,
        ]);

        $pipeline = Pipeline::create(['name' => 'Enterprise', 'creator_id' => 1, 'created_by' => 1]);
        $dStage = DealStage::create(['name' => 'Won', 'pipeline_id' => $pipeline->id, 'order' => 1, 'creator_id' => 1, 'created_by' => 1]);

        $deal = Deal::create([
            'name' => 'Addis Corp Cloud Rollout',
            'price' => 500000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $dStage->id,
            'tin_number' => '0044556677',
            'creator_id' => 1,
            'created_by' => 1,
        ]);

        ClientDeal::create(['client_id' => $client->id, 'deal_id' => $deal->id]);

        $service = new LeadErpIntegrationService();

        $quotation = $service->convertDealToQuotation($deal);
        $contract = $service->convertDealToContract($deal);
        $project = $service->convertDealToProject($deal);

        $deal->refresh();

        $this->assertNotNull($quotation);
        $this->assertEquals(500000.0, (float) $quotation->total_amount);
        $this->assertEquals($quotation->id, $deal->quotation_id);

        $this->assertNotNull($contract);
        $this->assertEquals('Addis Corp Cloud Rollout', $contract->subject);
        $this->assertEquals($contract->id, $deal->contract_id);

        $this->assertNotNull($project);
        $this->assertEquals('client', $project->project_scope);
        $this->assertEquals($project->id, $deal->project_id);
    }
}
