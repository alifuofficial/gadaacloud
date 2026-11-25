<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_accounts')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->nullable()->change();
                $table->decimal('current_balance', 15, 2)->nullable()->change();
            });

            if (!Schema::hasColumn('bank_accounts', 'currency_code')) {
                Schema::table('bank_accounts', function (Blueprint $table) {
                    $table->string('currency_code', 3)->default('ETB')->after('account_type');
                });
            }
        }

        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_payments', 'wht_rate')) {
                    $table->decimal('wht_rate', 5, 2)->default(0)->after('payment_amount');
                }
                if (!Schema::hasColumn('vendor_payments', 'wht_amount')) {
                    $table->decimal('wht_amount', 15, 2)->default(0)->after('wht_rate');
                }
                if (!Schema::hasColumn('vendor_payments', 'gross_settled_amount')) {
                    $table->decimal('gross_settled_amount', 15, 2)->nullable()->after('wht_amount');
                }
                if (!Schema::hasColumn('vendor_payments', 'wht_certificate_number')) {
                    $table->string('wht_certificate_number')->nullable()->after('gross_settled_amount');
                }
                if (!Schema::hasColumn('vendor_payments', 'currency_code')) {
                    $table->string('currency_code', 3)->default('ETB')->after('wht_certificate_number');
                }
                if (!Schema::hasColumn('vendor_payments', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 15, 6)->default(1.000000)->after('currency_code');
                }
                if (!Schema::hasColumn('vendor_payments', 'foreign_amount')) {
                    $table->decimal('foreign_amount', 15, 2)->nullable()->after('exchange_rate');
                }
                if (!Schema::hasColumn('vendor_payments', 'fx_gain_loss')) {
                    $table->decimal('fx_gain_loss', 15, 2)->default(0)->after('foreign_amount');
                }
            });
        }

        if (Schema::hasTable('customer_payments')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_payments', 'wht_rate')) {
                    $table->decimal('wht_rate', 5, 2)->default(0)->after('payment_amount');
                }
                if (!Schema::hasColumn('customer_payments', 'wht_amount')) {
                    $table->decimal('wht_amount', 15, 2)->default(0)->after('wht_rate');
                }
                if (!Schema::hasColumn('customer_payments', 'gross_settled_amount')) {
                    $table->decimal('gross_settled_amount', 15, 2)->nullable()->after('wht_amount');
                }
                if (!Schema::hasColumn('customer_payments', 'wht_certificate_number')) {
                    $table->string('wht_certificate_number')->nullable()->after('gross_settled_amount');
                }
                if (!Schema::hasColumn('customer_payments', 'currency_code')) {
                    $table->string('currency_code', 3)->default('ETB')->after('wht_certificate_number');
                }
                if (!Schema::hasColumn('customer_payments', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 15, 6)->default(1.000000)->after('currency_code');
                }
                if (!Schema::hasColumn('customer_payments', 'foreign_amount')) {
                    $table->decimal('foreign_amount', 15, 2)->nullable()->after('exchange_rate');
                }
                if (!Schema::hasColumn('customer_payments', 'fx_gain_loss')) {
                    $table->decimal('fx_gain_loss', 15, 2)->default(0)->after('foreign_amount');
                }
            });
        }

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('journal_entries', 'currency_code')) {
                    $table->string('currency_code', 3)->default('ETB')->after('total_credit');
                }
                if (!Schema::hasColumn('journal_entries', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 15, 6)->default(1.000000)->after('currency_code');
                }
                if (!Schema::hasColumn('journal_entries', 'reversed_by_entry_id')) {
                    $table->unsignedBigInteger('reversed_by_entry_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('journal_entries', 'reversal_reason')) {
                    $table->string('reversal_reason')->nullable()->after('reversed_by_entry_id');
                }
            });
        }

        if (Schema::hasTable('bank_transactions')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('bank_transactions', 'matched_type')) {
                    $table->string('matched_type', 64)->nullable()->after('reconciliation_status');
                }
                if (!Schema::hasColumn('bank_transactions', 'matched_id')) {
                    $table->unsignedBigInteger('matched_id')->nullable()->after('matched_type');
                }
                if (!Schema::hasColumn('bank_transactions', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable()->after('matched_id');
                }
                if (!Schema::hasColumn('bank_transactions', 'reconciled_by')) {
                    $table->unsignedBigInteger('reconciled_by')->nullable()->after('reconciled_at');
                }
                if (!Schema::hasColumn('bank_transactions', 'batch_reference')) {
                    $table->string('batch_reference', 100)->nullable()->after('reconciled_by');
                }
                $table->index(['bank_account_id', 'reconciliation_status'], 'idx_bank_txn_reconciliation');
            });
        }

        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('fiscal_year', 20);
                $table->unsignedTinyInteger('period_number')->default(1);
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['open', 'soft_locked', 'closed'])->default('open');
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'start_date', 'end_date'], 'idx_acc_period_tenant_dates');
                $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');

        if (Schema::hasTable('bank_transactions')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                foreach (['matched_type', 'matched_id', 'reconciled_at', 'reconciled_by', 'batch_reference'] as $col) {
                    if (Schema::hasColumn('bank_transactions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                foreach (['currency_code', 'exchange_rate', 'reversed_by_entry_id', 'reversal_reason'] as $col) {
                    if (Schema::hasColumn('journal_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        foreach (['vendor_payments', 'customer_payments'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    foreach ([
                        'wht_rate', 'wht_amount', 'gross_settled_amount', 'wht_certificate_number',
                        'currency_code', 'exchange_rate', 'foreign_amount', 'fx_gain_loss',
                    ] as $col) {
                        if (Schema::hasColumn($tableName, $col)) {
                            $table->dropColumn($col);
                        }
                    }
                });
            }
        }

        if (Schema::hasTable('bank_accounts') && Schema::hasColumn('bank_accounts', 'currency_code')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropColumn('currency_code');
            });
        }
    }
};
