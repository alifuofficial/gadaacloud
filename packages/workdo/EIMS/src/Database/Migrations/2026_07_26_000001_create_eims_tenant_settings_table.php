<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eims_tenant_settings')) {
            Schema::create('eims_tenant_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('tin', 10)->nullable();
                $table->string('vat_number', 20)->nullable();
                $table->string('legal_name', 191)->nullable();
                $table->string('trade_name', 191)->nullable();
                $table->string('branch_code', 50)->default('MAIN');
                $table->string('device_id', 100)->nullable();
                $table->enum('environment', ['sandbox', 'production'])->default('sandbox');
                $table->string('api_endpoint', 255)->nullable();
                $table->text('api_key')->nullable();
                $table->text('api_secret')->nullable();
                $table->boolean('offline_resiliency_enabled')->default(true);
                $table->boolean('auto_clear_on_post')->default(true);
                $table->json('custom_fields')->nullable();
                $table->timestamps();

                $table->unique(['created_by', 'branch_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eims_tenant_settings');
    }
};
