<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transfers')) {
            Schema::table('transfers', function (Blueprint $table) {
                if (!Schema::hasColumn('transfers', 'waybill_number')) {
                    $table->string('waybill_number')->nullable()->unique()->after('id');
                }
                if (!Schema::hasColumn('transfers', 'status')) {
                    $table->string('status')->default('in_transit')->after('quantity');
                }
                if (!Schema::hasColumn('transfers', 'driver_name')) {
                    $table->string('driver_name')->nullable()->after('status');
                }
                if (!Schema::hasColumn('transfers', 'driver_phone')) {
                    $table->string('driver_phone')->nullable()->after('driver_name');
                }
                if (!Schema::hasColumn('transfers', 'vehicle_plate_number')) {
                    $table->string('vehicle_plate_number')->nullable()->after('driver_phone');
                }
                if (!Schema::hasColumn('transfers', 'received_quantity')) {
                    $table->decimal('received_quantity', 15, 2)->nullable()->after('quantity');
                }
                if (!Schema::hasColumn('transfers', 'damaged_quantity')) {
                    $table->decimal('damaged_quantity', 15, 2)->default(0)->after('received_quantity');
                }
                if (!Schema::hasColumn('transfers', 'discrepancy_reason')) {
                    $table->text('discrepancy_reason')->nullable()->after('damaged_quantity');
                }
                if (!Schema::hasColumn('transfers', 'dispatched_at')) {
                    $table->timestamp('dispatched_at')->nullable()->after('date');
                }
                if (!Schema::hasColumn('transfers', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('dispatched_at');
                }
                if (!Schema::hasColumn('transfers', 'notes')) {
                    $table->text('notes')->nullable()->after('received_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transfers')) {
            Schema::table('transfers', function (Blueprint $table) {
                $columns = [
                    'waybill_number', 'status', 'driver_name', 'driver_phone',
                    'vehicle_plate_number', 'received_quantity', 'damaged_quantity',
                    'discrepancy_reason', 'dispatched_at', 'received_at', 'notes'
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('transfers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
