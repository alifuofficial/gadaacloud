<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eims_invoices')) {
            Schema::create('eims_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
                $table->string('invoice_number', 100)->index();
                $table->string('document_type', 10)->default('INV'); // INV, CRN, DBN
                $table->string('previous_irn', 128)->nullable();
                $table->string('irn', 128)->nullable()->index();
                $table->string('rrn', 128)->nullable()->index();
                $table->unsignedBigInteger('fiscal_counter')->default(1);
                $table->text('qr_code_data')->nullable();
                $table->longText('qr_code_image')->nullable();
                $table->string('buyer_type', 10)->default('B2C'); // B2B, B2C, B2G
                $table->string('buyer_tin', 10)->nullable();
                $table->string('buyer_name', 191)->nullable();
                $table->string('buyer_id_type', 10)->nullable(); // NID, KID, SID, WID, PST, DLS, MRS
                $table->string('buyer_id_number', 100)->nullable();
                $table->string('currency', 10)->default('ETB');
                $table->decimal('pre_tax_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('excise_tax_amount', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('payment_mode', 20)->default('CASH'); // CASH, CREDIT, ADVANCE
                $table->string('payment_term', 20)->default('IMMIDIATE'); // IMMIDIATE, NET30, NET60
                $table->string('status', 30)->default('PENDING'); // CLEARED, PENDING_OFFLINE, CANCELLED, FAILED, DRAFT
                $table->unsignedTinyInteger('cancel_reason_code')->nullable(); // 1: DUPLICATE, 2: DATA_ENTRY_MISTAKE, 3: ORDER_CANCELLED, 4: OTHERS
                $table->string('cancel_remark', 100)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->json('eims_payload')->nullable();
                $table->json('eims_response')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'status']);
                $table->index(['created_by', 'document_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eims_invoices');
    }
};
