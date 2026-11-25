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
use Workdo\Hrm\Models\Branch;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Designation;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\LeaveApplication;
use Workdo\Hrm\Models\LeaveType;
use Workdo\Hrm\Models\Payroll;
use Workdo\Hrm\Models\Shift;

class HrmIntegrationTest extends TestCase
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

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('designation_name');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_name');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days_per_year')->default(20);
            $table->unsignedBigInteger('creator_id')->nullable();
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
            $table->unsignedBigInteger('creator_id')->nullable();
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
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 201,
            'name' => 'HRM Company 1',
            'email' => 'hrm1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 201,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 202,
            'name' => 'HRM Company 2',
            'email' => 'hrm2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 202,
        ]);
    }

    public function test_multi_tenant_isolation_across_hrm_entities(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $branch = Branch::create(['branch_name' => 'Bole HQ', 'creator_id' => 201, 'created_by' => 201]);
        $dept = Department::create(['department_name' => 'Engineering', 'branch_id' => $branch->id, 'creator_id' => 201, 'created_by' => 201]);
        $desig = Designation::create(['designation_name' => 'Lead Dev', 'branch_id' => $branch->id, 'department_id' => $dept->id, 'creator_id' => 201, 'created_by' => 201]);
        $shift = Shift::create(['shift_name' => 'Day Shift', 'creator_id' => 201, 'created_by' => 201]);

        $empUser = User::forceCreate([
            'id' => 210,
            'name' => 'Abebe Bikila',
            'email' => 'abebe@hrm1.et',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'created_by' => 201,
        ]);

        $employee = Employee::create([
            'employee_id' => 'EMP-201',
            'user_id' => $empUser->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'basic_salary' => 25000,
            'creator_id' => 201,
            'created_by' => 201,
        ]);

        $leaveType = LeaveType::create(['name' => 'Annual Leave', 'max_days_per_year' => 20, 'creator_id' => 201, 'created_by' => 201]);
        $leave = LeaveApplication::create([
            'employee_id' => $empUser->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-26',
            'end_date' => '2026-11-27',
            'total_days' => 2,
            'status' => 'pending',
            'creator_id' => 201,
            'created_by' => 201,
        ]);

        $payroll = Payroll::create([
            'title' => 'Hidar 2019 Payroll',
            'pay_period_start' => '2026-11-10',
            'pay_period_end' => '2026-12-09',
            'pay_date' => '2026-12-09',
            'creator_id' => 201,
            'created_by' => 201,
        ]);

        $this->assertCount(1, Branch::all());
        $this->assertCount(1, Department::all());
        $this->assertCount(1, Designation::all());
        $this->assertCount(1, Shift::all());
        $this->assertCount(1, Employee::all());
        $this->assertCount(1, LeaveApplication::all());
        $this->assertCount(1, Payroll::all());

        // Switch to company 202
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, Branch::all());
        $this->assertCount(0, Department::all());
        $this->assertCount(0, Designation::all());
        $this->assertCount(0, Shift::all());
        $this->assertCount(0, Employee::all());
        $this->assertCount(0, LeaveApplication::all());
        $this->assertCount(0, Payroll::all());
        $this->assertNull(Employee::find($employee->id));
        $this->assertNull(Payroll::find($payroll->id));
    }
}
