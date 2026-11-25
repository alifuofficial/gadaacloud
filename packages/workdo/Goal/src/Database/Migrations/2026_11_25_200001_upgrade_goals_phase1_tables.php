<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade goals table
        if (Schema::hasTable('goals')) {
            Schema::table('goals', function (Blueprint $table) {
                if (!Schema::hasColumn('goals', 'bank_account_id')) {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->after('account_id')->index();
                }
                if (!Schema::hasColumn('goals', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 20)->nullable()->after('target_date');
                }
                if (!Schema::hasColumn('goals', 'ethiopian_quarter')) {
                    $table->string('ethiopian_quarter', 10)->nullable()->after('ethiopian_fiscal_year');
                }
            });

            // Modify enum columns to flexible strings if running in MySQL or compatible
            try {
                Schema::table('goals', function (Blueprint $table) {
                    $table->string('status', 40)->default('draft')->change();
                    $table->string('goal_type', 40)->default('savings')->change();
                });
            } catch (\Throwable $e) {
                // SQLite or driver without doctrine dbal modify
            }
        }

        // 2. Upgrade goal_milestones table
        if (Schema::hasTable('goal_milestones')) {
            try {
                Schema::table('goal_milestones', function (Blueprint $table) {
                    $table->string('status', 40)->default('pending')->change();
                });
            } catch (\Throwable $e) {
                // Fallback if change() unsupported
            }
        }

        // 3. Upgrade goal_contributions table
        if (Schema::hasTable('goal_contributions')) {
            Schema::table('goal_contributions', function (Blueprint $table) {
                if (!Schema::hasColumn('goal_contributions', 'reversal_of_id')) {
                    $table->unsignedBigInteger('reversal_of_id')->nullable()->after('reference_id')->index();
                }
            });

            try {
                Schema::table('goal_contributions', function (Blueprint $table) {
                    $table->string('reference_type', 50)->nullable()->change();
                    $table->string('contribution_type', 40)->default('manual')->change();
                });
            } catch (\Throwable $e) {
                // Fallback
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('goals')) {
            Schema::table('goals', function (Blueprint $table) {
                if (Schema::hasColumn('goals', 'bank_account_id')) {
                    $table->dropColumn('bank_account_id');
                }
                if (Schema::hasColumn('goals', 'ethiopian_fiscal_year')) {
                    $table->dropColumn('ethiopian_fiscal_year');
                }
                if (Schema::hasColumn('goals', 'ethiopian_quarter')) {
                    $table->dropColumn('ethiopian_quarter');
                }
            });
        }

        if (Schema::hasTable('goal_contributions')) {
            Schema::table('goal_contributions', function (Blueprint $table) {
                if (Schema::hasColumn('goal_contributions', 'reversal_of_id')) {
                    $table->dropColumn('reversal_of_id');
                }
            });
        }
    }
};
