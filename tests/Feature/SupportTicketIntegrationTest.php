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
use Workdo\SupportTicket\Models\Ticket;
use Workdo\SupportTicket\Models\TicketCategory;
use Workdo\SupportTicket\Traits\SupportTicketControllerHelpers;

class SupportTicketIntegrationTest extends TestCase
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

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#000000');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('account_type')->default('custom');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('category');
            $table->string('subject');
            $table->string('status')->default('In Progress');
            $table->text('description');
            $table->longText('attachments')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 901,
            'name' => 'Support Tenant One',
            'email' => 'support1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 901,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 902,
            'name' => 'Support Tenant Two',
            'email' => 'support2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 902,
        ]);
    }

    public function test_tickets_and_categories_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cat1 = TicketCategory::create([
            'name' => 'Billing 1',
            'color' => '#111111',
            'created_by' => 901,
            'creator_id' => 901,
        ]);

        $ticket1 = Ticket::create([
            'ticket_id' => 'TKT-901',
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'category' => $cat1->id,
            'subject' => 'Tenant 1 Issue',
            'description' => 'Details 1',
            'created_by' => 901,
            'creator_id' => 901,
        ]);

        Auth::login($this->company2);
        Once::flush();

        $cat2 = TicketCategory::create([
            'name' => 'Billing 2',
            'color' => '#222222',
            'created_by' => 902,
            'creator_id' => 902,
        ]);

        $ticket2 = Ticket::create([
            'ticket_id' => 'TKT-902',
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'category' => $cat2->id,
            'subject' => 'Tenant 2 Issue',
            'description' => 'Details 2',
            'created_by' => 902,
            'creator_id' => 902,
        ]);

        Auth::login($this->company1);
        Once::flush();
        $visibleTo1 = Ticket::pluck('id')->toArray();
        $this->assertContains($ticket1->id, $visibleTo1);
        $this->assertNotContains($ticket2->id, $visibleTo1);

        Auth::login($this->company2);
        Once::flush();
        $visibleTo2 = Ticket::pluck('id')->toArray();
        $this->assertContains($ticket2->id, $visibleTo2);
        $this->assertNotContains($ticket1->id, $visibleTo2);
    }

    public function test_tenant_exists_blocks_cross_tenant_ticket_category(): void
    {
        Auth::login($this->company2);
        Once::flush();

        $cat2 = TicketCategory::create([
            'name' => 'Private Category',
            'color' => '#333333',
            'created_by' => 902,
            'creator_id' => 902,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $validator = Validator::make(
            ['category' => $cat2->id],
            ['category' => ['required', SupportTicketControllerHelpers::tenantExists('ticket_categories', 'id')]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_assert_belongs_to_tenant_blocks_cross_tenant_ticket_access(): void
    {
        $helper = new class {
            use SupportTicketControllerHelpers;
        };

        Auth::login($this->company2);
        Once::flush();

        $ticket2 = Ticket::create([
            'ticket_id' => 'TKT-902B',
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'category' => 1,
            'subject' => 'Secret Ticket',
            'description' => 'Secret',
            'created_by' => 902,
            'creator_id' => 902,
        ]);

        Auth::login($this->company1);
        Once::flush();

        $this->assertFalse($helper->assertBelongsToTenant($ticket2));
    }
}
