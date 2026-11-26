<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'deactivation_reason')) {
                    $table->string('deactivation_reason', 40)->nullable()->after('is_enable_login')->index();
                }
                if (!Schema::hasColumn('users', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'deactivated_at')) {
                    $table->dropColumn('deactivated_at');
                }
                if (Schema::hasColumn('users', 'deactivation_reason')) {
                    $table->dropColumn('deactivation_reason');
                }
            });
        }
    }
};
