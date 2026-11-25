<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'is_pension_applicable')) {
                    $table->boolean('is_pension_applicable')->default(true)->after('basic_salary');
                }
                if (!Schema::hasColumn('employees', 'is_tax_exempt')) {
                    $table->boolean('is_tax_exempt')->default(false)->after('is_pension_applicable');
                }
            });
        }

        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                if (!Schema::hasColumn('loans', 'paid_amount')) {
                    $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
                }
                if (!Schema::hasColumn('loans', 'status')) {
                    $table->string('status', 20)->default('active')->after('reason');
                }
            });
        }

        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (!Schema::hasColumn('payrolls', 'ethiopian_period')) {
                    $table->string('ethiopian_period', 100)->nullable()->after('title');
                }
            });
        }

        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('payroll_entries', 'employee_pension')) {
                    $table->decimal('employee_pension', 10, 2)->default(0)->after('total_deductions');
                }
                if (!Schema::hasColumn('payroll_entries', 'employer_pension')) {
                    $table->decimal('employer_pension', 10, 2)->default(0)->after('employee_pension');
                }
                if (!Schema::hasColumn('payroll_entries', 'income_tax')) {
                    $table->decimal('income_tax', 10, 2)->default(0)->after('employer_pension');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                foreach (['is_pension_applicable', 'is_tax_exempt'] as $col) {
                    if (Schema::hasColumn('employees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                foreach (['paid_amount', 'status'] as $col) {
                    if (Schema::hasColumn('loans', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'ethiopian_period')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('ethiopian_period');
            });
        }

        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                foreach (['employee_pension', 'employer_pension', 'income_tax'] as $col) {
                    if (Schema::hasColumn('payroll_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
