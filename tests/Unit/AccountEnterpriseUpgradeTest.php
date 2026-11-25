<?php

namespace Tests\Unit;

use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Models\AccountingPeriod;
use Workdo\Account\Models\AccountCategory;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteApplication;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteApplication;
use Workdo\Account\Models\DebitNoteItem;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Account\Models\OpeningBalance;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\RevenueCategories;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Services\JournalService;

class AccountEnterpriseUpgradeTest extends TestCase
{
    public function test_sanitize_sort_rejects_untrusted_columns_and_directions(): void
    {
        $allowed = ['payment_number', 'payment_date', 'payment_amount'];

        [$col, $dir] = AccountUtility::sanitizeSort('payment_amount', $allowed, 'created_at', 'asc');
        $this->assertSame('payment_amount', $col);
        $this->assertSame('asc', $dir);

        [$injectedCol, $injectedDir] = AccountUtility::sanitizeSort('id; DROP TABLE users;--', $allowed, 'created_at', 'INVALID');
        $this->assertSame('created_at', $injectedCol);
        $this->assertSame('desc', $injectedDir);
    }

    public function test_all_tenant_owned_account_models_use_tenant_scoped_trait(): void
    {
        $tenantModels = [
            AccountCategory::class,
            AccountingPeriod::class,
            AccountType::class,
            BankAccount::class,
            BankTransaction::class,
            BankTransfer::class,
            ChartOfAccount::class,
            CreditNote::class,
            CreditNoteApplication::class,
            Customer::class,
            CustomerPayment::class,
            CustomerPaymentAllocation::class,
            DebitNote::class,
            DebitNoteApplication::class,
            DebitNoteItem::class,
            Expense::class,
            ExpenseCategories::class,
            JournalEntry::class,
            JournalEntryItem::class,
            OpeningBalance::class,
            Revenue::class,
            RevenueCategories::class,
            Vendor::class,
            VendorPayment::class,
        ];

        foreach ($tenantModels as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                \App\Traits\TenantScoped::class,
                $traits,
                "{$modelClass} must use TenantScoped trait"
            );
        }
    }

    public function test_accounting_period_lock_state_helpers(): void
    {
        $open = new AccountingPeriod(['status' => 'open']);
        $soft = new AccountingPeriod(['status' => 'soft_locked']);
        $closed = new AccountingPeriod(['status' => 'closed']);

        $this->assertFalse($open->isLocked());
        $this->assertTrue($soft->isLocked());
        $this->assertTrue($closed->isLocked());
    }

    public function test_journal_service_has_no_http_redirect_returns_or_silent_cogs_swallow(): void
    {
        $source = file_get_contents(base_path('packages/workdo/Account/src/Services/JournalService.php'));
        $this->assertNotFalse($source);
        $this->assertStringNotContainsString('return back(', $source);

        $ref = new ReflectionClass(JournalService::class);
        $this->assertTrue($ref->hasMethod('reverseJournalEntry'));
        $this->assertTrue($ref->hasMethod('reverseJournalEntryByReference'));
        $this->assertTrue($ref->hasMethod('createBankFeeJournal'));

        $wacMethod = new ReflectionMethod(JournalService::class, 'resolveWarehouseUnitCost');
        $this->assertTrue($wacMethod->isPrivate());
    }

    public function test_report_service_uses_parameterized_datediff_queries(): void
    {
        $source = file_get_contents(base_path('packages/workdo/Account/src/Services/ReportService.php'));
        $this->assertNotFalse($source);
        $this->assertStringNotContainsString('DATEDIFF("', $source);
        $this->assertStringContainsString('selectRaw(\'DATEDIFF(?, sales_invoices.due_date) as days_overdue\', [$asOfDate])', $source);
        $this->assertStringContainsString('selectRaw(\'DATEDIFF(?, purchase_invoices.due_date) as days_overdue\', [$asOfDate])', $source);
    }
}
