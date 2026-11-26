<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'workflow_steps',
        'workflow_logs',
        'workflow_delegations',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'created_by')) {
                        $table->unsignedBigInteger('created_by')->nullable()->index();
                    }
                });
            }
        }

        // Backfill workflow_steps from parent workflows table
        if (Schema::hasTable('workflow_steps') && Schema::hasTable('workflows')) {
            $rows = DB::table('workflow_steps')->whereNull('created_by')->get(['id', 'workflow_id']);
            foreach ($rows as $row) {
                $companyId = DB::table('workflows')->where('id', $row->workflow_id)->value('created_by');
                if ($companyId) {
                    DB::table('workflow_steps')->where('id', $row->id)->update(['created_by' => $companyId]);
                }
            }
        }

        // Backfill workflow_logs and workflow_delegations from parent workflow_requests table
        foreach (['workflow_logs', 'workflow_delegations'] as $childTable) {
            if (Schema::hasTable($childTable) && Schema::hasTable('workflow_requests')) {
                $rows = DB::table($childTable)->whereNull('created_by')->get(['id', 'workflow_request_id']);
                foreach ($rows as $row) {
                    $companyId = DB::table('workflow_requests')->where('id', $row->workflow_request_id)->value('created_by');
                    if ($companyId) {
                        DB::table($childTable)->where('id', $row->id)->update(['created_by' => $companyId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('created_by');
                });
            }
        }
    }
};
