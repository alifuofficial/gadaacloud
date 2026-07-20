<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->unsignedBigInteger('model_id'); // ID of the related document (e.g. sales invoice ID)
            $table->unsignedBigInteger('current_step_id')->nullable(); // references workflow_steps.id
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->unsignedBigInteger('created_by')->nullable(); // tenant scoped
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_requests');
    }
};
