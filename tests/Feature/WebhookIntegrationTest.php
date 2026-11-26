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
use Workdo\Webhook\Models\Webhook;
use Workdo\Webhook\Models\WebhookModule;
use Workdo\Webhook\Traits\WebhookControllerHelpers;

class WebhookIntegrationTest extends TestCase
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

        Schema::create('webhook_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('submodule');
            $table->string('type')->default('company');
            $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('method')->default('POST');
            $table->unsignedBigInteger('action')->nullable();
            $table->string('url');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1801,
            'name' => 'Webhook Tenant One',
            'email' => 'webhook1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1801,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1802,
            'name' => 'Webhook Tenant Two',
            'email' => 'webhook2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1802,
        ]);
    }

    public function test_webhook_is_strictly_tenant_isolated_while_modules_remain_global(): void
    {
        $module = WebhookModule::create([
            'module' => 'Sales',
            'submodule' => 'Create Invoice',
            'type' => 'company',
        ]);

        Auth::login($this->company1);
        Once::flush();

        $hook1 = Webhook::create([
            'method' => 'POST',
            'action' => $module->id,
            'url' => 'https://example1.com/hook',
        ]);

        Auth::login($this->company2);
        Once::flush();

        $hook2 = Webhook::create([
            'method' => 'POST',
            'action' => $module->id,
            'url' => 'https://example2.com/hook',
        ]);

        $this->assertEquals([$hook2->id], Webhook::pluck('id')->all());
        $this->assertCount(1, WebhookModule::all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$hook1->id], Webhook::pluck('id')->all());
        $this->assertCount(1, WebhookModule::all());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_webhook(): void
    {
        $helper = new class {
            use WebhookControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $hook2 = Webhook::create([
            'method' => 'GET',
            'action' => null,
            'url' => 'https://tenant2.example/callback',
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($hook2));
    }
}
