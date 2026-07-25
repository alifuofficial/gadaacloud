<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chatter_messages')) {
            Schema::create('chatter_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('model_type', 100)->index();
                $table->unsignedBigInteger('model_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('message');
                $table->string('type', 20)->default('note'); // note, message, activity, diff
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('chatter_attachments')) {
            Schema::create('chatter_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('chatter_message_id')->index();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('file_type', 50)->nullable();
                $table->integer('file_size')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('chatter_activities')) {
            Schema::create('chatter_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('model_type', 100)->index();
                $table->unsignedBigInteger('model_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('activity_type', 50)->default('todo');
                $table->string('title');
                $table->date('due_date')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chatter_activities');
        Schema::dropIfExists('chatter_attachments');
        Schema::dropIfExists('chatter_messages');
    }
};
