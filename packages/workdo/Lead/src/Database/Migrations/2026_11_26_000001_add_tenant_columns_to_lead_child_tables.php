<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dealChildren = [
            'client_deals',
            'deal_activity_logs',
            'deal_calls',
            'deal_emails',
            'deal_files',
            'user_deals',
        ];

        $leadChildren = [
            'lead_activity_logs',
            'lead_calls',
            'lead_emails',
            'lead_files',
            'user_leads',
        ];

        foreach (array_merge($dealChildren, $leadChildren) as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                });
            }
        }

        foreach ($dealChildren as $child) {
            if (Schema::hasTable($child) && Schema::hasTable('deals') && Schema::hasColumn($child, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT deals.created_by FROM deals WHERE deals.id = {$child}.deal_id LIMIT 1) WHERE created_by IS NULL");
            }
        }

        foreach ($leadChildren as $child) {
            if (Schema::hasTable($child) && Schema::hasTable('leads') && Schema::hasColumn($child, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT leads.created_by FROM leads WHERE leads.id = {$child}.lead_id LIMIT 1) WHERE created_by IS NULL");
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'client_deals',
            'deal_activity_logs',
            'deal_calls',
            'deal_emails',
            'deal_files',
            'user_deals',
            'lead_activity_logs',
            'lead_calls',
            'lead_emails',
            'lead_files',
            'user_leads',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('created_by');
                });
            }
        }
    }
};
