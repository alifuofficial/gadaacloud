<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_service_items')) {
            return;
        }

        Schema::table('product_service_items', function (Blueprint $table) {
            if (!Schema::hasColumn('product_service_items', 'barcode')) {
                $table->string('barcode', 100)->nullable()->index()->after('sku');
            }
            if (!Schema::hasColumn('product_service_items', 'hs_code')) {
                $table->string('hs_code', 30)->nullable()->index()->after('barcode');
            }
            if (!Schema::hasColumn('product_service_items', 'tax_classification')) {
                $table->string('tax_classification', 20)->default('VAT15')->after('tax_ids');
            }
            if (!Schema::hasColumn('product_service_items', 'excise_rate')) {
                $table->decimal('excise_rate', 6, 2)->default(0)->after('tax_classification');
            }
            if (!Schema::hasColumn('product_service_items', 'is_tax_inclusive')) {
                $table->boolean('is_tax_inclusive')->default(false)->after('excise_rate');
            }
            if (!Schema::hasColumn('product_service_items', 'sale_chartaccount_id')) {
                $table->unsignedBigInteger('sale_chartaccount_id')->nullable()->index()->after('purchase_price');
            }
            if (!Schema::hasColumn('product_service_items', 'expense_chartaccount_id')) {
                $table->unsignedBigInteger('expense_chartaccount_id')->nullable()->index()->after('sale_chartaccount_id');
            }
            if (!Schema::hasColumn('product_service_items', 'inventory_chartaccount_id')) {
                $table->unsignedBigInteger('inventory_chartaccount_id')->nullable()->index()->after('expense_chartaccount_id');
            }
            if (!Schema::hasColumn('product_service_items', 'purchase_unit_id')) {
                $table->unsignedBigInteger('purchase_unit_id')->nullable()->after('unit');
            }
            if (!Schema::hasColumn('product_service_items', 'unit_conversion_factor')) {
                $table->decimal('unit_conversion_factor', 12, 4)->default(1)->after('purchase_unit_id');
            }
            if (!Schema::hasColumn('product_service_items', 'reorder_level')) {
                $table->decimal('reorder_level', 15, 4)->default(0)->after('unit_conversion_factor');
            }
            if (!Schema::hasColumn('product_service_items', 'min_sale_price')) {
                $table->decimal('min_sale_price', 15, 2)->nullable()->after('sale_price');
            }
            if (!Schema::hasColumn('product_service_items', 'wholesale_price')) {
                $table->decimal('wholesale_price', 15, 2)->nullable()->after('min_sale_price');
            }
            if (!Schema::hasColumn('product_service_items', 'wholesale_min_qty')) {
                $table->decimal('wholesale_min_qty', 15, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('product_service_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_service_items')) {
            return;
        }

        Schema::table('product_service_items', function (Blueprint $table) {
            $cols = [
                'barcode',
                'hs_code',
                'tax_classification',
                'excise_rate',
                'is_tax_inclusive',
                'sale_chartaccount_id',
                'expense_chartaccount_id',
                'inventory_chartaccount_id',
                'purchase_unit_id',
                'unit_conversion_factor',
                'reorder_level',
                'min_sale_price',
                'wholesale_price',
                'wholesale_min_qty',
                'deleted_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('product_service_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
