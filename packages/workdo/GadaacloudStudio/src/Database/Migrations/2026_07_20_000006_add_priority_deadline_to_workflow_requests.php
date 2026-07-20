<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status')
                ->comment('low | medium | high | urgent');
            $table->timestamp('deadline_at')->nullable()->after('priority')
                ->comment('Optional deadline for this approval request');
            $table->unsignedBigInteger('delegated_to_user_id')->nullable()->after('deadline_at')
                ->comment('Current active delegate for the pending step');
            $table->text('submitter_note')->nullable()->after('delegated_to_user_id')
                ->comment('Note added by the person who submitted this for approval');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->dropColumn(['priority', 'deadline_at', 'delegated_to_user_id', 'submitter_note']);
        });
    }
};
