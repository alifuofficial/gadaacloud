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
        // 1. Optimize ch_messages table
        if (Schema::hasTable('ch_messages')) {
            try {
                Schema::table('ch_messages', function (Blueprint $table) {
                    $table->index(['to_id', 'created_at'], 'idx_ch_messages_to_created');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('ch_messages', function (Blueprint $table) {
                    $table->index(['from_id', 'to_id'], 'idx_ch_messages_from_to');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('ch_messages', function (Blueprint $table) {
                    $table->index(['to_id', 'seen'], 'idx_ch_messages_to_seen');
                });
            } catch (\Throwable $e) {}
        }

        // 2. Optimize settings table
        if (Schema::hasTable('settings')) {
            try {
                Schema::table('settings', function (Blueprint $table) {
                    $table->index(['created_by', 'key'], 'idx_settings_created_by_key');
                });
            } catch (\Throwable $e) {}
        }

        // 3. Optimize sales_invoices table
        if (Schema::hasTable('sales_invoices')) {
            try {
                Schema::table('sales_invoices', function (Blueprint $table) {
                    $table->index(['created_by', 'invoice_number'], 'idx_sales_inv_created_inv_num');
                });
            } catch (\Throwable $e) {}
        }

        // 4. Optimize purchase_invoices table
        if (Schema::hasTable('purchase_invoices')) {
            try {
                Schema::table('purchase_invoices', function (Blueprint $table) {
                    $table->index(['created_by', 'invoice_number'], 'idx_purch_inv_created_inv_num');
                });
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ch_messages')) {
            try {
                Schema::table('ch_messages', function (Blueprint $table) {
                    $table->dropIndex('idx_ch_messages_to_created');
                    $table->dropIndex('idx_ch_messages_from_to');
                    $table->dropIndex('idx_ch_messages_to_seen');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('settings')) {
            try {
                Schema::table('settings', function (Blueprint $table) {
                    $table->dropIndex('idx_settings_created_by_key');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('sales_invoices')) {
            try {
                Schema::table('sales_invoices', function (Blueprint $table) {
                    $table->dropIndex('idx_sales_inv_created_inv_num');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('purchase_invoices')) {
            try {
                Schema::table('purchase_invoices', function (Blueprint $table) {
                    $table->dropIndex('idx_purch_inv_created_inv_num');
                });
            } catch (\Throwable $e) {}
        }
    }
};
