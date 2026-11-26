<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_field_values')) {
            Schema::table('ticket_field_values', function (Blueprint $table) {
                if (!Schema::hasColumn('ticket_field_values', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->nullable();
                }
                if (!Schema::hasColumn('ticket_field_values', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_field_values')) {
            Schema::table('ticket_field_values', function (Blueprint $table) {
                $cols = array_filter(['creator_id', 'created_by'], fn ($c) => Schema::hasColumn('ticket_field_values', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
