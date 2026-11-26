<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'employment_status')) {
                    $table->string('employment_status', 30)->default('active')->after('employment_type')->index();
                }
                if (!Schema::hasColumn('employees', 'separation_date')) {
                    $table->date('separation_date')->nullable()->after('employment_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'separation_date')) {
                    $table->dropColumn('separation_date');
                }
                if (Schema::hasColumn('employees', 'employment_status')) {
                    $table->dropColumn('employment_status');
                }
            });
        }
    }
};
