<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade balance_sheets table
        if (Schema::hasTable('balance_sheets')) {
            Schema::table('balance_sheets', function (Blueprint $table) {
                if (!Schema::hasColumn('balance_sheets', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('financial_year')->index();
                }
                if (!Schema::hasColumn('balance_sheets', 'ethiopian_quarter')) {
                    $table->string('ethiopian_quarter', 10)->nullable()->after('ethiopian_fiscal_year');
                }
            });

            // Modify financial_year and status to flexible strings if supported
            try {
                Schema::table('balance_sheets', function (Blueprint $table) {
                    $table->string('financial_year', 30)->change();
                    $table->string('status', 40)->default('draft')->change();
                });
            } catch (\Throwable $e) {
                // SQLite or driver without doctrine dbal modify
            }
        }

        // 2. Upgrade comparative_balance_sheets table
        if (Schema::hasTable('comparative_balance_sheets')) {
            Schema::table('comparative_balance_sheets', function (Blueprint $table) {
                if (!Schema::hasColumn('comparative_balance_sheets', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('comparison_date')->index();
                }
                if (!Schema::hasColumn('comparative_balance_sheets', 'notes')) {
                    $table->text('notes')->nullable()->after('ethiopian_fiscal_year');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('balance_sheets')) {
            Schema::table('balance_sheets', function (Blueprint $table) {
                if (Schema::hasColumn('balance_sheets', 'ethiopian_fiscal_year')) {
                    $table->dropColumn('ethiopian_fiscal_year');
                }
                if (Schema::hasColumn('balance_sheets', 'ethiopian_quarter')) {
                    $table->dropColumn('ethiopian_quarter');
                }
            });
        }

        if (Schema::hasTable('comparative_balance_sheets')) {
            Schema::table('comparative_balance_sheets', function (Blueprint $table) {
                if (Schema::hasColumn('comparative_balance_sheets', 'ethiopian_fiscal_year')) {
                    $table->dropColumn('ethiopian_fiscal_year');
                }
                if (Schema::hasColumn('comparative_balance_sheets', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
