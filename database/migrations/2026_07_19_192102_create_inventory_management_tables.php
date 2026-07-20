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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('adjustment_type', ['addition', 'subtraction']);
            $table->decimal('quantity', 15, 4);
            $table->string('reason');
            $table->unsignedBigInteger('adjusted_by');
            $table->timestamps();
        });

        Schema::create('inventory_reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('min_stock_level', 15, 4)->default(5.0000);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        Schema::create('inventory_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->string('action_type'); // purchase, sale, transfer, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty_delta', 15, 4);
            $table->decimal('final_qty', 15, 4);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_logs');
        Schema::dropIfExists('inventory_reorder_rules');
        Schema::dropIfExists('inventory_adjustments');
    }
};
