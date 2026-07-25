<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('copilot_insights')) {
            Schema::create('copilot_insights', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by');
                $table->string('type')->default('cashflow'); // cashflow, tax, operation, anomaly
                $table->string('title');
                $table->text('description');
                $table->json('metrics_data')->nullable();
                $table->float('confidence_score')->default(0.95);
                $table->string('status')->default('unread'); // unread, dismissed, applied
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('copilot_automations')) {
            Schema::create('copilot_automations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by');
                $table->string('name');
                $table->string('trigger_event'); // invoice_due, low_stock, tax_period, payroll_due
                $table->string('action_type'); // send_email, auto_post, create_alert, reorder_stock
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_insights');
        Schema::dropIfExists('copilot_automations');
    }
};
