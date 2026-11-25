<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_quotation_items')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_quotation_items', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->nullable()->index()->after('total_amount');
                }
                if (!Schema::hasColumn('sales_quotation_items', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('creator_id');
                }
            });
        }

        if (Schema::hasTable('sales_quotation_item_taxes')) {
            Schema::table('sales_quotation_item_taxes', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_quotation_item_taxes', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->nullable()->index()->after('tax_rate');
                }
                if (!Schema::hasColumn('sales_quotation_item_taxes', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('creator_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_quotation_items')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_quotation_items', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('sales_quotation_items', 'creator_id')) {
                    $table->dropColumn('creator_id');
                }
            });
        }

        if (Schema::hasTable('sales_quotation_item_taxes')) {
            Schema::table('sales_quotation_item_taxes', function (Blueprint $table) {
                if (Schema::hasColumn('sales_quotation_item_taxes', 'created_by')) {
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('sales_quotation_item_taxes', 'creator_id')) {
                    $table->dropColumn('creator_id');
                }
            });
        }
    }
};
