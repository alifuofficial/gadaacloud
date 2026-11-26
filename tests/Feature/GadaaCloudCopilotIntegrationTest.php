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
use Workdo\GadaaCloudCopilot\Models\CopilotAutomation;
use Workdo\GadaaCloudCopilot\Models\CopilotInsight;
use Workdo\GadaaCloudCopilot\Models\CopilotMemory;
use Workdo\GadaaCloudCopilot\Models\CopilotTokenUsage;
use Workdo\GadaaCloudCopilot\Traits\GadaaCloudCopilotControllerHelpers;

class GadaaCloudCopilotIntegrationTest extends TestCase
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

        Schema::create('copilot_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('type')->default('cashflow');
            $table->string('title');
            $table->text('description');
            $table->json('metrics_data')->nullable();
            $table->float('confidence_score')->default(0.95);
            $table->string('status')->default('unread');
            $table->timestamps();
        });

        Schema::create('copilot_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('name');
            $table->string('trigger_event');
            $table->string('action_type');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('copilot_memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('session_id')->nullable();
            $table->string('role', 20)->default('user');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('copilot_token_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('token_cost', 12, 4)->default(0);
            $table->string('model_name', 100)->default('gemini-1.5-flash');
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1501,
            'name' => 'Copilot Tenant One',
            'email' => 'copilot1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1501,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1502,
            'name' => 'Copilot Tenant Two',
            'email' => 'copilot2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1502,
        ]);
    }

    public function test_copilot_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $insight1 = CopilotInsight::create([
            'type' => 'cashflow',
            'title' => 'Tenant 1 Cashflow Alert',
            'description' => 'Tenant 1 detail',
        ]);
        $auto1 = CopilotAutomation::create([
            'name' => 'Auto 1',
            'trigger_event' => 'invoice_due',
            'action_type' => 'send_email',
        ]);
        $mem1 = CopilotMemory::create([
            'user_id' => 1501,
            'role' => 'user',
            'content' => 'Secret prompt 1',
        ]);
        $usage1 = CopilotTokenUsage::create([
            'company_id' => 1501,
            'user_id' => 1501,
            'total_tokens' => 120,
        ]);

        $this->assertEquals(1501, (int) $insight1->created_by);
        $this->assertEquals(1501, (int) $usage1->created_by);

        Auth::login($this->company2);
        Once::flush();

        $insight2 = CopilotInsight::create([
            'type' => 'tax',
            'title' => 'Tenant 2 Tax Alert',
            'description' => 'Tenant 2 detail',
        ]);
        $auto2 = CopilotAutomation::create([
            'name' => 'Auto 2',
            'trigger_event' => 'tax_period',
            'action_type' => 'create_alert',
        ]);
        $mem2 = CopilotMemory::create([
            'user_id' => 1502,
            'role' => 'user',
            'content' => 'Secret prompt 2',
        ]);
        $usage2 = CopilotTokenUsage::create([
            'company_id' => 1502,
            'user_id' => 1502,
            'total_tokens' => 240,
        ]);

        $this->assertEquals([$insight2->id], CopilotInsight::pluck('id')->all());
        $this->assertEquals([$auto2->id], CopilotAutomation::pluck('id')->all());
        $this->assertEquals([$mem2->id], CopilotMemory::pluck('id')->all());
        $this->assertEquals([$usage2->id], CopilotTokenUsage::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$insight1->id], CopilotInsight::pluck('id')->all());
        $this->assertEquals([$auto1->id], CopilotAutomation::pluck('id')->all());
        $this->assertEquals([$mem1->id], CopilotMemory::pluck('id')->all());
        $this->assertEquals([$usage1->id], CopilotTokenUsage::pluck('id')->all());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_copilot_record(): void
    {
        $helper = new class {
            use GadaaCloudCopilotControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $insight2 = CopilotInsight::create([
            'type' => 'anomaly',
            'title' => 'Company 2 Only',
            'description' => 'Confidential anomaly',
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($insight2));
    }
}
