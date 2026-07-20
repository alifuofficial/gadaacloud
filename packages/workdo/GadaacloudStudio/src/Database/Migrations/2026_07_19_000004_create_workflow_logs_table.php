<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_request_id')->constrained('workflow_requests')->onDelete('cascade');
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->onDelete('cascade');
            $table->unsignedBigInteger('user_id'); // approver user ID
            $table->string('action'); // approve or reject
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};
