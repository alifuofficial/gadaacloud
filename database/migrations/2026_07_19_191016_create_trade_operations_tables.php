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
        Schema::create('trade_lcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('lc_number')->unique();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('vendor_id');
            $table->string('issuing_bank');
            $table->string('advising_bank')->nullable();
            $table->decimal('amount', 15, 4);
            $table->string('currency', 10)->default('USD');
            $table->decimal('exchange_rate', 15, 6)->default(1.000000);
            $table->decimal('tolerance_percent', 5, 2)->default(0.00);
            $table->string('payment_terms')->nullable();
            $table->date('latest_shipment_date')->nullable();
            $table->date('expiry_date');
            $table->string('status')->default('draft'); // draft, open, utilized, closed
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('trade_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lc_id')->nullable()->constrained('trade_lcs')->onDelete('set null');
            $table->string('shipping_line');
            $table->string('vessel_name');
            $table->string('voyage_number')->nullable();
            $table->text('container_numbers')->nullable();
            $table->string('bill_of_lading')->unique();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->date('atd')->nullable();
            $table->date('ata')->nullable();
            $table->string('status')->default('on_port'); // on_port, in_transit, customs_clearance, delivered
            $table->timestamps();
        });

        Schema::create('trade_landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lc_id')->constrained('trade_lcs')->onDelete('cascade');
            $table->decimal('freight_charges', 15, 4)->default(0.0000);
            $table->decimal('insurance_fees', 15, 4)->default(0.0000);
            $table->decimal('custom_duties', 15, 4)->default(0.0000);
            $table->decimal('agent_fees', 15, 4)->default(0.0000);
            $table->decimal('bank_fees', 15, 4)->default(0.0000);
            $table->string('currency', 10)->default('ETB');
            $table->string('allocation_method')->default('value'); // value, quantity
            $table->boolean('is_posted_to_accounts')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_landed_costs');
        Schema::dropIfExists('trade_shipments');
        Schema::dropIfExists('trade_lcs');
    }
};
