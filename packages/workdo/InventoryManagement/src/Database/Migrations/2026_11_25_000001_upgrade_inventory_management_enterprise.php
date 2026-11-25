<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add stock valuation columns to warehouse_stocks
        if (Schema::hasTable('warehouse_stocks')) {
            Schema::table('warehouse_stocks', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouse_stocks', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('warehouse_stocks', 'total_value')) {
                    $table->decimal('total_value', 15, 4)->default(0)->after('unit_cost');
                }
            });
        }

        // 2. Extend product_service_items.inventory_type to support batch/lot tracking
        if (Schema::hasTable('product_service_items') && Schema::hasColumn('product_service_items', 'inventory_type')) {
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement("ALTER TABLE product_service_items MODIFY COLUMN inventory_type ENUM('standard', 'serialized', 'batch') NOT NULL DEFAULT 'standard'");
            }
        }

        // 3. Upgrade inventory_adjustments with approval workflow status & cost valuation
        if (Schema::hasTable('inventory_adjustments')) {
            Schema::table('inventory_adjustments', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_adjustments', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('reason');
                }
                if (!Schema::hasColumn('inventory_adjustments', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('inventory_adjustments', 'workflow_request_id')) {
                    $table->unsignedBigInteger('workflow_request_id')->nullable()->after('status');
                }
            });
        }

        // 4. Upgrade inventory_reorder_rules with preferred vendor & default reorder quantity
        if (Schema::hasTable('inventory_reorder_rules')) {
            Schema::table('inventory_reorder_rules', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_reorder_rules', 'reorder_qty')) {
                    $table->decimal('reorder_qty', 15, 4)->default(10.0000)->after('min_stock_level');
                }
                if (!Schema::hasColumn('inventory_reorder_rules', 'preferred_vendor_id')) {
                    $table->unsignedBigInteger('preferred_vendor_id')->nullable()->after('reorder_qty');
                }
            });
        }

        // 5. Upgrade inventory_audit_logs with explicit reference_type & notes
        if (Schema::hasTable('inventory_audit_logs')) {
            Schema::table('inventory_audit_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_audit_logs', 'reference_type')) {
                    $table->string('reference_type', 100)->nullable()->after('reference_id');
                }
                if (!Schema::hasColumn('inventory_audit_logs', 'notes')) {
                    $table->string('notes')->nullable()->after('final_qty');
                }
            });
        }

        // 6. Create inventory_batches for lot/batch/expiry/ECX coffee grade tracking
        if (!Schema::hasTable('inventory_batches')) {
            Schema::create('inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->string('batch_number', 120);
                $table->string('lot_grade', 100)->nullable();
                $table->string('origin', 120)->nullable();
                $table->date('manufacture_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->decimal('initial_quantity', 15, 4)->default(0);
                $table->decimal('remaining_quantity', 15, 4)->default(0);
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->unsignedBigInteger('purchase_invoice_id')->nullable();
                $table->enum('status', ['active', 'depleted', 'expired', 'quarantined'])->default('active');
                $table->timestamps();

                $table->unique(['created_by', 'product_id', 'warehouse_id', 'batch_number'], 'batch_tenant_prod_wh_unique');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');

        if (Schema::hasTable('inventory_audit_logs')) {
            Schema::table('inventory_audit_logs', function (Blueprint $table) {
                foreach (['reference_type', 'notes'] as $col) {
                    if (Schema::hasColumn('inventory_audit_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('inventory_reorder_rules')) {
            Schema::table('inventory_reorder_rules', function (Blueprint $table) {
                foreach (['reorder_qty', 'preferred_vendor_id'] as $col) {
                    if (Schema::hasColumn('inventory_reorder_rules', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('inventory_adjustments')) {
            Schema::table('inventory_adjustments', function (Blueprint $table) {
                foreach (['status', 'unit_cost', 'workflow_request_id'] as $col) {
                    if (Schema::hasColumn('inventory_adjustments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('warehouse_stocks')) {
            Schema::table('warehouse_stocks', function (Blueprint $table) {
                foreach (['unit_cost', 'total_value'] as $col) {
                    if (Schema::hasColumn('warehouse_stocks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
