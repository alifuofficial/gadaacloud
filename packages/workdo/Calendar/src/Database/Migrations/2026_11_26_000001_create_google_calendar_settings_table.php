<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('google_calendar_settings')) {
            Schema::create('google_calendar_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('service_account_json')->nullable();
                $table->string('calendar_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_settings');
    }
};
