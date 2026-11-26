<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'sales_invoice_items',
            'sales_invoice_item_taxes',
            'purchase_invoice_items',
            'purchase_invoice_item_taxes',
            'sales_proposal_items',
            'sales_proposal_item_taxes',
            'sales_invoice_return_items',
            'sales_invoice_return_item_taxes',
            'purchase_return_items',
            'purchase_return_item_taxes',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                });
            }
        }

        // Backfill created_by from parent document / line-item headers safely
        $parentBackfills = [
            ['sales_invoice_items', 'sales_invoices', 'invoice_id'],
            ['purchase_invoice_items', 'purchase_invoices', 'invoice_id'],
            ['sales_proposal_items', 'sales_proposals', 'proposal_id'],
            ['sales_invoice_return_items', 'sales_invoice_returns', 'return_id'],
            ['purchase_return_items', 'purchase_returns', 'return_id'],
            ['sales_invoice_item_taxes', 'sales_invoice_items', 'item_id'],
            ['purchase_invoice_item_taxes', 'purchase_invoice_items', 'item_id'],
            ['sales_proposal_item_taxes', 'sales_proposal_items', 'item_id'],
            ['sales_invoice_return_item_taxes', 'sales_invoice_return_items', 'item_id'],
            ['purchase_return_item_taxes', 'purchase_return_items', 'item_id'],
        ];

        foreach ($parentBackfills as [$child, $parent, $fk]) {
            if (Schema::hasTable($child) && Schema::hasTable($parent) && Schema::hasColumn($child, 'created_by') && Schema::hasColumn($parent, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT {$parent}.created_by FROM {$parent} WHERE {$parent}.id = {$child}.{$fk} LIMIT 1) WHERE created_by IS NULL");
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'purchase_return_item_taxes',
            'purchase_return_items',
            'sales_invoice_return_item_taxes',
            'sales_invoice_return_items',
            'sales_proposal_item_taxes',
            'sales_proposal_items',
            'purchase_invoice_item_taxes',
            'purchase_invoice_items',
            'sales_invoice_item_taxes',
            'sales_invoice_items',
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
