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
        // 1. Fix trade_lcs table (Rename existing created_by to creator_id first, then rename user_id to created_by)
        Schema::table('trade_lcs', function (Blueprint $table) {
            $table->renameColumn('created_by', 'creator_id');
        });
        Schema::table('trade_lcs', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });

        // 2. Fix trade_shipments table
        Schema::table('trade_shipments', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
            $table->unsignedBigInteger('creator_id')->nullable()->after('created_by');
        });

        // 3. Fix trade_landed_costs table
        Schema::table('trade_landed_costs', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
            $table->unsignedBigInteger('creator_id')->nullable()->after('created_by');
        });

        // 4. Fix inventory_adjustments table
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('adjusted_by', 'creator_id');
        });
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });

        // 5. Fix inventory_reorder_rules table
        Schema::table('inventory_reorder_rules', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
            $table->unsignedBigInteger('creator_id')->nullable()->after('created_by');
        });

        // 6. Fix inventory_audit_logs table
        Schema::table('inventory_audit_logs', function (Blueprint $table) {
            $table->renameColumn('created_by', 'creator_id');
        });
        Schema::table('inventory_audit_logs', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_audit_logs', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
        });
        Schema::table('inventory_audit_logs', function (Blueprint $table) {
            $table->renameColumn('creator_id', 'created_by');
        });

        Schema::table('inventory_reorder_rules', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
            $table->dropColumn('creator_id');
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('creator_id', 'adjusted_by');
        });

        Schema::table('trade_landed_costs', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
            $table->dropColumn('creator_id');
        });

        Schema::table('trade_shipments', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
            $table->dropColumn('creator_id');
        });

        Schema::table('trade_lcs', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });
        Schema::table('trade_lcs', function (Blueprint $table) {
            $table->renameColumn('creator_id', 'created_by');
        });
    }
};
