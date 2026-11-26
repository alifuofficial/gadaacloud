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
use Workdo\GadaacloudStudio\Models\Workflow;
use Workdo\GadaacloudStudio\Models\WorkflowLog;
use Workdo\GadaacloudStudio\Models\WorkflowRequest;
use Workdo\GadaacloudStudio\Models\WorkflowStep;
use Workdo\GadaacloudStudio\Traits\GadaacloudStudioControllerHelpers;

class GadaacloudStudioIntegrationTest extends TestCase
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

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('target_model');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('name');
            $table->string('approver_type');
            $table->string('approver_role')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->integer('sequence')->default(1);
            $table->string('condition_field')->nullable();
            $table->string('condition_operator')->nullable();
            $table->string('condition_value')->nullable();
            $table->boolean('skip_if_condition_fails')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedBigInteger('delegated_to_user_id')->nullable();
            $table->text('submitter_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_request_id');
            $table->unsignedBigInteger('workflow_step_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1401,
            'name' => 'Studio Tenant One',
            'email' => 'studio1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1401,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1402,
            'name' => 'Studio Tenant Two',
            'email' => 'studio2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1402,
        ]);
    }

    public function test_studio_workflows_steps_and_logs_are_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();
        $wf1 = Workflow::create(['name' => 'PO Approval 1', 'target_model' => 'App\\Models\\PurchaseInvoice']);
        $step1 = WorkflowStep::create(['workflow_id' => $wf1->id, 'name' => 'CFO', 'approver_type' => 'user', 'approver_user_id' => 1401, 'sequence' => 1]);
        $req1 = WorkflowRequest::create(['workflow_id' => $wf1->id, 'model_id' => 10, 'current_step_id' => $step1->id]);
        $log1 = WorkflowLog::create(['workflow_request_id' => $req1->id, 'workflow_step_id' => $step1->id, 'user_id' => 1401, 'action' => 'submitted']);

        Auth::login($this->company2);
        Once::flush();
        $wf2 = Workflow::create(['name' => 'PO Approval 2', 'target_model' => 'App\\Models\\PurchaseInvoice']);
        $step2 = WorkflowStep::create(['workflow_id' => $wf2->id, 'name' => 'CEO', 'approver_type' => 'user', 'approver_user_id' => 1402, 'sequence' => 1]);
        $req2 = WorkflowRequest::create(['workflow_id' => $wf2->id, 'model_id' => 20, 'current_step_id' => $step2->id]);
        $log2 = WorkflowLog::create(['workflow_request_id' => $req2->id, 'workflow_step_id' => $step2->id, 'user_id' => 1402, 'action' => 'submitted']);

        Auth::login($this->company1);
        Once::flush();
        $this->assertContains($wf1->id, Workflow::pluck('id')->toArray());
        $this->assertNotContains($wf2->id, Workflow::pluck('id')->toArray());
        $this->assertContains($step1->id, WorkflowStep::pluck('id')->toArray());
        $this->assertNotContains($step2->id, WorkflowStep::pluck('id')->toArray());
        $this->assertContains($log1->id, WorkflowLog::pluck('id')->toArray());
        $this->assertNotContains($log2->id, WorkflowLog::pluck('id')->toArray());

        $helper = new class {
            use GadaacloudStudioControllerHelpers;
        };
        $this->assertFalse($helper->assertBelongsToTenant($wf2));
    }
}
