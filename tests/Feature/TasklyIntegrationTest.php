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
use Workdo\Taskly\Models\BugStage;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectBug;
use Workdo\Taskly\Models\ProjectTask;
use Workdo\Taskly\Models\TaskStage;

class TasklyIntegrationTest extends TestCase
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

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('project_scope')->default('client');
            $table->decimal('budget', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('Ongoing');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('task_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->string('priority')->default('Medium');
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('bug_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_bugs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->string('priority')->default('High');
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 301,
            'name' => 'Taskly Company 1',
            'email' => 'taskly1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 301,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 302,
            'name' => 'Taskly Company 2',
            'email' => 'taskly2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 302,
        ]);
    }

    public function test_multi_tenant_isolation_across_taskly_entities(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $project = Project::create([
            'name' => 'Addis Ababa Ring Road ERP',
            'project_scope' => 'client',
            'budget' => 1500000,
            'creator_id' => 301,
            'created_by' => 301,
        ]);

        $taskStage = TaskStage::create(['name' => 'In Progress', 'order' => 1, 'creator_id' => 301, 'created_by' => 301]);
        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title' => 'Configure GL Accounts',
            'priority' => 'High',
            'stage_id' => $taskStage->id,
            'creator_id' => 301,
            'created_by' => 301,
        ]);

        $bugStage = BugStage::create(['name' => 'Confirmed', 'order' => 1, 'creator_id' => 301, 'created_by' => 301]);
        $bug = ProjectBug::create([
            'project_id' => $project->id,
            'title' => 'Fix VAT Rounding',
            'priority' => 'High',
            'stage_id' => $bugStage->id,
            'creator_id' => 301,
            'created_by' => 301,
        ]);

        $this->assertCount(1, Project::all());
        $this->assertCount(1, TaskStage::all());
        $this->assertCount(1, ProjectTask::all());
        $this->assertCount(1, BugStage::all());
        $this->assertCount(1, ProjectBug::all());

        // Switch to company 302
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, Project::all());
        $this->assertCount(0, TaskStage::all());
        $this->assertCount(0, ProjectTask::all());
        $this->assertCount(0, BugStage::all());
        $this->assertCount(0, ProjectBug::all());
        $this->assertNull(Project::find($project->id));
        $this->assertNull(ProjectTask::find($task->id));
        $this->assertNull(ProjectBug::find($bug->id));
    }
}
