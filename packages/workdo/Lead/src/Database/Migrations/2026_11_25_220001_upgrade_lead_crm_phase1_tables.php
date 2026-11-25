<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (!Schema::hasColumn('leads', 'company_name')) {
                    $table->string('company_name', 150)->nullable()->after('name');
                }
                if (!Schema::hasColumn('leads', 'tin_number')) {
                    $table->string('tin_number', 50)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('leads', 'vat_number')) {
                    $table->string('vat_number', 50)->nullable()->after('tin_number');
                }
                if (!Schema::hasColumn('leads', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('date')->index();
                }
            });
        }

        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                if (!Schema::hasColumn('deals', 'tin_number')) {
                    $table->string('tin_number', 50)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('deals', 'vat_number')) {
                    $table->string('vat_number', 50)->nullable()->after('tin_number');
                }
                if (!Schema::hasColumn('deals', 'ethiopian_fiscal_year')) {
                    $table->string('ethiopian_fiscal_year', 30)->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('deals', 'quotation_id')) {
                    $table->unsignedBigInteger('quotation_id')->nullable()->after('ethiopian_fiscal_year')->index();
                }
                if (!Schema::hasColumn('deals', 'contract_id')) {
                    $table->unsignedBigInteger('contract_id')->nullable()->after('quotation_id')->index();
                }
                if (!Schema::hasColumn('deals', 'project_id')) {
                    $table->unsignedBigInteger('project_id')->nullable()->after('contract_id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                foreach (['company_name', 'tin_number', 'vat_number', 'ethiopian_fiscal_year'] as $col) {
                    if (Schema::hasColumn('leads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                foreach (['tin_number', 'vat_number', 'ethiopian_fiscal_year', 'quotation_id', 'contract_id', 'project_id'] as $col) {
                    if (Schema::hasColumn('deals', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
