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
use Workdo\EIMS\Models\EimsAuditLog;
use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsOfflineQueue;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Traits\EimsControllerHelpers;
use Workdo\EIMS\Traits\EimsDateHelpers;

class EimsIntegrationTest extends TestCase
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

        Schema::create('eims_tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('tin', 20);
            $table->string('vat_number', 30)->nullable();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('branch_code', 50)->default('HQ');
            $table->string('device_id', 100)->default('DEVICE-01');
            $table->string('environment', 20)->default('sandbox');
            $table->boolean('offline_resiliency_enabled')->default(true);
            $table->boolean('auto_clear_on_post')->default(true);
            $table->timestamps();
        });

        Schema::create('eims_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->string('invoice_number');
            $table->string('document_type', 20)->default('INV');
            $table->string('buyer_type', 20)->default('B2B');
            $table->string('buyer_name')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('eims_offline_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('eims_invoice_id');
            $table->string('idempotency_key', 100)->unique();
            $table->longText('payload');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(10);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('status', 30)->default('QUEUED');
            $table->timestamps();
        });

        Schema::create('eims_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('eims_invoice_id')->nullable();
            $table->string('action', 50);
            $table->string('previous_hash', 64)->nullable();
            $table->string('payload_hash', 64);
            $table->string('signature', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 2201,
            'name' => 'EIMS Tenant One',
            'email' => 'eims1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2201,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 2202,
            'name' => 'EIMS Tenant Two',
            'email' => 'eims2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2202,
        ]);
    }

    public function test_eims_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $set1 = EimsTenantSetting::create(['tin' => '0011223344', 'legal_name' => 'Co 1 PLC', 'branch_code' => 'BR-01']);
        $inv1 = EimsInvoice::create(['sales_invoice_id' => 1, 'invoice_number' => 'INV-2201', 'total_amount' => 1150]);
        $q1 = EimsOfflineQueue::create([
            'eims_invoice_id' => $inv1->id,
            'idempotency_key' => 'key-2201',
            'payload' => json_encode(['test' => 1]),
            'expires_at' => now()->addHours(72),
        ]);
        $log1 = EimsAuditLog::create([
            'eims_invoice_id' => $inv1->id,
            'action' => 'REGISTER',
            'payload_hash' => hash('sha256', 'p1'),
            'signature' => hash('sha256', 's1'),
            'details' => ['ok' => true],
        ]);

        Auth::login($this->company2);
        Once::flush();

        $set2 = EimsTenantSetting::create(['tin' => '0099887766', 'legal_name' => 'Co 2 PLC', 'branch_code' => 'BR-02']);
        $inv2 = EimsInvoice::create(['sales_invoice_id' => 2, 'invoice_number' => 'INV-2202', 'total_amount' => 2300]);
        $q2 = EimsOfflineQueue::create([
            'eims_invoice_id' => $inv2->id,
            'idempotency_key' => 'key-2202',
            'payload' => json_encode(['test' => 2]),
            'expires_at' => now()->addHours(72),
        ]);
        $log2 = EimsAuditLog::create([
            'eims_invoice_id' => $inv2->id,
            'action' => 'REGISTER',
            'payload_hash' => hash('sha256', 'p2'),
            'signature' => hash('sha256', 's2'),
            'details' => ['ok' => true],
        ]);

        $this->assertEquals([$set2->id], EimsTenantSetting::pluck('id')->all());
        $this->assertEquals([$inv2->id], EimsInvoice::pluck('id')->all());
        $this->assertEquals([$q2->id], EimsOfflineQueue::pluck('id')->all());
        $this->assertEquals([$log2->id], EimsAuditLog::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$set1->id], EimsTenantSetting::pluck('id')->all());
        $this->assertEquals([$inv1->id], EimsInvoice::pluck('id')->all());
        $this->assertEquals([$q1->id], EimsOfflineQueue::pluck('id')->all());
        $this->assertEquals([$log1->id], EimsAuditLog::pluck('id')->all());
    }

    public function test_eims_helpers_block_cross_tenant_and_resolve_kenat(): void
    {
        $helper = new class {
            use EimsControllerHelpers, EimsDateHelpers;
        };

        Auth::login($this->company2);
        Once::flush();
        $inv2 = EimsInvoice::create(['sales_invoice_id' => 9, 'invoice_number' => 'INV-SECRET', 'total_amount' => 900]);

        Auth::login($this->company1);
        Once::flush();
        $this->assertFalse($helper->assertBelongsToTenant($inv2));

        $fy = $helper->resolveEimsDateRange('2017');
        $this->assertStringStartsWith('2024-', $fy['start_date']);
        $this->assertStringStartsWith('2025-', $fy['end_date']);
    }
}
