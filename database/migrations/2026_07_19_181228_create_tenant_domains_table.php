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
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('custom_domain_status')->default('pending_dns'); // pending_dns, active, expired, suspended
            $table->text('custom_ssl_certificate')->nullable();
            $table->text('custom_ssl_private_key')->nullable();
            $table->boolean('is_registered_via_dynadot')->default(false);
            $table->timestamp('registration_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domain_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('domain');
            $table->string('payment_gateway')->nullable();
            $table->string('payment_status')->default('pending'); // pending, paid, failed
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('USD');
            $table->string('dynadot_status')->default('pending'); // pending, completed, failed
            $table->text('dynadot_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domain_orders');
        Schema::dropIfExists('tenant_domains');
    }
};
