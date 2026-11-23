<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'project_scope')) {
                $table->string('project_scope', 30)->default('client')->after('project_type');
            }
            if (!Schema::hasColumn('projects', 'internal_category')) {
                $table->string('internal_category', 50)->nullable()->after('project_scope');
            }
            if (!Schema::hasColumn('projects', 'project_sponsor_id')) {
                $table->unsignedBigInteger('project_sponsor_id')->nullable()->after('internal_category');
            }
            if (!Schema::hasColumn('projects', 'business_case')) {
                $table->text('business_case')->nullable()->after('project_sponsor_id');
            }
            if (!Schema::hasColumn('projects', 'target_roi_savings')) {
                $table->string('target_roi_savings', 255)->nullable()->after('business_case');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = ['project_scope', 'internal_category', 'project_sponsor_id', 'business_case', 'target_roi_savings'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
