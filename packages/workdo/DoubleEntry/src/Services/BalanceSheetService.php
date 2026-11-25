<?php

namespace Workdo\DoubleEntry\Services;

use Workdo\DoubleEntry\Models\BalanceSheet;
use Workdo\DoubleEntry\Models\BalanceSheetItem;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\AccountCategory;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\OpeningBalance;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Account\Models\AccountingPeriod;
use Workdo\Account\Services\JournalService;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    use DoubleEntryDateHelpers;

    public function calculateAllAccountBalances($asOfDate)
    {
        return DB::select("
            SELECT
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.normal_balance,
                coa.opening_balance,
                coa.account_type_id,
                ac.type as category_type,
                at.code as type_code,
                CASE
                    WHEN coa.normal_balance = 'debit' THEN
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN substr(je.journal_date, 1, 10) <= substr(?, 1, 10) AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN substr(je.journal_date, 1, 10) <= substr(?, 1, 10) AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0)
                    ELSE
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN substr(je.journal_date, 1, 10) <= substr(?, 1, 10) AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN substr(je.journal_date, 1, 10) <= substr(?, 1, 10) AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0)
                END as current_balance
            FROM chart_of_accounts coa
            LEFT JOIN account_types at ON coa.account_type_id = at.id
            LEFT JOIN account_categories ac ON at.category_id = ac.id
            LEFT JOIN journal_entry_items jei ON coa.id = jei.account_id
            LEFT JOIN journal_entries je ON jei.journal_entry_id = je.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
            GROUP BY coa.id, coa.account_code, coa.account_name, coa.normal_balance, coa.opening_balance, coa.account_type_id, ac.type, at.code
            ORDER BY CAST(coa.account_code AS UNSIGNED) ASC, coa.account_code ASC
        ", [$asOfDate, $asOfDate, $asOfDate, $asOfDate, creatorId()]);
    }

    public function getAccountSection($accountCode, $account = null)
    {
        // 1. Dynamic category / type resolution if available
        if ($account && is_object($account)) {
            $catType = strtolower($account->category_type ?? ($account->accountType->category->type ?? ''));
            $typeCode = strtoupper($account->type_code ?? ($account->accountType->code ?? ''));

            if ($catType === 'assets') {
                if ($typeCode === 'CA' || str_contains($typeCode, 'CURRENT')) {
                    return ['section_type' => 'assets', 'sub_section' => 'current_assets'];
                } elseif ($typeCode === 'FA' || str_contains($typeCode, 'FIXED')) {
                    return ['section_type' => 'assets', 'sub_section' => 'fixed_assets'];
                }
                return ['section_type' => 'assets', 'sub_section' => 'other_assets'];
            } elseif ($catType === 'liabilities') {
                if ($typeCode === 'CL' || str_contains($typeCode, 'CURRENT')) {
                    return ['section_type' => 'liabilities', 'sub_section' => 'current_liabilities'];
                }
                return ['section_type' => 'liabilities', 'sub_section' => 'long_term_liabilities'];
            } elseif ($catType === 'equity') {
                return ['section_type' => 'equity', 'sub_section' => 'equity'];
            } elseif ($catType === 'revenue' || $catType === 'expenses') {
                return ['section_type' => 'other', 'sub_section' => 'other'];
            }
        }

        // 2. Numeric range fallback
        $code = intval($accountCode);

        // Assets (1000-1999)
        if ($code >= 1000 && $code <= 1399) {
            return ['section_type' => 'assets', 'sub_section' => 'current_assets'];
        } elseif ($code >= 1400 && $code <= 1599) {
            return ['section_type' => 'assets', 'sub_section' => 'other_assets'];
        } elseif ($code >= 1600 && $code <= 1999) {
            return ['section_type' => 'assets', 'sub_section' => 'fixed_assets'];
        }

        // Liabilities (2000-2999)
        elseif ($code >= 2000 && $code <= 2499) {
            return ['section_type' => 'liabilities', 'sub_section' => 'current_liabilities'];
        } elseif ($code >= 2500 && $code <= 2999) {
            return ['section_type' => 'liabilities', 'sub_section' => 'long_term_liabilities'];
        }

        // Equity (3000-3999)
        elseif ($code >= 3000 && $code <= 3999) {
            return ['section_type' => 'equity', 'sub_section' => 'equity'];
        }

        // Revenue (4000-4999) and Expense (5000-5999) - excluded from balance sheet
        elseif ($code >= 4000 && $code <= 5999) {
            return ['section_type' => 'other', 'sub_section' => 'other'];
        }

        return ['section_type' => 'other', 'sub_section' => 'other'];
    }

    public function generateBalanceSheet($date, $financialYear, $ethiopianFiscalYear = null, $ethiopianQuarter = null)
    {
        return DB::transaction(function () use ($date, $financialYear, $ethiopianFiscalYear, $ethiopianQuarter) {
            // 1. Create main balance sheet record
            $balanceSheet = BalanceSheet::create([
                'balance_sheet_date' => $date,
                'financial_year' => $financialYear,
                'ethiopian_fiscal_year' => $ethiopianFiscalYear,
                'ethiopian_quarter' => $ethiopianQuarter,
                'status' => 'draft',
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            // 2. Get all accounts with balances as of the specified date
            $accounts = $this->calculateAllAccountBalances($date);

            $totalAssets = 0;
            $totalLiabilities = 0;
            $totalEquity = 0;

            // 3. Calculate net income from revenue/expense accounts as of date
            $netIncome = $this->calculateNetIncome($date);

            // 4. Get Retained Earnings account
            $retainedEarningsAccount = $this->getOrCreateRetainedEarningsAccount();
            $retainedEarningsId = $retainedEarningsAccount ? $retainedEarningsAccount->id : null;

            // 5. Create balance sheet items for each account
            foreach ($accounts as $account) {
                if (abs((float) $account->current_balance) > 0.01) {
                    $sectionInfo = $this->getAccountSection($account->account_code, $account);

                    // Skip revenue/expense accounts and Retained Earnings (added separately)
                    if ($sectionInfo['section_type'] !== 'other' && $account->id != $retainedEarningsId) {
                        $amount = (float) $account->current_balance;

                        BalanceSheetItem::create([
                            'balance_sheet_id' => $balanceSheet->id,
                            'account_id' => $account->id,
                            'section_type' => $sectionInfo['section_type'],
                            'sub_section' => $sectionInfo['sub_section'],
                            'amount' => $amount,
                            'creator_id' => Auth::id(),
                            'created_by' => creatorId(),
                        ]);

                        if ($sectionInfo['section_type'] == 'assets') {
                            $totalAssets += $amount;
                        } elseif ($sectionInfo['section_type'] == 'liabilities') {
                            $totalLiabilities += $amount;
                        } elseif ($sectionInfo['section_type'] == 'equity') {
                            $totalEquity += $amount;
                        }
                    }
                }
            }

            // 6. Add Retained Earnings with net income
            if ($retainedEarningsAccount) {
                $retainedEarningsCalculatedBalance = 0;
                foreach ($accounts as $acc) {
                    if ($acc->id == $retainedEarningsAccount->id) {
                        $retainedEarningsCalculatedBalance = (float) $acc->current_balance;
                        break;
                    }
                }

                $retainedEarningsBalance = $retainedEarningsCalculatedBalance + $netIncome;

                if (abs($retainedEarningsBalance) > 0.01) {
                    BalanceSheetItem::create([
                        'balance_sheet_id' => $balanceSheet->id,
                        'account_id' => $retainedEarningsAccount->id,
                        'section_type' => 'equity',
                        'sub_section' => 'equity',
                        'amount' => $retainedEarningsBalance,
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);

                    $totalEquity += $retainedEarningsBalance;
                }
            }

            // 7. Update balance sheet totals
            $balanceSheet->update([
                'total_assets' => round($totalAssets, 2),
                'total_liabilities' => round($totalLiabilities, 2),
                'total_equity' => round($totalEquity, 2),
                'is_balanced' => (abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01),
            ]);

            return $balanceSheet->id;
        });
    }

    public function calculateNetIncome($asOfDate)
    {
        $revenueExpenseAccounts = DB::select("
            SELECT
                coa.account_code,
                ac.type as category_type,
                CASE
                    WHEN coa.normal_balance = 'debit' THEN
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date <= ? AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date <= ? AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0)
                    ELSE
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date <= ? AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date <= ? AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0)
                END as current_balance
            FROM chart_of_accounts coa
            LEFT JOIN account_types at ON coa.account_type_id = at.id
            LEFT JOIN account_categories ac ON at.category_id = ac.id
            LEFT JOIN opening_balances ob ON coa.id = ob.account_id
                AND ob.created_by = coa.created_by
                AND ob.id = (SELECT MAX(id) FROM opening_balances WHERE account_id = coa.id AND created_by = coa.created_by)
            LEFT JOIN journal_entry_items jei ON coa.id = jei.account_id
            LEFT JOIN journal_entries je ON jei.journal_entry_id = je.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
              AND (
                  ac.type IN ('revenue', 'expenses')
                  OR (CAST(coa.account_code AS UNSIGNED) >= 4000 AND CAST(coa.account_code AS UNSIGNED) <= 5999)
                  OR (coa.account_code >= '4000' AND coa.account_code <= '5999')
              )
            GROUP BY coa.id, coa.account_code, coa.account_name, coa.normal_balance, coa.opening_balance, ob.effective_date, ac.type
        ", [$asOfDate, $asOfDate, $asOfDate, $asOfDate, creatorId()]);

        $netIncome = 0;
        foreach ($revenueExpenseAccounts as $account) {
            $catType = $account->category_type;
            $numCode = (int) $account->account_code;

            if ($catType === 'revenue' || ($catType === null && $numCode >= 4000 && $numCode <= 4999)) {
                $netIncome += (float) $account->current_balance;
            } elseif ($catType === 'expenses' || ($catType === null && $numCode >= 5000 && $numCode <= 5999)) {
                $netIncome -= (float) $account->current_balance;
            }
        }

        return $netIncome;
    }

    public function getOrCreateRetainedEarningsAccount()
    {
        $companyId = function_exists('creatorId') ? creatorId() : 1;

        // 1. Check for account 3200
        $account = ChartOfAccount::where('account_code', '3200')
            ->where('created_by', $companyId)
            ->first();

        if ($account) {
            return $account;
        }

        // 2. Check by AccountType code 'RE' or name
        $account = ChartOfAccount::where('created_by', $companyId)
            ->whereHas('accountType', function ($q) {
                $q->where('code', 'RE')->orWhere('name', 'like', '%Retained Earnings%');
            })
            ->first();

        if ($account) {
            return $account;
        }

        // 3. Auto-provision Retained Earnings account
        return DB::transaction(function () use ($companyId) {
            $accountType = AccountType::where('code', 'RE')
                ->where('created_by', $companyId)
                ->first();

            if (!$accountType) {
                $equityCategory = AccountCategory::where('code', 'EQT')
                    ->where('created_by', $companyId)
                    ->first();

                $accountType = AccountType::create([
                    'category_id' => $equityCategory ? $equityCategory->id : 3,
                    'name' => 'Retained Earnings',
                    'code' => 'RE',
                    'normal_balance' => 'credit',
                    'description' => 'Accumulated profits retained in business',
                    'is_active' => true,
                    'is_system_type' => true,
                    'creator_id' => Auth::id() ?? $companyId,
                    'created_by' => $companyId,
                ]);
            }

            return ChartOfAccount::create([
                'account_code' => '3200',
                'account_name' => 'Retained Earnings',
                'level' => 1,
                'normal_balance' => 'credit',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'is_system_account' => true,
                'description' => 'Auto-provisioned Retained Earnings account',
                'account_type_id' => $accountType->id,
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);
        });
    }

    public function validateBalanceSheet($balanceSheetId)
    {
        $balanceSheet = BalanceSheet::where('id', $balanceSheetId)
            ->where('created_by', creatorId())
            ->first();

        if (!$balanceSheet) {
            return false;
        }

        $isBalanced = abs($balanceSheet->total_assets - ($balanceSheet->total_liabilities + $balanceSheet->total_equity)) < 0.01;
        $balanceSheet->update(['is_balanced' => $isBalanced]);

        return $isBalanced;
    }

    public function performYearEndClose($financialYear, $closingDate)
    {
        $companyId = function_exists('creatorId') ? creatorId() : 1;

        // Check if year-end close already performed
        $nextYear = (string) ((int) preg_replace('/[^0-9]/', '', $financialYear) + 1);
        $existingOpeningBalances = OpeningBalance::where('financial_year', $nextYear)
            ->where('created_by', $companyId)
            ->exists();

        if ($existingOpeningBalances) {
            throw new \Exception("Year-end close for {$financialYear} has already been performed.");
        }

        DB::transaction(function () use ($financialYear, $closingDate, $companyId, $nextYear) {
            // 1. Create closing journal entries for revenue/expense accounts
            $this->createClosingJournalEntries($financialYear, $closingDate);

            // 2. Get all account balances (after closing entries)
            $accounts = $this->calculateAllAccountBalances($closingDate);

            // 3. Create opening balances for next year
            $nextYearStartDate = date('Y-m-d', strtotime($closingDate . ' +1 day'));

            foreach ($accounts as $account) {
                $sectionInfo = $this->getAccountSection($account->account_code, $account);

                // Only create opening balances for balance sheet accounts (assets, liabilities, equity)
                if ($sectionInfo['section_type'] !== 'other') {
                    $openingBalance = (float) $account->current_balance;

                    if (abs($openingBalance) > 0.01) {
                        OpeningBalance::updateOrCreate(
                            [
                                'account_id' => $account->id,
                                'financial_year' => $nextYear,
                                'created_by' => $companyId,
                            ],
                            [
                                'opening_balance' => $openingBalance,
                                'balance_type' => ($openingBalance >= 0 && $account->normal_balance === 'debit') || ($openingBalance < 0 && $account->normal_balance === 'credit') ? 'debit' : 'credit',
                                'effective_date' => $nextYearStartDate,
                                'creator_id' => Auth::id() ?? $companyId,
                            ]
                        );
                    }
                }
            }

            // 4. Update chart_of_accounts opening_balance safely
            foreach ($accounts as $account) {
                ChartOfAccount::where('id', $account->id)
                    ->where('created_by', $companyId)
                    ->update(['opening_balance' => $account->current_balance]);
            }

            // 5. Integrate with AccountingPeriod: Lock the closed period if present
            if (class_exists(AccountingPeriod::class)) {
                try {
                    $period = AccountingPeriod::where('created_by', $companyId)
                        ->whereDate('start_date', '<=', $closingDate)
                        ->whereDate('end_date', '>=', $closingDate)
                        ->first();

                    if ($period) {
                        $period->update([
                            'status' => 'closed',
                            'locked_by' => Auth::id() ?? $companyId,
                            'locked_at' => now(),
                            'notes' => trim(($period->notes ?? '') . " Closed via Year-End Close for {$financialYear}."),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Non-breaking if accounting_periods table not present
                }
            }
        });
    }

    private function createClosingJournalEntries($financialYear, $closingDate)
    {
        $companyId = function_exists('creatorId') ? creatorId() : 1;
        $retainedEarningsAccount = $this->getOrCreateRetainedEarningsAccount();

        // Get revenue and expense accounts as of closing date
        $revenueExpenseAccounts = $this->calculateNetIncomeAccounts($closingDate);

        $totalRevenue = 0;
        $totalExpense = 0;
        $journalItems = [];

        foreach ($revenueExpenseAccounts as $account) {
            $curBal = (float) $account->current_balance;
            if (abs($curBal) > 0.01) {
                $code = (int) $account->account_code;
                $catType = $account->category_type ?? null;

                if ($catType === 'revenue' || ($catType === null && $code >= 4000 && $code <= 4999)) {
                    // Revenue accounts - debit to close
                    $totalRevenue += $curBal;
                    $journalItems[] = [
                        'account_id' => $account->id,
                        'description' => 'Close revenue account: ' . $account->account_name,
                        'debit_amount' => $curBal,
                        'credit_amount' => 0,
                    ];
                } elseif ($catType === 'expenses' || ($catType === null && $code >= 5000 && $code <= 5999)) {
                    // Expense accounts - credit to close
                    $totalExpense += $curBal;
                    $journalItems[] = [
                        'account_id' => $account->id,
                        'description' => 'Close expense account: ' . $account->account_name,
                        'debit_amount' => 0,
                        'credit_amount' => $curBal,
                    ];
                }
            }
        }

        if (!empty($journalItems)) {
            $netIncome = $totalRevenue - $totalExpense;

            $totalDebits = $totalRevenue + ($netIncome < 0 ? abs($netIncome) : 0);
            $totalCredits = $totalExpense + ($netIncome >= 0 ? $netIncome : 0);

            $journalEntry = JournalEntry::create([
                'journal_date' => $closingDate,
                'entry_type' => 'automatic',
                'reference_type' => 'year_end_close',
                'reference_id' => null,
                'description' => 'Year-end closing entries for ' . $financialYear,
                'total_debit' => round($totalDebits, 2),
                'total_credit' => round($totalCredits, 2),
                'status' => 'posted',
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);

            foreach ($journalItems as $item) {
                JournalEntryItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $item['account_id'],
                    'description' => $item['description'],
                    'debit_amount' => round($item['debit_amount'], 2),
                    'credit_amount' => round($item['credit_amount'], 2),
                    'creator_id' => Auth::id() ?? $companyId,
                    'created_by' => $companyId,
                ]);
            }

            // Transfer net income to retained earnings
            JournalEntryItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $retainedEarningsAccount->id,
                'description' => 'Transfer net income to retained earnings',
                'debit_amount' => $netIncome >= 0 ? 0 : round(abs($netIncome), 2),
                'credit_amount' => $netIncome >= 0 ? round($netIncome, 2) : 0,
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);

            // Sync account current balances with journal posting
            $this->updateClosingAccountBalances($journalEntry);
        }
    }

    private function calculateNetIncomeAccounts($asOfDate)
    {
        return DB::select("
            SELECT
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.normal_balance,
                coa.current_balance,
                ac.type as category_type
            FROM chart_of_accounts coa
            LEFT JOIN account_types at ON coa.account_type_id = at.id
            LEFT JOIN account_categories ac ON at.category_id = ac.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
              AND (
                  ac.type IN ('revenue', 'expenses')
                  OR (CAST(coa.account_code AS UNSIGNED) >= 4000 AND CAST(coa.account_code AS UNSIGNED) <= 5999)
                  OR (coa.account_code >= '4000' AND coa.account_code <= '5999')
              )
            ORDER BY CAST(coa.account_code AS UNSIGNED) ASC, coa.account_code ASC
        ", [creatorId()]);
    }

    public function finalizeBalanceSheet($balanceSheetId)
    {
        $balanceSheet = BalanceSheet::where('id', $balanceSheetId)
            ->where('created_by', creatorId())
            ->first();

        if (!$balanceSheet) {
            throw new \Exception("Balance sheet not found");
        }

        if (!$this->validateBalanceSheet($balanceSheetId)) {
            throw new \Exception("Balance sheet is not balanced. Cannot finalize.");
        }

        $balanceSheet->update(['status' => 'finalized']);

        return $balanceSheet;
    }

    private function updateClosingAccountBalances($journalEntry)
    {
        $journalEntry->load('items.account');

        foreach ($journalEntry->items as $item) {
            $account = $item->account;
            if (!$account) continue;

            $debitAmount = (float) $item->debit_amount;
            $creditAmount = (float) $item->credit_amount;

            if ($account->normal_balance === 'debit') {
                $account->current_balance += ($debitAmount - $creditAmount);
            } else {
                $account->current_balance += ($creditAmount - $debitAmount);
            }

            $account->save();
        }
    }
}
