<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Ethiopia Trade & Logistics Operations.
     */
    public function up(): void
    {
        // 1. National Bank of Ethiopia (NBE) Forex Allocation Queues
        if (!Schema::hasTable('nbe_forex_queues')) {
            Schema::create('nbe_forex_queues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('lc_number', 100);
                $table->string('bank_name', 255)->default('Commercial Bank of Ethiopia');
                $table->string('nbe_queue_number', 100)->nullable();
                $table->boolean('is_franco_valuta')->default(false);
                $table->decimal('amount_usd', 15, 2)->default(0.00);
                $table->string('queue_status', 50)->default('pending'); // pending, allocated, utilized, cancelled
                $table->date('application_date');
                $table->date('allocated_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'queue_status']);
            });
        }

        // 2. Djibouti Port & Doraleh Container Terminal (DCT) Storage & Demurrage Tracking
        if (!Schema::hasTable('djibouti_port_containers')) {
            Schema::create('djibouti_port_containers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('container_number', 100);
                $table->string('vessel_name', 255);
                $table->string('bill_of_lading', 100);
                $table->date('discharge_date');
                $table->integer('free_storage_days')->default(8); // Standard 8 free days for Ethiopian transit cargo
                $table->decimal('daily_demurrage_usd', 10, 2)->default(50.00);
                $table->string('current_location', 100)->default('DCT Djibouti');
                $table->string('status', 50)->default('in_port'); // in_port, cleared, in_rail_transit, delivered
                $table->date('cleared_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'status']);
            });
        }

        // 3. Ethiopian Customs Commission (ECC) Duty & Tariff Assessments
        if (!Schema::hasTable('ecc_customs_duties')) {
            Schema::create('ecc_customs_duties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('hs_code', 50);
                $table->string('description', 255);
                $table->decimal('cif_value_etb', 15, 2);
                $table->decimal('duty_rate_percent', 5, 2)->default(20.00);
                $table->decimal('excise_rate_percent', 5, 2)->default(0.00);
                $table->decimal('vat_percent', 5, 2)->default(15.00);
                $table->decimal('surtax_percent', 5, 2)->default(10.00);
                $table->decimal('withholding_percent', 5, 2)->default(3.00);
                $table->decimal('total_duty_payable_etb', 15, 2);
                $table->timestamps();

                $table->index(['created_by', 'hs_code']);
            });
        }

        // 4. Ethiopian Commodity Exchange (ECX) & Coffee Export Contracts
        if (!Schema::hasTable('ecx_export_contracts')) {
            Schema::create('ecx_export_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('contract_number', 100);
                $table->string('commodity_type', 100)->default('Yirgacheffe Arabica Coffee');
                $table->string('ecx_grade', 50)->default('Grade 1');
                $table->decimal('quantity_metric_tons', 12, 2);
                $table->decimal('contract_value_usd', 15, 2);
                $table->string('destination_country', 100);
                $table->string('lc_status', 50)->default('lc_opened'); // draft, lc_opened, cupping_approved, shipped, settled
                $table->decimal('mep_rate_usd', 10, 2)->nullable(); // Minimum Export Price per metric ton
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'lc_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecx_export_contracts');
        Schema::dropIfExists('ecc_customs_duties');
        Schema::dropIfExists('djibouti_port_containers');
        Schema::dropIfExists('nbe_forex_queues');
    }
};
