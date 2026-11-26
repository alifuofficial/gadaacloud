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
use Workdo\Chatter\Models\ChatterActivity;
use Workdo\Chatter\Models\ChatterAttachment;
use Workdo\Chatter\Models\ChatterFollower;
use Workdo\Chatter\Models\ChatterMessage;
use Workdo\Chatter\Traits\ChatterControllerHelpers;

class ChatterIntegrationTest extends TestCase
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

        Schema::create('chatter_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->string('type', 20)->default('note');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('chatter_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('chatter_message_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->nullable();
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('chatter_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('activity_type', 50)->default('todo');
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chatter_followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1901,
            'name' => 'Chatter Tenant One',
            'email' => 'chatter1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1901,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1902,
            'name' => 'Chatter Tenant Two',
            'email' => 'chatter2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1902,
        ]);
    }

    public function test_chatter_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $msg1 = ChatterMessage::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1901,
            'message' => 'Tenant 1 note',
        ]);
        $att1 = ChatterAttachment::create([
            'chatter_message_id' => $msg1->id,
            'file_name' => 'doc1.pdf',
            'file_path' => 'chatter/doc1.pdf',
        ]);
        $act1 = ChatterActivity::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1901,
            'title' => 'Call Tenant 1 Client',
        ]);
        $fol1 = ChatterFollower::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1901,
        ]);

        $this->assertEquals(1901, (int) $att1->created_by);

        Auth::login($this->company2);
        Once::flush();

        $msg2 = ChatterMessage::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1902,
            'message' => 'Tenant 2 note',
        ]);
        $att2 = ChatterAttachment::create([
            'chatter_message_id' => $msg2->id,
            'file_name' => 'doc2.pdf',
            'file_path' => 'chatter/doc2.pdf',
        ]);
        $act2 = ChatterActivity::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1902,
            'title' => 'Call Tenant 2 Client',
        ]);
        $fol2 = ChatterFollower::create([
            'model_type' => 'Lead',
            'model_id' => 1,
            'user_id' => 1902,
        ]);

        $this->assertEquals([$msg2->id], ChatterMessage::pluck('id')->all());
        $this->assertEquals([$att2->id], ChatterAttachment::pluck('id')->all());
        $this->assertEquals([$act2->id], ChatterActivity::pluck('id')->all());
        $this->assertEquals([$fol2->id], ChatterFollower::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$msg1->id], ChatterMessage::pluck('id')->all());
        $this->assertEquals([$att1->id], ChatterAttachment::pluck('id')->all());
        $this->assertEquals([$act1->id], ChatterActivity::pluck('id')->all());
        $this->assertEquals([$fol1->id], ChatterFollower::pluck('id')->all());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_chatter_attachment(): void
    {
        $helper = new class {
            use ChatterControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $att2 = ChatterAttachment::create([
            'chatter_message_id' => 99,
            'file_name' => 'secret.pdf',
            'file_path' => 'chatter/secret.pdf',
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($att2));
    }
}
