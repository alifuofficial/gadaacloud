<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('condition_field')->nullable()->after('sequence')
                ->comment('Document field name to evaluate, e.g. total, amount');
            $table->string('condition_operator')->nullable()->after('condition_field')
                ->comment('Comparison operator: gt, lt, gte, lte, eq, neq');
            $table->string('condition_value')->nullable()->after('condition_operator')
                ->comment('The value to compare against');
            $table->boolean('skip_if_condition_fails')->default(false)->after('condition_value')
                ->comment('If true, step is auto-skipped when condition evaluates false');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn(['condition_field', 'condition_operator', 'condition_value', 'skip_if_condition_fails']);
        });
    }
};
