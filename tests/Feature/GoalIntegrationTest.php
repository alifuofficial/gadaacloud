<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalCategory;
use Workdo\Goal\Models\GoalContribution;
use Workdo\Goal\Models\GoalMilestone;
use Workdo\Goal\Models\GoalTracking;
use Workdo\Goal\Services\GoalErpIntegrationService;
use Workdo\Goal\Services\GoalService;

class GoalIntegrationTest extends TestCase
{
    protected User $company1;
    protected User $company2;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        // Create core users table
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

        // Create roles and model_has_roles tables for Spatie permission checks in Module_is_active()
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        // Create chart_of_accounts prerequisite for FK on goals.account_id
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('account_name')->nullable();
                $table->string('account_code')->nullable();
                $table->string('normal_balance')->default('debit');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Add-ons table for Module_is_active() checks
        if (!Schema::hasTable('add_ons')) {
            Schema::create('add_ons', function (Blueprint $table) {
                $table->id();
                $table->string('module');
                $table->string('name');
                $table->decimal('monthly_price', 8, 2)->default(0);
                $table->decimal('yearly_price', 8, 2)->default(0);
                $table->string('image')->nullable();
                $table->boolean('is_enable')->default(true);
                $table->boolean('for_admin')->default(false);
                $table->string('package_name')->default('Account');
                $table->integer('priority')->default(0);
                $table->timestamps();
            });

            DB::table('add_ons')->insert([
                'module'       => 'Account',
                'name'         => 'Account',
                'is_enable'    => 1,
                'package_name' => 'Account',
                'priority'     => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // Run Goal migrations
        $this->artisan('migrate', [
            '--path'     => 'packages/workdo/Goal/src/Database/Migrations',
            '--database' => 'sqlite',
        ])->assertSuccessful();

        // Optional mock tables for Account ERP sync
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('bank_name');
                $table->string('holder_name');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->unsignedBigInteger('chart_account_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->decimal('amount', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        $this->company1 = User::create([
            'name'       => 'Company Alpha',
            'slug'       => 'company-alpha',
            'email'      => 'alpha@test.com',
            'password'   => bcrypt('password'),
            'type'       => 'company',
            'created_by' => 1,
            'creator_id' => 1,
        ]);

        $this->company2 = User::create([
            'name'       => 'Company Beta',
            'slug'       => 'company-beta',
            'email'      => 'beta@test.com',
            'password'   => bcrypt('password'),
            'type'       => 'company',
            'created_by' => 2,
            'creator_id' => 2,
        ]);
    }

    public function test_multi_tenant_data_isolation(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cat1 = GoalCategory::create([
            'category_name' => 'Alpha Expansion Reserve',
            'category_code' => 'EXP-01',
            'is_active'     => true,
            'created_by'    => $this->company1->id,
            'creator_id'    => $this->company1->id,
        ]);

        $goal1 = Goal::create([
            'goal_name'      => 'Alpha Treasury Goal',
            'category_id'    => $cat1->id,
            'goal_type'      => 'savings',
            'target_amount'  => 50000.0,
            'current_amount' => 10000.0,
            'start_date'     => now()->toDateString(),
            'target_date'    => now()->addYear()->toDateString(),
            'priority'       => 'high',
            'status'         => 'active',
            'created_by'     => $this->company1->id,
            'creator_id'     => $this->company1->id,
        ]);

        $this->assertCount(1, Goal::all());

        // Switch to Company 2
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, Goal::all(), 'Company 2 must not see Company 1 goals due to TenantScoped');
        $this->assertCount(0, GoalCategory::all(), 'Company 2 must not see Company 1 categories');
    }

    public function test_atomic_goal_contributions_and_milestone_distribution(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cat = GoalCategory::create([
            'category_name' => 'Capital Reserves',
            'category_code' => 'CAP-01',
            'is_active'     => true,
            'created_by'    => $this->company1->id,
            'creator_id'    => $this->company1->id,
        ]);

        $goal = Goal::create([
            'goal_name'      => 'Corporate Reserve 2026',
            'category_id'    => $cat->id,
            'goal_type'      => 'savings',
            'target_amount'  => 10000.0,
            'current_amount' => 0.0,
            'start_date'     => now()->subMonth()->toDateString(),
            'target_date'    => now()->addMonths(5)->toDateString(),
            'priority'       => 'high',
            'status'         => 'active',
            'created_by'     => $this->company1->id,
            'creator_id'     => $this->company1->id,
        ]);

        // Create 2 sequential milestones: 4,000 and 6,000
        $ms1 = GoalMilestone::create([
            'goal_id'         => $goal->id,
            'milestone_name'  => 'Q1 Phase 1',
            'target_amount'   => 4000.0,
            'achieved_amount' => 0.0,
            'target_date'     => now()->addMonths(2)->toDateString(),
            'status'          => 'pending',
            'created_by'      => $this->company1->id,
            'creator_id'      => $this->company1->id,
        ]);

        $ms2 = GoalMilestone::create([
            'goal_id'         => $goal->id,
            'milestone_name'  => 'Q2 Phase 2',
            'target_amount'   => 6000.0,
            'achieved_amount' => 0.0,
            'target_date'     => now()->addMonths(4)->toDateString(),
            'status'          => 'pending',
            'created_by'      => $this->company1->id,
            'creator_id'      => $this->company1->id,
        ]);

        $goalService = new GoalService();

        // 1. Add contribution of 5,000
        $c1Id = $goalService->addGoalContribution($goal->id, [
            'contribution_date'   => now()->toDateString(),
            'contribution_amount' => 5000.0,
            'contribution_type'   => 'manual',
            'notes'               => 'First tranche funding',
        ]);

        $this->assertNotNull($c1Id);
        $goal->refresh();
        $this->assertEquals(5000.0, (float) $goal->current_amount);
        $this->assertSame('active', $goal->status);

        // Milestone 1 (4,000) should be achieved, Milestone 2 should have 1,000 achieved
        $ms1->refresh();
        $ms2->refresh();
        $this->assertSame('achieved', $ms1->status);
        $this->assertEquals(4000.0, (float) $ms1->achieved_amount);
        $this->assertSame('pending', $ms2->status);
        $this->assertEquals(1000.0, (float) $ms2->achieved_amount);

        // Tracking record should exist
        $tracking = GoalTracking::where('goal_id', $goal->id)->latest('id')->first();
        $this->assertNotNull($tracking);
        $this->assertEquals(50.0, (float) $tracking->progress_percentage);

        // 2. Add second contribution of 5,000 -> completes goal
        $c2Id = $goalService->addGoalContribution($goal->id, [
            'contribution_date'   => now()->toDateString(),
            'contribution_amount' => 5000.0,
            'contribution_type'   => 'manual',
            'notes'               => 'Final tranche',
        ]);

        $goal->refresh();
        $this->assertEquals(10000.0, (float) $goal->current_amount);
        $this->assertSame('completed', $goal->status, 'Goal status should transition to completed once target is reached');

        $ms2->refresh();
        $this->assertSame('achieved', $ms2->status);
        $this->assertEquals(6000.0, (float) $ms2->achieved_amount);

        // 3. Reverse the first contribution of 5,000
        $reversed = $goalService->reverseGoalContribution($c1Id, 'Erroneous voucher');
        $this->assertTrue($reversed);

        $goal->refresh();
        $this->assertEquals(5000.0, (float) $goal->current_amount);
        $this->assertSame('active', $goal->status, 'Status must revert to active when current amount drops below target');

        $ms1->refresh();
        $ms2->refresh();
        $this->assertSame('achieved', $ms1->status);
        $this->assertEquals(4000.0, (float) $ms1->achieved_amount);
        $this->assertSame('pending', $ms2->status);
        $this->assertEquals(1000.0, (float) $ms2->achieved_amount);
    }

    public function test_erp_bank_account_and_revenue_sync(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cat = GoalCategory::create([
            'category_name' => 'Operational Liquidity',
            'category_code' => 'OP-01',
            'is_active'     => true,
            'created_by'    => $this->company1->id,
            'creator_id'    => $this->company1->id,
        ]);

        $bankAccount = DB::table('bank_accounts')->insertGetId([
            'bank_name'       => 'Commercial Bank of Ethiopia',
            'holder_name'     => 'Gadaa Operations',
            'opening_balance' => 25000.0,
            'created_by'      => $this->company1->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $goal = Goal::create([
            'goal_name'       => 'CBE Liquidity Cushion',
            'category_id'     => $cat->id,
            'goal_type'       => 'savings',
            'target_amount'   => 50000.0,
            'current_amount'  => 0.0,
            'start_date'      => now()->subDays(10)->toDateString(),
            'target_date'     => now()->addMonths(6)->toDateString(),
            'bank_account_id' => $bankAccount,
            'priority'        => 'medium',
            'status'          => 'active',
            'created_by'      => $this->company1->id,
            'creator_id'      => $this->company1->id,
        ]);

        $goalService = new GoalService();
        $erpService = new GoalErpIntegrationService($goalService);

        $synced = $erpService->syncFromBankAccount($goal);
        $this->assertTrue($synced);

        $goal->refresh();
        $this->assertEquals(25000.0, (float) $goal->current_amount);

        // Test Revenue Sync
        $revenueGoal = Goal::create([
            'goal_name'      => 'Annual Revenue Target',
            'category_id'    => $cat->id,
            'goal_type'      => 'revenue_target',
            'target_amount'  => 100000.0,
            'current_amount' => 0.0,
            'start_date'     => now()->startOfYear()->toDateString(),
            'target_date'    => now()->endOfYear()->toDateString(),
            'priority'       => 'high',
            'status'         => 'active',
            'created_by'     => $this->company1->id,
            'creator_id'     => $this->company1->id,
        ]);

        DB::table('customer_payments')->insert([
            'date'       => now()->toDateString(),
            'amount'     => 42000.0,
            'created_by' => $this->company1->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revSynced = $erpService->syncRevenueFromPaidInvoices($revenueGoal);
        $this->assertTrue($revSynced);

        $revenueGoal->refresh();
        $this->assertEquals(42000.0, (float) $revenueGoal->current_amount);
    }
}
