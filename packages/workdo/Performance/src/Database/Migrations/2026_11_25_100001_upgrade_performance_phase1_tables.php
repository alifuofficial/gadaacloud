<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('performance_employee_reviews')) {
            Schema::table('performance_employee_reviews', function (Blueprint $table) {
                $table->longText('rating')->nullable()->change();
                $table->string('status', 40)->default('pending')->change();

                if (!Schema::hasColumn('performance_employee_reviews', 'review_template_id')) {
                    $table->foreignId('review_template_id')->nullable()->after('review_cycle_id')->index();
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'self_rating')) {
                    $table->longText('self_rating')->nullable()->after('rating');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'self_pros')) {
                    $table->text('self_pros')->nullable()->after('cons');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'self_cons')) {
                    $table->text('self_cons')->nullable()->after('self_pros');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'self_submitted_at')) {
                    $table->timestamp('self_submitted_at')->nullable()->after('self_cons');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'competency_ratings')) {
                    $table->longText('competency_ratings')->nullable()->after('self_rating');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'goal_score')) {
                    $table->decimal('goal_score', 5, 2)->nullable()->after('competency_ratings');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'kpi_score')) {
                    $table->decimal('kpi_score', 5, 2)->nullable()->after('goal_score');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'competency_score')) {
                    $table->decimal('competency_score', 5, 2)->nullable()->after('kpi_score');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'overall_score')) {
                    $table->decimal('overall_score', 5, 2)->nullable()->after('competency_score');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'weighted_rating')) {
                    $table->decimal('weighted_rating', 4, 2)->nullable()->after('overall_score');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'rating_band')) {
                    $table->string('rating_band', 50)->nullable()->after('weighted_rating');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'workflow_stage')) {
                    $table->string('workflow_stage', 40)->default('manager_review')->after('status');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('workflow_stage');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'calibrated_by')) {
                    $table->foreignId('calibrated_by')->nullable()->after('is_locked');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'calibrated_at')) {
                    $table->timestamp('calibrated_at')->nullable()->after('calibrated_by');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'calibration_notes')) {
                    $table->text('calibration_notes')->nullable()->after('calibrated_at');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable()->after('calibration_notes');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'employee_acknowledgment_notes')) {
                    $table->text('employee_acknowledgment_notes')->nullable()->after('acknowledged_at');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'pip_recommended')) {
                    $table->boolean('pip_recommended')->default(false)->after('employee_acknowledgment_notes');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'promotion_recommended')) {
                    $table->boolean('promotion_recommended')->default(false)->after('pip_recommended');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'bonus_recommendation_amount')) {
                    $table->decimal('bonus_recommendation_amount', 12, 2)->nullable()->after('promotion_recommended');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'bonus_recommendation_type')) {
                    $table->string('bonus_recommendation_type', 20)->nullable()->after('bonus_recommendation_amount');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'linked_action_type')) {
                    $table->string('linked_action_type', 40)->nullable()->after('bonus_recommendation_type');
                }
                if (!Schema::hasColumn('performance_employee_reviews', 'linked_action_id')) {
                    $table->unsignedBigInteger('linked_action_id')->nullable()->after('linked_action_type');
                }
            });
        }

        if (Schema::hasTable('performance_indicators')) {
            Schema::table('performance_indicators', function (Blueprint $table) {
                if (!Schema::hasColumn('performance_indicators', 'weight')) {
                    $table->decimal('weight', 5, 2)->default(0)->after('target_value');
                }
                if (!Schema::hasColumn('performance_indicators', 'goal_direction')) {
                    $table->string('goal_direction', 25)->default('higher_is_better')->after('weight');
                }
                if (!Schema::hasColumn('performance_indicators', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->after('category_id')->index();
                }
                if (!Schema::hasColumn('performance_indicators', 'designation_id')) {
                    $table->foreignId('designation_id')->nullable()->after('department_id')->index();
                }
                if (!Schema::hasColumn('performance_indicators', 'kpi_source')) {
                    $table->string('kpi_source', 40)->default('manual')->after('goal_direction');
                }
            });
        }

        if (Schema::hasTable('performance_employee_goals')) {
            Schema::table('performance_employee_goals', function (Blueprint $table) {
                $table->string('status', 40)->default('not_started')->change();
                if (!Schema::hasColumn('performance_employee_goals', 'target_numeric')) {
                    $table->decimal('target_numeric', 14, 2)->nullable()->after('target');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'current_value')) {
                    $table->decimal('current_value', 14, 2)->nullable()->after('target_numeric');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'unit')) {
                    $table->string('unit', 30)->nullable()->after('current_value');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'weight')) {
                    $table->decimal('weight', 5, 2)->default(0)->after('progress');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'review_cycle_id')) {
                    $table->foreignId('review_cycle_id')->nullable()->after('goal_type_id')->index();
                }
                if (!Schema::hasColumn('performance_employee_goals', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->after('employee_id')->index();
                }
                if (!Schema::hasColumn('performance_employee_goals', 'kpi_source')) {
                    $table->string('kpi_source', 40)->default('manual')->after('status');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'auto_sync_enabled')) {
                    $table->boolean('auto_sync_enabled')->default(false)->after('kpi_source');
                }
                if (!Schema::hasColumn('performance_employee_goals', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable()->after('auto_sync_enabled');
                }
            });
        }

        if (Schema::hasTable('performance_review_cycles')) {
            Schema::table('performance_review_cycles', function (Blueprint $table) {
                $table->string('frequency', 40)->change();
                if (!Schema::hasColumn('performance_review_cycles', 'start_date')) {
                    $table->date('start_date')->nullable()->after('frequency');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'end_date')) {
                    $table->date('end_date')->nullable()->after('start_date');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'self_review_deadline')) {
                    $table->date('self_review_deadline')->nullable()->after('end_date');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'manager_review_deadline')) {
                    $table->date('manager_review_deadline')->nullable()->after('self_review_deadline');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'calendar_type')) {
                    $table->string('calendar_type', 20)->default('gregorian')->after('manager_review_deadline');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'ethiopian_fiscal_year')) {
                    $table->integer('ethiopian_fiscal_year')->nullable()->after('calendar_type');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'ethiopian_quarter')) {
                    $table->string('ethiopian_quarter', 15)->nullable()->after('ethiopian_fiscal_year');
                }
                if (!Schema::hasColumn('performance_review_cycles', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('ethiopian_quarter')->index();
                }
                if (!Schema::hasColumn('performance_review_cycles', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->after('branch_id')->index();
                }
                if (!Schema::hasColumn('performance_review_cycles', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive downgrade
    }
};
