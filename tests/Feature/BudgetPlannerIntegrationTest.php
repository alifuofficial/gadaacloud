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
use Workdo\BudgetPlanner\Models\Budget;
use Workdo\BudgetPlanner\Models\BudgetAllocation;
use Workdo\BudgetPlanner\Models\BudgetMonitoring;
use Workdo\BudgetPlanner\Models\BudgetPeriod;

class BudgetPlannerIntegrationTest extends TestCase
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

        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_name');
            $table->string('financial_year', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name');
            $table->unsignedBigInteger('period_id');
            $table->string('budget_type')->default('operational');
            $table->decimal('total_budget_amount', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_monitorings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_id');
            $table->date('monitoring_date');
            $table->decimal('total_allocated', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->decimal('total_remaining', 15, 2)->default(0);
            $table->decimal('variance_amount', 15, 2)->default(0);
            $table->decimal('variance_percentage', 8, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 401,
            'name' => 'Budget Company 1',
            'email' => 'budget1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 401,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 402,
            'name' => 'Budget Company 2',
            'email' => 'budget2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 402,
        ]);
    }

    public function test_multi_tenant_isolation_across_budget_planner_entities(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $period = BudgetPeriod::create([
            'period_name' => 'FY 2018 E.C.',
            'financial_year' => '2018 E.C.',
            'start_date' => '2025-07-08',
            'end_date' => '2026-07-07',
            'status' => 'active',
            'creator_id' => 401,
            'created_by' => 401,
        ]);

        $budget = Budget::create([
            'budget_name' => 'Operations Budget',
            'period_id' => $period->id,
            'budget_type' => 'operational',
            'total_budget_amount' => 500000,
            'status' => 'active',
            'creator_id' => 401,
            'created_by' => 401,
        ]);

        $allocation = BudgetAllocation::create([
            'budget_id' => $budget->id,
            'account_id' => 1,
            'allocated_amount' => 500000,
            'spent_amount' => 100000,
            'remaining_amount' => 400000,
            'creator_id' => 401,
            'created_by' => 401,
        ]);

        $monitoring = BudgetMonitoring::create([
            'budget_id' => $budget->id,
            'monitoring_date' => '2026-01-15',
            'total_allocated' => 500000,
            'total_spent' => 100000,
            'total_remaining' => 400000,
            'variance_amount' => 400000,
            'variance_percentage' => 80.0,
            'creator_id' => 401,
            'created_by' => 401,
        ]);

        $this->assertCount(1, BudgetPeriod::all());
        $this->assertCount(1, Budget::all());
        $this->assertCount(1, BudgetAllocation::all());
        $this->assertCount(1, BudgetMonitoring::all());

        // Switch to company 402
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, BudgetPeriod::all());
        $this->assertCount(0, Budget::all());
        $this->assertCount(0, BudgetAllocation::all());
        $this->assertCount(0, BudgetMonitoring::all());
        $this->assertNull(BudgetPeriod::find($period->id));
        $this->assertNull(Budget::find($budget->id));
    }
}
