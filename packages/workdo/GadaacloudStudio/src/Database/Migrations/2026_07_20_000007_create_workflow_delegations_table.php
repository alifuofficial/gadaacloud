<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_delegations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_request_id');
            $table->unsignedBigInteger('workflow_step_id');
            $table->unsignedBigInteger('delegated_by_user_id');
            $table->unsignedBigInteger('delegated_to_user_id');
            $table->text('reason')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('workflow_request_id')->references('id')->on('workflow_requests')->onDelete('cascade');
            $table->foreign('workflow_step_id')->references('id')->on('workflow_steps')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_delegations');
    }
};
