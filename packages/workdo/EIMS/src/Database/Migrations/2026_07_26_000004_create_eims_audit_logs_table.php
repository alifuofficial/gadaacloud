<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eims_audit_logs')) {
            Schema::create('eims_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('eims_invoice_id')->nullable()->index();
                $table->string('action', 50); // REGISTER, OFFLINE_QUEUE, SYNC_OFFLINE, CANCEL, VERIFY, REJECT
                $table->string('previous_hash', 64)->nullable(); // SHA256 chained to preceding log
                $table->string('payload_hash', 64); // SHA256 of payload
                $table->string('signature', 64); // HMAC-SHA256 signature
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();

                $table->index(['created_by', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eims_audit_logs');
    }
};
