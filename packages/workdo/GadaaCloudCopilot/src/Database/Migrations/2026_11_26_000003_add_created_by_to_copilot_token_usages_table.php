<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('copilot_token_usages')) {
            Schema::table('copilot_token_usages', function (Blueprint $table) {
                if (!Schema::hasColumn('copilot_token_usages', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });

            if (Schema::hasColumn('copilot_token_usages', 'created_by') && Schema::hasColumn('copilot_token_usages', 'company_id')) {
                DB::table('copilot_token_usages')
                    ->whereNull('created_by')
                    ->update(['created_by' => DB::raw('company_id')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('copilot_token_usages') && Schema::hasColumn('copilot_token_usages', 'created_by')) {
            Schema::table('copilot_token_usages', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
