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
use Workdo\ERPMigrate\Models\MigrationBatch;
use Workdo\ERPMigrate\Models\MigrationConnection;
use Workdo\ERPMigrate\Models\MigrationFieldMapping;
use Workdo\ERPMigrate\Models\MigrationLog;
use Workdo\ERPMigrate\Traits\ERPMigrateControllerHelpers;

class ERPMigrateIntegrationTest extends TestCase
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

        Schema::create('erp_migration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_type');
            $table->string('host');
            $table->string('database_name')->nullable();
            $table->string('username')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('connection_status')->default('untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();
        });

        Schema::create('erp_migration_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->string('source_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->string('original_filename')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->string('status')->default('draft');
            $table->json('field_mappings')->nullable();
            $table->json('validation_summary')->nullable();
            $table->json('created_entity_ids')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('erp_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->integer('row_index')->default(0);
            $table->string('record_identifier')->nullable();
            $table->string('status');
            $table->string('target_model')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('message')->nullable();
            $table->json('raw_data')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('erp_migration_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->string('source_erp');
            $table->string('entity_type');
            $table->json('mappings');
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 2301,
            'name' => 'Migrate Tenant One',
            'email' => 'migrate1@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2301,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 2302,
            'name' => 'Migrate Tenant Two',
            'email' => 'migrate2@example.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 2302,
        ]);
    }

    public function test_erp_migrate_models_are_strictly_tenant_isolated(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $conn1 = MigrationConnection::create([
            'name' => 'Odoo Co 1',
            'source_type' => 'odoo',
            'host' => 'https://odoo1.company.et',
        ]);
        $batch1 = MigrationBatch::create([
            'batch_code' => 'MIG-2301',
            'source_type' => 'csv',
            'entity_type' => 'customers',
            'connection_id' => $conn1->id,
        ]);
        $log1 = MigrationLog::create([
            'batch_id' => $batch1->id,
            'row_index' => 1,
            'status' => 'imported',
        ]);
        $map1 = MigrationFieldMapping::create([
            'template_name' => 'Co1 Map',
            'source_erp' => 'odoo',
            'entity_type' => 'customers',
            'mappings' => ['Name' => 'name'],
        ]);

        Auth::login($this->company2);
        Once::flush();

        $conn2 = MigrationConnection::create([
            'name' => 'Odoo Co 2',
            'source_type' => 'odoo',
            'host' => 'https://odoo2.company.et',
        ]);
        $batch2 = MigrationBatch::create([
            'batch_code' => 'MIG-2302',
            'source_type' => 'csv',
            'entity_type' => 'vendors',
            'connection_id' => $conn2->id,
        ]);
        $log2 = MigrationLog::create([
            'batch_id' => $batch2->id,
            'row_index' => 1,
            'status' => 'imported',
        ]);
        $map2 = MigrationFieldMapping::create([
            'template_name' => 'Co2 Map',
            'source_erp' => 'erpnext',
            'entity_type' => 'vendors',
            'mappings' => ['Supplier' => 'name'],
        ]);

        $this->assertEquals([$conn2->id], MigrationConnection::pluck('id')->all());
        $this->assertEquals([$batch2->id], MigrationBatch::pluck('id')->all());
        $this->assertEquals([$log2->id], MigrationLog::pluck('id')->all());
        $this->assertEquals([$map2->id], MigrationFieldMapping::pluck('id')->all());

        Auth::login($this->company1);
        Once::flush();

        $this->assertEquals([$conn1->id], MigrationConnection::pluck('id')->all());
        $this->assertEquals([$batch1->id], MigrationBatch::pluck('id')->all());
        $this->assertEquals([$log1->id], MigrationLog::pluck('id')->all());
        $this->assertEquals([$map1->id], MigrationFieldMapping::pluck('id')->all());

        $helper = new class {
            use ERPMigrateControllerHelpers;
        };
        $this->assertTrue($helper->assertBelongsToTenant($batch1));
        $this->assertFalse($helper->assertBelongsToTenant($batch2));
    }
}
