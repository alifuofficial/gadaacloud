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
use Workdo\ZoomMeeting\Models\ZoomMeeting;
use Workdo\ZoomMeeting\Traits\ZoomMeetingControllerHelpers;

class ZoomMeetingIntegrationTest extends TestCase
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

        Schema::create('zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('meeting_password')->nullable();
            $table->text('start_url')->nullable();
            $table->text('join_url')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->boolean('host_video')->default(false);
            $table->boolean('participant_video')->default(false);
            $table->boolean('waiting_room')->default(false);
            $table->boolean('recording')->default(false);
            $table->string('status')->default('Scheduled');
            $table->json('participants')->nullable();
            $table->unsignedBigInteger('host_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 1701,
            'name' => 'Zoom Tenant One',
            'email' => 'zoom1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1701,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1702,
            'name' => 'Zoom Tenant Two',
            'email' => 'zoom2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1702,
        ]);
    }

    public function test_zoom_meeting_is_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $meeting1 = ZoomMeeting::create([
            'title' => 'Tenant 1 Board Sync',
            'start_time' => '2026-04-01 10:00:00',
            'duration' => 60,
            'status' => 'Scheduled',
            'host_id' => 1701,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $meeting2 = ZoomMeeting::create([
            'title' => 'Tenant 2 Standup',
            'start_time' => '2026-04-01 11:00:00',
            'duration' => 30,
            'status' => 'Scheduled',
            'host_id' => 1702,
        ]);

        $this->assertEquals([$meeting2->id], ZoomMeeting::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$meeting1->id], ZoomMeeting::pluck('id')->all());
    }

    public function test_tenant_exists_blocks_cross_tenant_host_user(): void
    {
        $staff2 = User::forceCreate([
            'id' => 1703,
            'name' => 'Staff From Tenant 2',
            'email' => 'staff2@example.com',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'created_by' => 1702,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['host_id' => $staff2->id],
            ['host_id' => ['required', ZoomMeetingControllerHelpers::tenantExists('users', 'id')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_zoom_meeting(): void
    {
        $helper = new class {
            use ZoomMeetingControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $meeting2 = ZoomMeeting::create([
            'title' => 'Confidential Review',
            'start_time' => '2026-04-02 09:00:00',
            'duration' => 45,
            'status' => 'Scheduled',
            'host_id' => 1702,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($meeting2));
    }
}
