<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competency_categories')) {
            Schema::create('competency_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('competencies')) {
            Schema::create('competencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->index();
                $table->foreignId('department_id')->nullable()->index();
                $table->foreignId('designation_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('weight', 5, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('category_id')->references('id')->on('competency_categories')->onDelete('cascade');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_competencies')) {
            Schema::create('employee_competencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->index();
                $table->foreignId('competency_id')->index();
                $table->foreignId('review_cycle_id')->nullable()->index();
                $table->foreignId('assessor_id')->nullable()->index();
                $table->decimal('rating', 4, 2)->default(0);
                $table->date('assessment_date');
                $table->text('comments')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('competency_id')->references('id')->on('competencies')->onDelete('cascade');
                $table->foreign('review_cycle_id')->references('id')->on('performance_review_cycles')->onDelete('set null');
                $table->foreign('assessor_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('feedback_types')) {
            Schema::create('feedback_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_feedback')) {
            Schema::create('employee_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->index();
                $table->foreignId('given_by')->nullable()->index();
                $table->foreignId('feedback_type_id')->index();
                $table->foreignId('review_cycle_id')->nullable()->index();
                $table->string('relationship_type', 30)->default('peer');
                $table->date('date');
                $table->tinyInteger('rating')->nullable();
                $table->text('content');
                $table->string('visibility', 20)->default('team');
                $table->boolean('is_anonymous')->default(false);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('given_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('feedback_type_id')->references('id')->on('feedback_types')->onDelete('cascade');
                $table->foreign('review_cycle_id')->references('id')->on('performance_review_cycles')->onDelete('set null');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('performance_items')) {
            Schema::create('performance_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->tinyInteger('rating_scale_max')->default(5);
                $table->decimal('weight', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('review_templates')) {
            Schema::create('review_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('branch_id')->nullable()->index();
                $table->foreignId('department_id')->nullable()->index();
                $table->foreignId('designation_id')->nullable()->index();
                $table->decimal('kpi_weight', 5, 2)->default(60);
                $table->decimal('goal_weight', 5, 2)->default(20);
                $table->decimal('competency_weight', 5, 2)->default(20);
                $table->boolean('include_self_assessment')->default(true);
                $table->longText('indicator_weights')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('review_template_indicators')) {
            Schema::create('review_template_indicators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_template_id')->index();
                $table->foreignId('performance_indicator_id')->index();
                $table->decimal('weight', 5, 2)->default(0);
                $table->foreign('review_template_id')->references('id')->on('review_templates')->onDelete('cascade');
                $table->foreign('performance_indicator_id')->references('id')->on('performance_indicators')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_template_indicators');
        Schema::dropIfExists('review_templates');
        Schema::dropIfExists('performance_items');
        Schema::dropIfExists('employee_feedback');
        Schema::dropIfExists('feedback_types');
        Schema::dropIfExists('employee_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('competency_categories');
    }
};
