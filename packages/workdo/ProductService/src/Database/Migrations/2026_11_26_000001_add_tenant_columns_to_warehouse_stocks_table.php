<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_stocks')) {
            Schema::table('warehouse_stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouse_stocks', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->nullable();
                }
                if (!Schema::hasColumn('warehouse_stocks', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });

            // Backfill created_by from parent warehouses table
            if (Schema::hasTable('warehouses') && Schema::hasColumn('warehouse_stocks', 'created_by')) {
                $stocks = DB::table('warehouse_stocks')
                    ->whereNull('created_by')
                    ->get(['id', 'warehouse_id']);

                foreach ($stocks as $stock) {
                    $companyId = DB::table('warehouses')->where('id', $stock->warehouse_id)->value('created_by');
                    if ($companyId) {
                        DB::table('warehouse_stocks')
                            ->where('id', $stock->id)
                            ->update(['created_by' => $companyId, 'creator_id' => $companyId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warehouse_stocks')) {
            Schema::table('warehouse_stocks', function (Blueprint $table) {
                $cols = array_filter(['creator_id', 'created_by'], fn ($c) => Schema::hasColumn('warehouse_stocks', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
