<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Saved ERP API Connections (Odoo, ERPNext)
        if (!Schema::hasTable('erp_migration_connections')) {
            Schema::create('erp_migration_connections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('source_type'); // 'odoo', 'erpnext'
                $table->string('host'); // e.g. https://myerp.com
                $table->string('database_name')->nullable(); // Odoo DB
                $table->string('username')->nullable(); // Odoo login / ERPNext user
                $table->text('api_key')->nullable(); // Odoo API key or ERPNext API Key
                $table->text('api_secret')->nullable(); // ERPNext API Secret
                $table->string('connection_status')->default('untested'); // 'untested', 'connected', 'failed'
                $table->timestamp('last_tested_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'source_type']);
            });
        }

        // 2. Migration Execution Batches
        if (!Schema::hasTable('erp_migration_batches')) {
            Schema::create('erp_migration_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_code')->unique();
                $table->string('source_type'); // 'odoo_api', 'odoo_csv', 'erpnext_api', 'erpnext_csv', 'universal_csv', 'universal_excel'
                $table->string('entity_type'); // 'chart_of_accounts', 'customers', 'vendors', 'products', 'employees', 'opening_balances'
                $table->unsignedBigInteger('connection_id')->nullable();
                $table->string('original_filename')->nullable();
                $table->integer('total_records')->default(0);
                $table->integer('imported_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->integer('skipped_count')->default(0);
                $table->string('status')->default('draft'); // 'draft', 'validating', 'ready', 'importing', 'completed', 'failed', 'rolled_back'
                $table->json('field_mappings')->nullable();
                $table->json('validation_summary')->nullable();
                $table->json('created_entity_ids')->nullable(); // For 1-click Rollback
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'entity_type']);
                $table->index('batch_code');
            });
        }

        // 3. Granular Row-by-Row Migration Logs
        if (!Schema::hasTable('erp_migration_logs')) {
            Schema::create('erp_migration_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->integer('row_index')->default(0);
                $table->string('record_identifier')->nullable();
                $table->string('status')->default('success'); // 'success', 'warning', 'error', 'skipped'
                $table->string('target_model')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->text('message')->nullable();
                $table->json('raw_data')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['batch_id', 'status']);
                $table->index('created_by');
            });
        }

        // 4. Saved Field Mapping Templates
        if (!Schema::hasTable('erp_migration_field_mappings')) {
            Schema::create('erp_migration_field_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('template_name');
                $table->string('source_erp'); // 'odoo', 'erpnext', 'custom'
                $table->string('entity_type'); // 'chart_of_accounts', 'customers', 'vendors', 'products', 'employees'
                $table->json('mappings');
                $table->boolean('is_system')->default(false);
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['created_by', 'entity_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_migration_logs');
        Schema::dropIfExists('erp_migration_batches');
        Schema::dropIfExists('erp_migration_connections');
        Schema::dropIfExists('erp_migration_field_mappings');
    }
};
