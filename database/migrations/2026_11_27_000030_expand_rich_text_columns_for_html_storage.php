<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand VARCHAR(255) description/notes columns to TEXT so sanitized rich-text HTML
     * (headings, lists, tables, links, formatting) never silently truncates.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // SQLite treats TEXT/VARCHAR equivalently without length truncation
        }

        $targets = [
            'resignations' => ['description'],
            'promotions' => ['reason'],
            'warnings' => ['employee_response'],
            'awards' => ['description'],
            'award_types' => ['description'],
            'revenue_categories' => ['description'],
            'expense_categories' => ['description'],
            'ai_prompts' => ['description'],
            'ecc_customs_duties' => ['description'],
            'inventory_audit_logs' => ['notes'],
        ];

        foreach ($targets as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->text($column)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: keeping TEXT prevents data loss if HTML content already exists.
    }
};
