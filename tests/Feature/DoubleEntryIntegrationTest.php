<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Account\Models\AccountCategory;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\AccountingPeriod;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Account\Models\OpeningBalance;
use Workdo\DoubleEntry\Models\BalanceSheet;
use Workdo\DoubleEntry\Models\BalanceSheetItem;
use Workdo\DoubleEntry\Models\BalanceSheetNote;
use Workdo\DoubleEntry\Models\ComparativeBalanceSheet;
use Workdo\DoubleEntry\Services\BalanceSheetService;
use Workdo\DoubleEntry\Services\ProfitLossService;
use Workdo\DoubleEntry\Services\TrialBalanceService;

class DoubleEntryIntegrationTest extends TestCase
{
    protected User $company1;
    protected User $company2;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        // Create core users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('type')->default('company');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // Create roles and model_has_roles tables
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        // Add-ons table
        if (!Schema::hasTable('add_ons')) {
            Schema::create('add_ons', function (Blueprint $table) {
                $table->id();
                $table->string('module');
                $table->string('name');
                $table->decimal('monthly_price', 8, 2)->default(0);
                $table->decimal('yearly_price', 8, 2)->default(0);
                $table->string('image')->nullable();
                $table->boolean('is_enable')->default(true);
                $table->boolean('for_admin')->default(false);
                $table->string('package_name')->default('Account');
                $table->integer('priority')->default(0);
                $table->timestamps();
            });

            DB::table('add_ons')->insert([
                'module'       => 'DoubleEntry',
                'name'         => 'Double Entry',
                'is_enable'    => 1,
                'package_name' => 'DoubleEntry',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('add_ons')->insert([
                'module'       => 'Account',
                'name'         => 'Account',
                'is_enable'    => 1,
                'package_name' => 'Account',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // Create account categories table
        if (!Schema::hasTable('account_categories')) {
            Schema::create('account_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('type');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create account types table
        if (!Schema::hasTable('account_types')) {
            Schema::create('account_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('normal_balance')->default('debit');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system_type')->default(false);
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create chart_of_accounts
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('account_code');
                $table->string('account_name');
                $table->integer('level')->default(1);
                $table->string('normal_balance')->default('debit');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('current_balance', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system_account')->default(false);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('account_type_id')->nullable();
                $table->unsignedBigInteger('parent_account_id')->nullable();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create opening_balances
        if (!Schema::hasTable('opening_balances')) {
            Schema::create('opening_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('financial_year')->nullable();
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('debit_amount', 15, 2)->default(0);
                $table->decimal('credit_amount', 15, 2)->default(0);
                $table->string('balance_type')->default('debit');
                $table->date('effective_date')->nullable();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create journal_entries
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('journal_number')->nullable();
                $table->date('journal_date');
                $table->string('entry_type')->default('manual');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_credit', 15, 2)->default(0);
                $table->string('status')->default('posted');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create journal_entry_items
        if (!Schema::hasTable('journal_entry_items')) {
            Schema::create('journal_entry_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id');
                $table->unsignedBigInteger('account_id');
                $table->text('description')->nullable();
                $table->decimal('debit_amount', 15, 2)->default(0);
                $table->decimal('credit_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create accounting_periods
        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('fiscal_year');
                $table->integer('period_number')->default(1);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('open');
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create DoubleEntry tables
        if (!Schema::hasTable('balance_sheets')) {
            Schema::create('balance_sheets', function (Blueprint $table) {
                $table->id();
                $table->date('balance_sheet_date');
                $table->string('financial_year', 30);
                $table->string('ethiopian_fiscal_year', 30)->nullable();
                $table->string('ethiopian_quarter', 10)->nullable();
                $table->decimal('total_assets', 15, 2)->default(0);
                $table->decimal('total_liabilities', 15, 2)->default(0);
                $table->decimal('total_equity', 15, 2)->default(0);
                $table->boolean('is_balanced')->default(false);
                $table->string('status', 40)->default('draft');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('balance_sheet_items')) {
            Schema::create('balance_sheet_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('balance_sheet_id');
                $table->unsignedBigInteger('account_id');
                $table->string('section_type');
                $table->string('sub_section');
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('balance_sheet_notes')) {
            Schema::create('balance_sheet_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('balance_sheet_id');
                $table->integer('note_number');
                $table->string('note_title');
                $table->text('note_content');
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('comparative_balance_sheets')) {
            Schema::create('comparative_balance_sheets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('current_period_id');
                $table->unsignedBigInteger('previous_period_id');
                $table->date('comparison_date');
                $table->string('ethiopian_fiscal_year', 30)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Create test companies
        $this->company1 = User::create([
            'id'       => 1,
            'name'     => 'Company One',
            'email'    => 'comp1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type'     => 'company',
        ]);
        $this->company1->created_by = $this->company1->id;
        $this->company1->save();

        $this->company2 = User::create([
            'id'       => 2,
            'name'     => 'Company Two',
            'email'    => 'comp2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type'     => 'company',
        ]);
        $this->company2->created_by = $this->company2->id;
        $this->company2->save();
    }

    public function test_multi_tenant_isolation_on_balance_sheets(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $bs1 = BalanceSheet::create([
            'balance_sheet_date' => '2026-11-25',
            'financial_year' => '2026',
            'total_assets' => 50000,
            'total_liabilities' => 20000,
            'total_equity' => 30000,
            'is_balanced' => true,
            'status' => 'draft',
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        $this->assertCount(1, BalanceSheet::all());
        $this->assertEquals($bs1->id, BalanceSheet::first()->id);

        // Switch to company 2
        Auth::login($this->company2);
        Once::flush();

        // Company 2 must see zero balance sheets
        $this->assertCount(0, BalanceSheet::all());
        $this->assertNull(BalanceSheet::find($bs1->id));
    }

    public function test_multi_tenant_isolation_on_comparative_balance_sheets(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $bsA = BalanceSheet::create([
            'balance_sheet_date' => '2025-12-31',
            'financial_year' => '2025',
            'total_assets' => 10000,
            'total_liabilities' => 5000,
            'total_equity' => 5000,
            'is_balanced' => true,
            'status' => 'finalized',
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        $bsB = BalanceSheet::create([
            'balance_sheet_date' => '2026-12-31',
            'financial_year' => '2026',
            'total_assets' => 15000,
            'total_liabilities' => 5000,
            'total_equity' => 10000,
            'is_balanced' => true,
            'status' => 'finalized',
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        $comp = ComparativeBalanceSheet::create([
            'current_period_id' => $bsB->id,
            'previous_period_id' => $bsA->id,
            'comparison_date' => now(),
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        $this->assertCount(1, ComparativeBalanceSheet::all());

        // Switch to company 2
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, ComparativeBalanceSheet::all());
        $this->assertNull(ComparativeBalanceSheet::find($comp->id));
    }

    public function test_balance_sheet_generation_and_balancing(): void
    {
        Auth::login($this->company1);
        Once::flush();

        // Setup chart of accounts: Cash (1010, Asset), Accounts Payable (2010, Liability), Capital (3010, Equity)
        $cash = ChartOfAccount::create([
            'account_code' => '1010',
            'account_name' => 'Cash',
            'normal_balance' => 'debit',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $ap = ChartOfAccount::create([
            'account_code' => '2010',
            'account_name' => 'Accounts Payable',
            'normal_balance' => 'credit',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $capital = ChartOfAccount::create([
            'account_code' => '3010',
            'account_name' => 'Owner Capital',
            'normal_balance' => 'credit',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        // Post a balanced journal entry: Debit Cash 100,000, Credit AP 40,000, Credit Capital 60,000
        $je = JournalEntry::create([
            'journal_date' => '2026-11-20',
            'entry_type' => 'manual',
            'total_debit' => 100000,
            'total_credit' => 100000,
            'status' => 'posted',
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        JournalEntryItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $cash->id,
            'debit_amount' => 100000,
            'credit_amount' => 0,
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        JournalEntryItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $ap->id,
            'debit_amount' => 0,
            'credit_amount' => 40000,
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        JournalEntryItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $capital->id,
            'debit_amount' => 0,
            'credit_amount' => 60000,
            'creator_id' => $this->company1->id,
            'created_by' => $this->company1->id,
        ]);

        $service = new BalanceSheetService();
        $bsId = $service->generateBalanceSheet('2026-11-25', '2026', '2019 E.C.', 'q2');

        $this->assertNotNull($bsId);
        $bs = BalanceSheet::find($bsId);

        $this->assertEquals(100000.0, (float) $bs->total_assets);
        $this->assertEquals(40000.0, (float) $bs->total_liabilities);
        $this->assertEquals(60000.0, (float) $bs->total_equity);
        $this->assertTrue((bool) $bs->is_balanced);
        $this->assertEquals('2019 E.C.', $bs->ethiopian_fiscal_year);
        $this->assertEquals('q2', $bs->ethiopian_quarter);
    }

    public function test_retained_earnings_account_auto_provisioning(): void
    {
        Auth::login($this->company1);
        Once::flush();

        // Verify account 3200 does not exist
        $this->assertNull(ChartOfAccount::where('account_code', '3200')->where('created_by', $this->company1->id)->first());

        $service = new BalanceSheetService();
        $account = $service->getOrCreateRetainedEarningsAccount();

        $this->assertNotNull($account);
        $this->assertEquals('3200', $account->account_code);
        $this->assertEquals('Retained Earnings', $account->account_name);
        $this->assertEquals('credit', $account->normal_balance);
        $this->assertEquals($this->company1->id, $account->created_by);
    }

    public function test_year_end_close_atomic_execution_and_period_lock(): void
    {
        Auth::login($this->company1);
        Once::flush();

        // 1. Create open AccountingPeriod
        $period = AccountingPeriod::create([
            'name' => 'FY 2026',
            'fiscal_year' => '2026',
            'period_number' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'created_by' => $this->company1->id,
            'creator_id' => $this->company1->id,
        ]);

        // 2. Create revenue (4010) and expense (5010) accounts
        $revenue = ChartOfAccount::create([
            'account_code' => '4010',
            'account_name' => 'Sales Revenue',
            'normal_balance' => 'credit',
            'current_balance' => 80000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $expense = ChartOfAccount::create([
            'account_code' => '5010',
            'account_name' => 'Rent Expense',
            'normal_balance' => 'debit',
            'current_balance' => 30000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $service = new BalanceSheetService();
        $service->performYearEndClose('2026', '2026-12-31');

        // Verify closing journal entry was created
        $closingEntry = JournalEntry::where('reference_type', 'year_end_close')
            ->where('created_by', $this->company1->id)
            ->first();

        $this->assertNotNull($closingEntry);
        $this->assertEquals('posted', $closingEntry->status);
        $this->assertEquals(80000.0, (float) $closingEntry->total_debit);
        $this->assertEquals(80000.0, (float) $closingEntry->total_credit);

        // Verify AccountingPeriod was closed
        $period->refresh();
        $this->assertEquals('closed', $period->status);
        $this->assertNotNull($period->locked_at);

        // Verify opening balances were created for next year (2027)
        $nextOpening = OpeningBalance::where('financial_year', '2027')
            ->where('created_by', $this->company1->id)
            ->exists();
        $this->assertTrue($nextOpening);
    }

    public function test_profit_loss_service_calculation(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $sales = ChartOfAccount::create([
            'account_code' => '4010',
            'account_name' => 'Product Sales',
            'normal_balance' => 'credit',
            'opening_balance' => 50000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $supplies = ChartOfAccount::create([
            'account_code' => '5020',
            'account_name' => 'Office Supplies',
            'normal_balance' => 'debit',
            'opening_balance' => 15000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $service = new ProfitLossService();
        $result = $service->generateProfitLoss('2026-01-01', '2026-12-31');

        $this->assertEquals(50000.0, $result['total_revenue']);
        $this->assertEquals(15000.0, $result['total_expenses']);
        $this->assertEquals(35000.0, $result['net_profit']);
    }

    public function test_trial_balance_service_calculation(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $cash = ChartOfAccount::create([
            'account_code' => '1010',
            'account_name' => 'Bank Account',
            'normal_balance' => 'debit',
            'opening_balance' => 25000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $capital = ChartOfAccount::create([
            'account_code' => '3010',
            'account_name' => 'Equity Capital',
            'normal_balance' => 'credit',
            'opening_balance' => 25000,
            'is_active' => true,
            'created_by' => $this->company1->id,
        ]);

        $service = new TrialBalanceService();
        $tb = $service->generateTrialBalance('2026-01-01', '2026-12-31');

        $this->assertEquals(25000.0, $tb['total_debit']);
        $this->assertEquals(25000.0, $tb['total_credit']);
        $this->assertTrue($tb['is_balanced']);
    }
}
