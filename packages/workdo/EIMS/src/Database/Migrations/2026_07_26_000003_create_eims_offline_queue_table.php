<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eims_offline_queue')) {
            Schema::create('eims_offline_queue', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('eims_invoice_id')->index();
                $table->string('idempotency_key', 100)->unique();
                $table->longText('payload'); // AES-256 encrypted payload
                $table->unsignedInteger('attempt_count')->default(0);
                $table->unsignedInteger('max_attempts')->default(10);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index(); // Strictly 72h from invoice generation
                $table->text('last_error')->nullable();
                $table->string('status', 30)->default('QUEUED')->index(); // QUEUED, PROCESSING, COMPLETED, EXPIRED, DEAD_LETTER
                $table->timestamps();

                $table->index(['created_by', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eims_offline_queue');
    }
};
