<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Traits\AccountControllerHelpers;

class AccountIntegrationTest extends TestCase
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

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code');
            $table->string('account_name');
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->unsignedBigInteger('parent_account_id')->nullable();
            $table->integer('level')->default(1);
            $table->string('normal_balance')->default('debit');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number');
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_type')->default('checking');
            $table->string('currency_code', 3)->default('ETB');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('routing_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->string('category_code');
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->nullable();
            $table->date('expense_date');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->unsignedBigInteger('chart_of_account_id');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 801,
            'name' => 'Account Tenant One',
            'email' => 'account1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 801,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 802,
            'name' => 'Account Tenant Two',
            'email' => 'account2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 802,
        ]);
    }

    public function test_account_models_isolate_data_between_tenants(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $gl1 = ChartOfAccount::create([
            'account_code' => '1001',
            'account_name' => 'Cash Tenant 1',
            'normal_balance' => 'debit',
            'created_by' => 801,
            'creator_id' => 801,
        ]);

        $bank1 = BankAccount::create([
            'account_number' => 'CBE-001',
            'account_name' => 'Main CBE 1',
            'bank_name' => 'CBE',
            'account_type' => 'checking',
            'gl_account_id' => $gl1->id,
            'created_by' => 801,
            'creator_id' => 801,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $gl2 = ChartOfAccount::create([
            'account_code' => '1002',
            'account_name' => 'Cash Tenant 2',
            'normal_balance' => 'debit',
            'created_by' => 802,
            'creator_id' => 802,
        ]);

        $bank2 = BankAccount::create([
            'account_number' => 'CBE-002',
            'account_name' => 'Main CBE 2',
            'bank_name' => 'CBE',
            'account_type' => 'checking',
            'gl_account_id' => $gl2->id,
            'created_by' => 802,
            'creator_id' => 802,
        ]);

        Auth::login($this->company1);
        Once::flush();
        $visibleToTenant1 = BankAccount::pluck('id')->toArray();
        $this->assertContains($bank1->id, $visibleToTenant1);
        $this->assertNotContains($bank2->id, $visibleToTenant1);

        Auth::login($this->company2);
        Once::flush();
        $visibleToTenant2 = BankAccount::pluck('id')->toArray();
        $this->assertContains($bank2->id, $visibleToTenant2);
        $this->assertNotContains($bank1->id, $visibleToTenant2);
    }

    public function test_tenant_exists_validation_blocks_cross_tenant_bank_account_reference(): void
    {
        Auth::login($this->company2);
        Once::flush();

        $bank2 = BankAccount::create([
            'account_number' => 'AWASH-002',
            'account_name' => 'Awash Tenant 2',
            'bank_name' => 'Awash',
            'account_type' => 'checking',
            'created_by' => 802,
            'creator_id' => 802,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['bank_account_id' => $bank2->id],
            ['bank_account_id' => ['required', AccountControllerHelpers::tenantExists('bank_accounts', 'id')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_access(): void
    {
        $helper = new class {
            use AccountControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $cat2 = ExpenseCategories::create([
            'category_name' => 'Travel',
            'category_code' => 'TRV-2',
            'created_by' => 802,
            'creator_id' => 802,
        ]);

        $exp2 = Expense::create([
            'expense_date' => '2026-01-01',
            'category_id' => $cat2->id,
            'bank_account_id' => 1,
            'chart_of_account_id' => 1,
            'amount' => 500,
            'created_by' => 802,
            'creator_id' => 802,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($exp2));
    }
}
