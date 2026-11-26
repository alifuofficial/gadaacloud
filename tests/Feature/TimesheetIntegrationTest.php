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
use Workdo\Timesheet\Models\Timesheet;
use Workdo\Timesheet\Traits\TimesheetControllerHelpers;

class TimesheetIntegrationTest extends TestCase
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

        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->date('date');
            $table->integer('hours')->default(0);
            $table->integer('minutes')->default(0);
            $table->text('notes')->nullable();
            $table->string('type')->default('manual');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1301,
            'name' => 'Timesheet Tenant One',
            'email' => 'timesheet1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1301,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1302,
            'name' => 'Timesheet Tenant Two',
            'email' => 'timesheet2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1302,
        ]);
    }

    public function test_timesheets_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();
        $t1 = Timesheet::create(['user_id' => 1301, 'date' => '2026-03-01', 'hours' => 8, 'minutes' => 0, 'type' => 'manual']);

        Auth::login($this->company2);
        Once::flush();
        $t2 = Timesheet::create(['user_id' => 1302, 'date' => '2026-03-01', 'hours' => 6, 'minutes' => 30, 'type' => 'manual']);

        Auth::login($this->company1);
        Once::flush();
        $this->assertContains($t1->id, Timesheet::pluck('id')->toArray());
        $this->assertNotContains($t2->id, Timesheet::pluck('id')->toArray());

        Auth::login($this->company2);
        Once::flush();
        $this->assertContains($t2->id, Timesheet::pluck('id')->toArray());
        $this->assertNotContains($t1->id, Timesheet::pluck('id')->toArray());
    }

    public function test_tenant_exists_and_assert_belongs_to_tenant_block_cross_tenant_access(): void
    {
        $helper = new class {
            use TimesheetControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $emp2 = User::forceCreate([
            'id' => 1303,
            'name' => 'Employee Tenant Two',
            'email' => 'emp1303@example.com',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'created_by' => 1302,
        ]);

        $t2 = Timesheet::create(['user_id' => $emp2->id, 'date' => '2026-03-02', 'hours' => 4, 'minutes' => 0, 'type' => 'manual']);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['user_id' => $emp2->id],
            ['user_id' => ['required', TimesheetControllerHelpers::tenantExists('users', 'id')]]
        );
        $this->assertTrue($validator->fails());
        $this->assertFalse($helper->assertBelongsToTenant($t2));
    }
}
