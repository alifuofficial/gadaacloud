<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $projectChildren = [
            'project_activity_logs',
            'project_clients',
            'project_files',
            'project_milestones',
            'project_users',
        ];

        $taskChildren = [
            'task_comments',
            'task_subtasks',
        ];

        $bugChildren = [
            'bug_comments',
        ];

        foreach (array_merge($projectChildren, $taskChildren, $bugChildren) as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                });
            }
        }

        foreach ($projectChildren as $child) {
            if (Schema::hasTable($child) && Schema::hasTable('projects') && Schema::hasColumn($child, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT projects.created_by FROM projects WHERE projects.id = {$child}.project_id LIMIT 1) WHERE created_by IS NULL");
            }
        }

        foreach ($taskChildren as $child) {
            if (Schema::hasTable($child) && Schema::hasTable('project_tasks') && Schema::hasColumn($child, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT project_tasks.created_by FROM project_tasks WHERE project_tasks.id = {$child}.task_id LIMIT 1) WHERE created_by IS NULL");
            }
        }

        foreach ($bugChildren as $child) {
            if (Schema::hasTable($child) && Schema::hasTable('project_bugs') && Schema::hasColumn($child, 'created_by')) {
                DB::statement("UPDATE {$child} SET created_by = (SELECT project_bugs.created_by FROM project_bugs WHERE project_bugs.id = {$child}.bug_id LIMIT 1) WHERE created_by IS NULL");
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'project_activity_logs',
            'project_clients',
            'project_files',
            'project_milestones',
            'project_users',
            'task_comments',
            'task_subtasks',
            'bug_comments',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('created_by');
                });
            }
        }
    }
};
