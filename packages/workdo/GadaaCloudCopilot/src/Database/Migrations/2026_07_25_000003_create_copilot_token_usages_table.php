<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('copilot_token_usages')) {
            Schema::create('copilot_token_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->integer('prompt_tokens')->default(0);
                $table->integer('completion_tokens')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('token_cost', 12, 4)->default(0);
                $table->string('model_name', 100)->default('gemini-1.5-flash');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_token_usages');
    }
};
