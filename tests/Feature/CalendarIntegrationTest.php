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
use Workdo\Calendar\Models\GoogleCalendarSetting;
use Workdo\Calendar\Traits\CalendarControllerHelpers;

class CalendarIntegrationTest extends TestCase
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

        Schema::create('google_calendar_settings', function (Blueprint $table) {
            $table->id();
            $table->longText('service_account_json')->nullable();
            $table->string('calendar_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 2001,
            'name' => 'Calendar Tenant One',
            'email' => 'cal1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2001,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 2002,
            'name' => 'Calendar Tenant Two',
            'email' => 'cal2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2002,
        ]);
    }

    public function test_google_calendar_setting_is_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cal1 = GoogleCalendarSetting::create([
            'service_account_json' => '{"project_id":"t1"}',
            'calendar_id' => 'cal-tenant-1',
        ]);

        Auth::login($this->company2);
        Once::flush();

        $cal2 = GoogleCalendarSetting::create([
            'service_account_json' => '{"project_id":"t2"}',
            'calendar_id' => 'cal-tenant-2',
        ]);

        $this->assertEquals([$cal2->id], GoogleCalendarSetting::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$cal1->id], GoogleCalendarSetting::pluck('id')->all());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_calendar_setting(): void
    {
        $helper = new class {
            use CalendarControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $cal2 = GoogleCalendarSetting::create([
            'service_account_json' => '{"project_id":"secret"}',
            'calendar_id' => 'secret-cal',
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($cal2));
    }
}
