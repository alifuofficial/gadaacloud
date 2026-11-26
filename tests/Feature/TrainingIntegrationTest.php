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
use Workdo\Training\Models\Trainer;
use Workdo\Training\Models\Training;
use Workdo\Training\Models\TrainingType;
use Workdo\Training\Traits\TrainingControllerHelpers;

class TrainingIntegrationTest extends TestCase
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

        Schema::create('training_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact');
            $table->string('email');
            $table->string('experience')->default('5 years');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->text('expertise')->nullable();
            $table->text('qualification')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('training_type_id');
            $table->unsignedBigInteger('trainer_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('location')->nullable();
            $table->integer('max_participants')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('status')->default('scheduled');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1001,
            'name' => 'Training Tenant One',
            'email' => 'training1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1001,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1002,
            'name' => 'Training Tenant Two',
            'email' => 'training2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1002,
        ]);
    }

    public function test_training_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $type1 = TrainingType::create(['name' => 'Safety 1', 'created_by' => 1001, 'creator_id' => 1001]);
        $trainer1 = Trainer::create(['name' => 'Abebe', 'contact' => '0911000001', 'email' => 'abebe@t1.com', 'created_by' => 1001, 'creator_id' => 1001]);
        $training1 = Training::create([
            'title' => 'Safety Workshop 1',
            'training_type_id' => $type1->id,
            'trainer_id' => $trainer1->id,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
            'created_by' => 1001,
            'creator_id' => 1001,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $type2 = TrainingType::create(['name' => 'Safety 2', 'created_by' => 1002, 'creator_id' => 1002]);
        $trainer2 = Trainer::create(['name' => 'Kebede', 'contact' => '0911000002', 'email' => 'kebede@t2.com', 'created_by' => 1002, 'creator_id' => 1002]);
        $training2 = Training::create([
            'title' => 'Safety Workshop 2',
            'training_type_id' => $type2->id,
            'trainer_id' => $trainer2->id,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
            'created_by' => 1002,
            'creator_id' => 1002,
        ]);

        Auth::login($this->company1);
        Once::flush();
        $visible1 = Training::pluck('id')->toArray();
        $this->assertContains($training1->id, $visible1);
        $this->assertNotContains($training2->id, $visible1);

        Auth::login($this->company2);
        Once::flush();
        $visible2 = Training::pluck('id')->toArray();
        $this->assertContains($training2->id, $visible2);
        $this->assertNotContains($training1->id, $visible2);
    }

    public function test_tenant_exists_blocks_cross_tenant_trainer_and_type(): void
    {
        Auth::login($this->company2);
        Once::flush();

        $trainer2 = Trainer::create([
            'name' => 'Secret Trainer',
            'contact' => '0911999999',
            'email' => 'secret@t2.com',
            'created_by' => 1002,
            'creator_id' => 1002,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['trainer_id' => $trainer2->id],
            ['trainer_id' => ['required', TrainingControllerHelpers::tenantExists('trainers', 'id')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_training_access(): void
    {
        $helper = new class {
            use TrainingControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $training2 = Training::create([
            'title' => 'Internal Training',
            'training_type_id' => 1,
            'trainer_id' => 1,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-02',
            'created_by' => 1002,
            'creator_id' => 1002,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($training2));
    }
}
