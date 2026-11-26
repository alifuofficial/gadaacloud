<?php

namespace Tests\Feature;

use App\Http\Middleware\UpdateUserActiveStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Hrm\Http\Controllers\PayrollController;
use Workdo\Hrm\Http\Controllers\ResignationController;
use Workdo\Hrm\Http\Controllers\TerminationController;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Payroll;
use Workdo\Hrm\Models\PayrollEntry;
use Workdo\Hrm\Models\Resignation;
use Workdo\Hrm\Models\Termination;
use Workdo\Hrm\Services\EmployeeLifecycleService;
use Workdo\Hrm\Services\EthiopianPayrollService;
use Workdo\Taskly\Http\Controllers\ProjectController;
use Workdo\Taskly\Models\BugStage;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectBug;
use Workdo\Taskly\Models\ProjectTask;
use Workdo\Taskly\Models\ProjectUser;
use Workdo\Taskly\Models\TaskStage;

class EmployeeLifecycleAndProjectIntegrationTest extends TestCase
{
    protected User $company;
    protected User $empResignImmediate;
    protected User $empActiveTeammate;
    protected User $empNoticePeriod;
    protected User $empTerminated;
    protected User $empPlanLimited;
    protected User $empMidPeriodResign;

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
            $table->string('avatar')->nullable();
            $table->string('type')->default('company');
            $table->integer('is_disable')->default(0);
            $table->integer('is_enable_login')->default(1);
            $table->string('deactivation_reason', 40)->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('active_plan')->nullable();
            $table->date('plan_expire_date')->nullable();
            $table->date('trial_expire_date')->nullable();
            $table->integer('total_user')->default(-1);
            $table->decimal('storage_limit', 15, 2)->default(0);
            $table->string('remember_token')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('number_of_users')->default(-1);
            $table->decimal('storage_limit', 15, 2)->default(0);
            $table->text('modules')->nullable();
            $table->integer('trial_days')->default(0);
            $table->boolean('is_plan_enable')->default(true);
            $table->timestamps();
        });

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

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('basic_salary', 15, 2)->default(10000);
            $table->boolean('is_pension_applicable')->default(false);
            $table->boolean('is_tax_exempt')->default(true);
            $table->decimal('rate_per_hour', 10, 2)->default(0);
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('employment_status', 30)->default('active');
            $table->date('separation_date')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('resignations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('last_working_date')->nullable();
            $table->date('resignation_date')->nullable();
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->string('document')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('terminations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('termination_type_id')->nullable();
            $table->date('notice_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->string('document')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('allowance_type_id')->nullable();
            $table->string('type')->default('fixed');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('deduction_type_id')->nullable();
            $table->string('type')->default('fixed');
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('loan_type_id')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date')->nullable();
            $table->string('status')->default('present');
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days')->default(1);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('payroll_frequency')->default('monthly');
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->date('pay_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net_pay', 12, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('employee_pension', 12, 2)->default(0);
            $table->decimal('employer_pension', 12, 2)->default(0);
            $table->decimal('income_tax', 12, 2)->default(0);
            $table->decimal('total_loans', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('per_day_salary', 12, 2)->default(0);
            $table->integer('working_days')->default(0);
            $table->decimal('present_days', 5, 2)->default(0);
            $table->decimal('half_days', 5, 2)->default(0);
            $table->decimal('half_day_deduction', 12, 2)->default(0);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('absent_day_deduction', 12, 2)->default(0);
            $table->decimal('paid_leave_days', 5, 2)->default(0);
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->decimal('unpaid_leave_deduction', 12, 2)->default(0);
            $table->decimal('manual_overtime_hours', 5, 2)->default(0);
            $table->decimal('total_manual_overtimes', 12, 2)->default(0);
            $table->decimal('attendance_overtime_hours', 5, 2)->default(0);
            $table->decimal('attendance_overtime_rate', 12, 2)->default(0);
            $table->decimal('attendance_overtime_amount', 12, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->json('allowances_breakdown')->nullable();
            $table->json('deductions_breakdown')->nullable();
            $table->json('manual_overtimes_breakdown')->nullable();
            $table->json('loans_breakdown')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('Ongoing');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('task_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('complete')->default(false);
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->string('assigned_to')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('bug_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('complete')->default(false);
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_bugs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->string('assigned_to')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('project_id');
            $table->string('log_type');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('bug_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bug_id');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Gate::before(fn () => true);

        $this->company = User::forceCreate([
            'id' => 2601,
            'name' => 'Gadaa Lifecycle Co',
            'email' => 'company2601@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'total_user' => 10,
            'created_by' => 2601,
        ]);

        $this->empResignImmediate = $this->createStaffEmployee(2610, 'Resigning Dev', 'resign@2601.et', 10000);
        $this->empActiveTeammate = $this->createStaffEmployee(2611, 'Active Engineer', 'active@2601.et', 10000);
        $this->empNoticePeriod = $this->createStaffEmployee(2612, 'Notice Dev', 'notice@2601.et', 10000);
        $this->empTerminated = $this->createStaffEmployee(2613, 'Terminated Staff', 'term@2601.et', 10000);
        $this->empPlanLimited = $this->createStaffEmployee(2614, 'Plan Limited', 'plan@2601.et', 10000);
        $this->empMidPeriodResign = $this->createStaffEmployee(2615, 'Mid Month Resign', 'midmonth@2601.et', 10000);

        DB::table('settings')->insert([
            ['key' => 'working_days', 'value' => json_encode([1, 2, 3, 4, 5]), 'created_by' => 2601],
            ['key' => 'defult_currancy', 'value' => 'USD', 'created_by' => 2601],
            ['key' => 'useEthiopianTax', 'value' => 'off', 'created_by' => 2601],
        ]);
    }

    private function createStaffEmployee(int $id, string $name, string $email, float $salary): User
    {
        $user = User::forceCreate([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'type' => 'staff',
            'is_disable' => 0,
            'is_enable_login' => 1,
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        Employee::create([
            'employee_id' => 'EMP-' . $id,
            'user_id' => $id,
            'basic_salary' => $salary,
            'is_pension_applicable' => false,
            'is_tax_exempt' => true,
            'employment_status' => 'active',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        return $user;
    }

    public function test_immediate_resignation_deactivates_account_filters_pickers_and_retains_project_history(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        $project = Project::create(['name' => 'Addis Cloud ERP', 'creator_id' => 2601, 'created_by' => 2601]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => 2610, 'created_by' => 2601]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => 2611, 'created_by' => 2601]);

        $todoStage = TaskStage::create(['name' => 'Todo', 'complete' => false, 'order' => 0, 'created_by' => 2601]);
        ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Open API Task',
            'assigned_to' => json_encode(['2610']),
            'stage_id' => $todoStage->id,
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        $resignation = Resignation::create([
            'employee_id' => 2610,
            'last_working_date' => now()->toDateString(),
            'resignation_date' => now()->subDays(10)->toDateString(),
            'reason' => 'Relocation',
            'status' => 'pending',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        $controller = new ResignationController();
        $controller->updateStatus(Request::create('/', 'PUT'), $resignation, 'accepted');

        $user = User::find(2610);
        $emp = Employee::where('user_id', 2610)->first();

        $this->assertEquals(1, $user->is_disable);
        $this->assertEquals(0, $user->is_enable_login);
        $this->assertEquals('resigned', $user->deactivation_reason);
        $this->assertTrue($user->is_deactivated);
        $this->assertEquals('Resigned', $user->account_status_label);
        $this->assertEquals('resigned', $emp->employment_status);

        // New assignment dropdowns must exclude resigned staff
        $activeEmpIds = User::activeEmp()->where('created_by', 2601)->pluck('id')->all();
        $this->assertNotContains(2610, $activeEmpIds);
        $this->assertContains(2611, $activeEmpIds);

        // Historical project roster stays intact while reflecting Resigned metadata
        $project->load('teamMembers');
        $resignedMember = $project->teamMembers->firstWhere('id', 2610);
        $this->assertNotNull($resignedMember);
        $this->assertTrue($resignedMember->is_deactivated);
        $this->assertEquals('Resigned', $resignedMember->account_status_label);
    }

    public function test_future_notice_period_stays_active_then_scheduler_deactivates_after_separation_date(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        DB::table('sessions')->insert([
            'id' => 'sess-notice-2612',
            'user_id' => 2612,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $resignation = Resignation::create([
            'employee_id' => 2612,
            'last_working_date' => now()->addDays(7)->toDateString(),
            'resignation_date' => now()->toDateString(),
            'status' => 'pending',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        app(ResignationController::class)->updateStatus(Request::create('/', 'PUT'), $resignation, 'accepted');

        $emp = Employee::where('user_id', 2612)->first();
        $user = User::find(2612);
        $this->assertEquals('notice_period', $emp->employment_status);
        $this->assertEquals(0, $user->is_disable);
        $this->assertContains(2612, User::activeEmp()->where('created_by', 2601)->pluck('id')->all());

        // Fast-forward separation date into the past and run daily scheduler command
        $emp->separation_date = now()->subDay()->toDateString();
        $emp->save();

        Artisan::call('hrm:process-separations');

        $emp->refresh();
        $user->refresh();
        $this->assertEquals('resigned', $emp->employment_status);
        $this->assertEquals(1, $user->is_disable);
        $this->assertEquals(0, $user->is_enable_login);
        $this->assertEquals('resigned', $user->deactivation_reason);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-notice-2612']);
    }

    public function test_assign_plan_upgrade_never_reactivates_resigned_or_terminated_users(): void
    {
        Event::fake();
        $svc = app(EmployeeLifecycleService::class);
        $svc->handleSeparation(2610, 2601, 'resigned', now()->toDateString());
        $svc->handleSeparation(2613, 2601, 'terminated', now()->toDateString());

        $this->empPlanLimited->is_disable = 1;
        $this->empPlanLimited->is_enable_login = 0;
        $this->empPlanLimited->deactivation_reason = 'plan_limit';
        $this->empPlanLimited->save();

        $unlimitedPlan = Plan::create([
            'name' => 'Unlimited Enterprise',
            'number_of_users' => -1,
            'storage_limit' => 1000,
            'modules' => json_encode(['Hrm', 'Taskly']),
        ]);

        assignPlan($unlimitedPlan->id, 'month', null, null, 2601);

        $this->assertEquals(0, User::find(2614)->is_disable);
        $this->assertNull(User::find(2614)->deactivation_reason);

        // Resigned and terminated must remain deactivated
        $this->assertEquals(1, User::find(2610)->is_disable);
        $this->assertEquals(0, User::find(2610)->is_enable_login);
        $this->assertEquals('resigned', User::find(2610)->deactivation_reason);

        $this->assertEquals(1, User::find(2613)->is_disable);
        $this->assertEquals(0, User::find(2613)->is_enable_login);
        $this->assertEquals('terminated', User::find(2613)->deactivation_reason);
    }

    public function test_middleware_terminates_active_session_for_disabled_user(): void
    {
        app(EmployeeLifecycleService::class)->handleSeparation(2610, 2601, 'resigned', now()->toDateString());

        $disabledUser = User::find(2610);
        Auth::login($disabledUser);
        $this->assertTrue(Auth::check());

        $middleware = new UpdateUserActiveStatus();
        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertFalse(Auth::check());
    }

    public function test_resignation_reversal_restores_account_and_enforces_plan_limit(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        $resignation = Resignation::create([
            'employee_id' => 2610,
            'last_working_date' => now()->toDateString(),
            'status' => 'pending',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        $controller = new ResignationController();
        $controller->updateStatus(Request::create('/', 'PUT'), $resignation, 'accepted');
        $this->assertEquals(1, User::find(2610)->is_disable);

        // With available seats, reversing resignation re-enables account
        $controller->updateStatus(Request::create('/', 'PUT'), $resignation, 'rejected');
        $this->assertEquals(0, User::find(2610)->is_disable);
        $this->assertEquals('active', Employee::where('user_id', 2610)->first()->employment_status);

        // Now accept again, fill plan seat quota (total_user = 1), and try reversing
        $controller->updateStatus(Request::create('/', 'PUT'), $resignation, 'accepted');
        $this->company->total_user = 1;
        $this->company->save();

        $controller->updateStatus(Request::create('/', 'PUT'), $resignation, 'rejected');
        $this->assertEquals('active', Employee::where('user_id', 2610)->first()->employment_status);
        $this->assertEquals(1, User::find(2610)->is_disable);
        $this->assertEquals('plan_limit', User::find(2610)->deactivation_reason);
    }

    public function test_payroll_excludes_prior_accepted_resignation_and_prorates_mid_period_separation(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        // Resigned BEFORE November pay period (Oct 15) -> must be completely excluded
        $priorRes = Resignation::create([
            'employee_id' => 2610,
            'last_working_date' => '2026-10-15',
            'status' => 'accepted',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);
        app(EmployeeLifecycleService::class)->handleSeparation(2610, 2601, 'resigned', '2026-10-15');

        // Resigned MID-November (Nov 13 -> half month even though login disabled)
        Resignation::create([
            'employee_id' => 2615,
            'last_working_date' => '2026-11-13',
            'status' => 'accepted',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);
        app(EmployeeLifecycleService::class)->handleSeparation(2615, 2601, 'resigned', '2026-11-13');

        $payroll = Payroll::create([
            'title' => 'November 2026 Payroll',
            'payroll_frequency' => 'monthly',
            'pay_period_start' => '2026-11-01',
            'pay_period_end' => '2026-11-30',
            'pay_date' => '2026-11-30',
            'status' => 'draft',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        $controller = new PayrollController(new EthiopianPayrollService());
        $controller->runPayroll($payroll);

        $entries = PayrollEntry::where('payroll_id', $payroll->id)->get()->keyBy('employee_id');

        // Prior accepted resignation (Oct 15) must NOT receive November pay!
        $this->assertFalse($entries->has(2610), 'Prior accepted resignation was mistakenly paid.');

        // Active full-month employee gets full 10,000 basic salary
        $this->assertTrue($entries->has(2611));
        $this->assertEquals(10000.0, (float) $entries[2611]->basic_salary);

        // Mid-period separated employee gets prorated pay (>0 and <10,000) despite is_disable = 1
        $this->assertTrue($entries->has(2615));
        $midSalary = (float) $entries[2615]->basic_salary;
        $this->assertGreaterThan(4000.0, $midSalary);
        $this->assertLessThan(6000.0, $midSalary);
    }

    public function test_project_reassign_member_work_transfers_open_items_while_preserving_completed_history(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        app(EmployeeLifecycleService::class)->handleSeparation(2610, 2601, 'resigned', now()->toDateString());

        $project = Project::create(['name' => 'Bole Tower IT', 'creator_id' => 2601, 'created_by' => 2601]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => 2610, 'created_by' => 2601]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => 2611, 'created_by' => 2601]);

        $todoStage = TaskStage::create(['name' => 'Todo', 'complete' => false, 'order' => 0, 'created_by' => 2601]);
        $doneStage = TaskStage::create(['name' => 'Done', 'complete' => true, 'order' => 1, 'created_by' => 2601]);
        $bugOpenStage = BugStage::create(['name' => 'Confirmed', 'complete' => false, 'order' => 0, 'created_by' => 2601]);

        $openTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Migrate Auth',
            'assigned_to' => json_encode(['2610']),
            'stage_id' => $todoStage->id,
            'created_by' => 2601,
        ]);
        $doneTask = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Historical Sprint Task',
            'assigned_to' => json_encode(['2610']),
            'stage_id' => $doneStage->id,
            'created_by' => 2601,
        ]);
        $openBug = ProjectBug::create([
            'project_id' => $project->id,
            'title' => 'Memory Leak',
            'assigned_to' => '2610',
            'stage_id' => $bugOpenStage->id,
            'created_by' => 2601,
        ]);

        $controller = new ProjectController();
        $req = Request::create('/', 'POST', [
            'from_user_id' => 2610,
            'to_user_id' => 2611,
            'remove_from_team' => true,
        ]);
        $controller->reassignMemberWork($req, $project);

        $openTask->refresh();
        $doneTask->refresh();
        $openBug->refresh();

        $this->assertEquals(['2611'], json_decode($openTask->assigned_to, true));
        $this->assertEquals(['2611'], json_decode($openBug->assigned_to, true));
        // Completed work must retain historical assignee for audit & EVM
        $this->assertEquals(['2610'], json_decode($doneTask->assigned_to, true));

        $memberIds = $project->teamMembers()->pluck('users.id')->all();
        $this->assertNotContains(2610, $memberIds);
        $this->assertContains(2611, $memberIds);

        // Assigning open work to a deactivated user (e.g. terminated 2613) must fail validation
        app(EmployeeLifecycleService::class)->handleSeparation(2613, 2601, 'terminated', now()->toDateString());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->reassignMemberWork(Request::create('/', 'POST', [
            'from_user_id' => 2611,
            'to_user_id' => 2613,
        ]), $project);
    }

    public function test_termination_impersonation_block_and_project_duplicate_excludes_deactivated_staff(): void
    {
        Auth::login($this->company);
        Once::flush();
        Event::fake();

        $termination = Termination::create([
            'employee_id' => 2613,
            'notice_date' => now()->toDateString(),
            'termination_date' => now()->toDateString(),
            'reason' => 'Misconduct',
            'status' => 'pending',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);

        $termController = new TerminationController();
        $termController->updateStatus(Request::create('/', 'PUT', ['status' => 'approved']), $termination);

        $termUser = User::find(2613);
        $this->assertEquals(1, $termUser->is_disable);
        $this->assertEquals('terminated', $termUser->deactivation_reason);
        $this->assertEquals('Terminated', $termUser->account_status_label);

        // UserController impersonation must block disabled/terminated users
        $impersonateResponse = app(\App\Http\Controllers\UserController::class)->impersonate($termUser);
        $this->assertEquals(302, $impersonateResponse->getStatusCode());
        $this->assertEquals(2601, Auth::id());

        // Project duplication must copy active teammate (2611) and omit terminated member (2613)
        $sourceProject = Project::create([
            'name' => 'Legacy Core',
            'status' => 'Ongoing',
            'creator_id' => 2601,
            'created_by' => 2601,
        ]);
        ProjectUser::create(['project_id' => $sourceProject->id, 'user_id' => 2611, 'created_by' => 2601]);
        ProjectUser::create(['project_id' => $sourceProject->id, 'user_id' => 2613, 'created_by' => 2601]);

        app(ProjectController::class)->duplicate(Request::create('/', 'POST', ['teamMembers' => true]), $sourceProject);

        $copied = Project::where('name', 'Legacy Core (Copy)')->first();
        $this->assertNotNull($copied);
        $copiedMemberIds = $copied->teamMembers()->pluck('users.id')->all();
        $this->assertContains(2611, $copiedMemberIds);
        $this->assertNotContains(2613, $copiedMemberIds);

        // Reverse termination back to rejected and ensure account recovers cleanly
        $termController->updateStatus(Request::create('/', 'PUT', ['status' => 'rejected']), $termination);
        $termUser->refresh();
        $this->assertEquals(0, $termUser->is_disable);
        $this->assertEquals(1, $termUser->is_enable_login);
        $this->assertEquals('active', Employee::where('user_id', 2613)->first()->employment_status);
    }
}
