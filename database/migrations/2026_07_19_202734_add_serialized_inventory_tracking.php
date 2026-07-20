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
        // 1. Add inventory_type to product_service_items
        if (Schema::hasTable('product_service_items') && !Schema::hasColumn('product_service_items', 'inventory_type')) {
            Schema::table('product_service_items', function (Blueprint $table) {
                $table->enum('inventory_type', ['standard', 'serialized'])->default('standard')->after('type');
            });
        }

        // 2. Create inventory_serials table
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('serial_number');
            $table->enum('status', ['available', 'sold', 'damaged'])->default('available');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->timestamps();

            // Add unique constraint for (tenant, product, serial_number) to prevent duplicates per company
            $table->unique(['created_by', 'product_id', 'serial_number'], 'serial_tenant_product_unique');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');

        if (Schema::hasTable('product_service_items') && Schema::hasColumn('product_service_items', 'inventory_type')) {
            Schema::table('product_service_items', function (Blueprint $table) {
                $table->dropColumn('inventory_type');
            });
        }
    }
};
