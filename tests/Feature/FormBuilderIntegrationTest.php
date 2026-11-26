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
use Workdo\FormBuilder\Models\Form;
use Workdo\FormBuilder\Models\FormField;
use Workdo\FormBuilder\Models\FormResponse;
use Workdo\FormBuilder\Traits\FormBuilderControllerHelpers;

class FormBuilderIntegrationTest extends TestCase
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

        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('default_layout')->default('single');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('label');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->json('response_data');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1601,
            'name' => 'Form Tenant One',
            'email' => 'form1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1601,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1602,
            'name' => 'Form Tenant Two',
            'email' => 'form2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1602,
        ]);
    }

    public function test_form_builder_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $form1 = Form::create(['name' => 'Lead Intake 1', 'code' => 'FRM-1601', 'is_active' => true]);
        $field1 = FormField::create(['form_id' => $form1->id, 'label' => 'Full Name', 'type' => 'text', 'order' => 1]);
        $resp1 = FormResponse::create(['form_id' => $form1->id, 'response_data' => ['Full Name' => 'Abebe']]);

        Auth::login($this->company2);
        Once::flush();

        $form2 = Form::create(['name' => 'Lead Intake 2', 'code' => 'FRM-1602', 'is_active' => true]);
        $field2 = FormField::create(['form_id' => $form2->id, 'label' => 'Company', 'type' => 'text', 'order' => 1]);
        $resp2 = FormResponse::create(['form_id' => $form2->id, 'response_data' => ['Company' => 'Tenant Two']]);

        $this->assertEquals([$form2->id], Form::pluck('id')->all());
        $this->assertEquals([$field2->id], FormField::pluck('id')->all());
        $this->assertEquals([$resp2->id], FormResponse::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$form1->id], Form::pluck('id')->all());
        $this->assertEquals([$field1->id], FormField::pluck('id')->all());
        $this->assertEquals([$resp1->id], FormResponse::pluck('id')->all());
    }

    public function test_tenant_exists_and_assert_belongs_to_tenant_block_cross_tenant_forms(): void
    {
        $helper = new class {
            use FormBuilderControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $form2 = Form::create(['name' => 'Private Form', 'code' => 'FRM-PRIV', 'is_active' => true]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['form_id' => $form2->id],
            ['form_id' => ['required', FormBuilderControllerHelpers::tenantExists('forms', 'id')]]
        );

        $this->assertTrue($validator->fails());
        $this->assertFalse($helper->assertBelongsToTenant($form2));
    }
}
