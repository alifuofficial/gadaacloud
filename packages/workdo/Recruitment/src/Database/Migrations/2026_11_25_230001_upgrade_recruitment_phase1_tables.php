<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table) {
                if (!Schema::hasColumn('job_postings', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('application_deadline')->index();
                }
            });
        }

        if (Schema::hasTable('candidates')) {
            Schema::table('candidates', function (Blueprint $table) {
                if (!Schema::hasColumn('candidates', 'national_id')) {
                    $table->string('national_id', 60)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('candidates', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('application_date')->index();
                }
            });
        }

        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (!Schema::hasColumn('offers', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('offer_date')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table) {
                if (Schema::hasColumn('job_postings', 'ethiopian_fiscal_year')) {
                    $table->dropColumn('ethiopian_fiscal_year');
                }
            });
        }

        if (Schema::hasTable('candidates')) {
            Schema::table('candidates', function (Blueprint $table) {
                foreach (['national_id', 'ethiopian_fiscal_year'] as $col) {
                    if (Schema::hasColumn('candidates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (Schema::hasColumn('offers', 'ethiopian_fiscal_year')) {
                    $table->dropColumn('ethiopian_fiscal_year');
                }
            });
        }
    }
};
