<?php

namespace Workdo\DoubleEntry\Services;

use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\OpeningBalance;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;
use Illuminate\Support\Facades\DB;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;

class ReportService
{
    use DoubleEntryDateHelpers;

    public function getGeneralLedger($filters = [])
    {
        $dates = $this->resolveDateRange(
            $filters['from_date'] ?? null,
            $filters['to_date'] ?? null,
            $filters['ethiopian_fiscal_year'] ?? null,
            $filters['ethiopian_quarter'] ?? null
        );

        $accountId = $filters['account_id'] ?? null;
        $fromDate = $dates['from_date'];
        $toDate = $dates['to_date'];

        $query = JournalEntryItem::select(
                'journal_entry_items.id',
                'journal_entries.journal_date',
                'journal_entries.reference_type',
                'journal_entries.reference_id',
                'journal_entry_items.description',
                'journal_entry_items.debit_amount',
                'journal_entry_items.credit_amount',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name'
            )
            ->join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_items.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.created_by', creatorId())
            ->where('chart_of_accounts.created_by', creatorId())
            ->when($accountId, fn($q) => $q->where('journal_entry_items.account_id', $accountId))
            ->when($fromDate, fn($q) => $q->where('journal_entries.journal_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('journal_entries.journal_date', '<=', $toDate))
            ->orderBy('journal_entries.journal_date', 'asc')
            ->orderBy('journal_entry_items.id', 'asc');

        $entries = $query->get();

        $openingBalance = 0;
        if ($accountId && $fromDate) {
            $openingBalance = $this->getOpeningBalance($accountId, $fromDate);
        }

        $runningBalance = $openingBalance;
        $transactions = $entries->map(function ($entry) use (&$runningBalance) {
            $runningBalance += ((float) $entry->debit_amount - (float) $entry->credit_amount);
            return [
                'id' => $entry->id,
                'date' => $entry->journal_date,
                'account_code' => $entry->account_code,
                'account_name' => $entry->account_name,
                'description' => $entry->description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'debit' => (float) $entry->debit_amount,
                'credit' => (float) $entry->credit_amount,
                'balance' => round($runningBalance, 2),
            ];
        });

        return [
            'opening_balance' => round($openingBalance, 2),
            'transactions' => $transactions,
            'closing_balance' => round($runningBalance, 2),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $dates['ethiopian_fiscal_year'],
            'ethiopian_quarter' => $dates['ethiopian_quarter'],
            'fiscal_year_label' => $dates['fiscal_year_label'],
            'dual_from' => $dates['dual_from'],
            'dual_to' => $dates['dual_to'],
        ];
    }

    public function getOpeningBalance($accountId, $date)
    {
        $openingBalance = OpeningBalance::where('account_id', $accountId)
            ->where('created_by', creatorId())
            ->first();

        $balance = $openingBalance ? ((float) ($openingBalance->opening_balance ?? ($openingBalance->debit_amount ?? 0) - ($openingBalance->credit_amount ?? 0))) : 0;

        $priorTransactions = JournalEntryItem::join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_items.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.created_by', creatorId())
            ->where('journal_entries.journal_date', '<', $date)
            ->select(
                DB::raw('SUM(journal_entry_items.debit_amount) as total_debit'),
                DB::raw('SUM(journal_entry_items.credit_amount) as total_credit')
            )
            ->first();

        if ($priorTransactions) {
            $balance += ((float) ($priorTransactions->total_debit ?? 0) - (float) ($priorTransactions->total_credit ?? 0));
        }

        return $balance;
    }

    public function getJournalEntries($filters = [])
    {
        $dates = $this->resolveDateRange(
            $filters['from_date'] ?? null,
            $filters['to_date'] ?? null,
            $filters['ethiopian_fiscal_year'] ?? null,
            $filters['ethiopian_quarter'] ?? null
        );

        $query = JournalEntry::with(['items.account'])
            ->where('created_by', creatorId())
            ->when($dates['from_date'], fn($q, $date) => $q->where('journal_date', '>=', $date))
            ->when($dates['to_date'], fn($q, $date) => $q->where('journal_date', '<=', $date))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $query->map(function ($entry) {
            $totalDebit = (float) $entry->items->sum('debit_amount');
            $totalCredit = (float) $entry->items->sum('credit_amount');

            return [
                'id' => $entry->id,
                'journal_number' => $entry->journal_number ?? 'JE-' . $entry->id,
                'date' => $entry->journal_date,
                'reference_type' => $entry->reference_type,
                'description' => $entry->description,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'status' => $entry->status,
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
                'items' => $entry->items->map(fn($item) => [
                    'account_code' => $item->account->account_code ?? '',
                    'account_name' => $item->account->account_name ?? '',
                    'description' => $item->description,
                    'debit' => (float) $item->debit_amount,
                    'credit' => (float) $item->credit_amount,
                ]),
            ];
        });
    }

    public function getAccountBalances($filters = [])
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');
        if (!empty($filters['ethiopian_fiscal_year'])) {
            $dates = $this->resolveDateRange(null, null, $filters['ethiopian_fiscal_year']);
            $asOfDate = $dates['to_date'];
        }

        $accountType = $filters['account_type'] ?? null;
        $showZeroBalances = $filters['show_zero_balances'] ?? false;

        $accounts = ChartOfAccount::with(['accountType.category'])
            ->where('created_by', creatorId())
            ->where('is_active', 1)
            ->orderByRaw('CAST(account_code AS UNSIGNED) ASC, account_code ASC')
            ->get();

        if ($accountType && trim($accountType)) {
            $accounts = $accounts->filter(function($account) use ($accountType) {
                return $this->getAccountTypeLabel($account) === $accountType;
            });
        }

        $grouped = [];
        $totals = ['debit' => 0, 'credit' => 0, 'net' => 0];

        foreach ($accounts as $account) {
            $balance = (float) $this->calculateAccountBalance($account->id, $asOfDate);
            
            if (!$showZeroBalances && abs($balance) < 0.01) {
                continue;
            }

            $debit = $balance > 0 ? $balance : 0;
            $credit = $balance < 0 ? abs($balance) : 0;

            $type = $this->getAccountTypeLabel($account);

            if (!isset($grouped[$type])) {
                $grouped[$type] = ['accounts' => [], 'subtotal_debit' => 0, 'subtotal_credit' => 0, 'subtotal_net' => 0];
            }

            $grouped[$type]['accounts'][] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'net_balance' => round($balance, 2),
            ];

            $grouped[$type]['subtotal_debit'] += $debit;
            $grouped[$type]['subtotal_credit'] += $credit;
            $grouped[$type]['subtotal_net'] += $balance;

            $totals['debit'] += $debit;
            $totals['credit'] += $credit;
            $totals['net'] += $balance;
        }

        return [
            'grouped' => $grouped,
            'totals' => [
                'debit' => round($totals['debit'], 2),
                'credit' => round($totals['credit'], 2),
                'net' => round($totals['net'], 2),
            ],
            'as_of_date' => $asOfDate,
            'dual_as_of' => $this->formatDualDate($asOfDate),
        ];
    }

    private function calculateAccountBalance($accountId, $asOfDate)
    {
        $openingBalance = OpeningBalance::where('account_id', $accountId)
            ->where('created_by', creatorId())
            ->first();

        $balance = $openingBalance ? ((float) ($openingBalance->opening_balance ?? ($openingBalance->debit_amount ?? 0) - ($openingBalance->credit_amount ?? 0))) : 0;

        $transactions = JournalEntryItem::join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_items.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.created_by', creatorId())
            ->where('journal_entries.journal_date', '<=', $asOfDate)
            ->select(
                DB::raw('SUM(journal_entry_items.debit_amount) as total_debit'),
                DB::raw('SUM(journal_entry_items.credit_amount) as total_credit')
            )
            ->first();

        if ($transactions) {
            $balance += ((float) ($transactions->total_debit ?? 0) - (float) ($transactions->total_credit ?? 0));
        }

        return $balance;
    }

    public function getAccountTypeLabel($account)
    {
        // 1. Dynamic check via Category if relationship loaded
        if (is_object($account) && isset($account->accountType->category->type)) {
            $catType = strtolower($account->accountType->category->type);
            if ($catType === 'assets') return 'Assets';
            if ($catType === 'liabilities') return 'Liabilities';
            if ($catType === 'equity') return 'Equity';
            if ($catType === 'revenue') return 'Revenue';
            if ($catType === 'expenses') return 'Expenses';
        }

        // 2. Numeric range fallback
        $accountCode = is_object($account) ? ($account->account_code ?? '') : $account;
        $code = (int) $accountCode;
        if ($code >= 1000 && $code < 2000) return 'Assets';
        if ($code >= 2000 && $code < 3000) return 'Liabilities';
        if ($code >= 3000 && $code < 4000) return 'Equity';
        if ($code >= 4000 && $code < 5000) return 'Revenue';
        if ($code >= 5000 && $code < 6000) return 'Expenses';
        return 'Other';
    }

    public function getCashFlow($filters = [])
    {
        $dates = $this->resolveDateRange(
            $filters['from_date'] ?? null,
            $filters['to_date'] ?? null,
            $filters['ethiopian_fiscal_year'] ?? null,
            $filters['ethiopian_quarter'] ?? null
        );

        $fromDate = $dates['from_date'];
        $toDate = $dates['to_date'];

        $cashAccounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', 1)
            ->where(function($q) {
                $q->whereBetween('account_code', [1000, 1099])
                  ->orWhere('account_code', 'like', '101%');
            })
            ->get();

        $beginningCash = 0;
        foreach ($cashAccounts as $account) {
            $beginningCash += (float) $this->calculateAccountBalance($account->id, $fromDate);
        }

        $operating = (float) $this->getCashFlowByCategory($fromDate, $toDate, 4000, 5999);
        $investing = (float) $this->getCashFlowByCategory($fromDate, $toDate, 1100, 1999);
        $financing = (float) $this->getCashFlowByCategory($fromDate, $toDate, 2000, 3999);

        $netCashFlow = $operating + $investing + $financing;
        $endingCash = $beginningCash + $netCashFlow;

        return [
            'beginning_cash' => round($beginningCash, 2),
            'operating' => round($operating, 2),
            'investing' => round($investing, 2),
            'financing' => round($financing, 2),
            'net_cash_flow' => round($netCashFlow, 2),
            'ending_cash' => round($endingCash, 2),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $dates['ethiopian_fiscal_year'],
            'ethiopian_quarter' => $dates['ethiopian_quarter'],
            'fiscal_year_label' => $dates['fiscal_year_label'],
            'dual_from' => $dates['dual_from'],
            'dual_to' => $dates['dual_to'],
        ];
    }

    private function getCashFlowByCategory($fromDate, $toDate, $codeStart, $codeEnd)
    {
        $accounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', 1)
            ->whereBetween('account_code', [$codeStart, $codeEnd])
            ->get();

        $total = 0;
        foreach ($accounts as $account) {
            $balance = (float) $this->getAccountBalanceForPeriod($account->id, $fromDate, $toDate);
            $total += $balance;
        }

        return $total;
    }

    private function getAccountBalanceForPeriod($accountId, $fromDate, $toDate)
    {
        $transactions = JournalEntryItem::join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_items.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.created_by', creatorId())
            ->whereBetween('journal_entries.journal_date', [$fromDate, $toDate])
            ->select(
                DB::raw('SUM(journal_entry_items.credit_amount) as total_credit'),
                DB::raw('SUM(journal_entry_items.debit_amount) as total_debit')
            )
            ->first();

        return (float) (($transactions->total_credit ?? 0) - ($transactions->total_debit ?? 0));
    }

    public function getExpenseReport($filters = [])
    {
        $dates = $this->resolveDateRange(
            $filters['from_date'] ?? null,
            $filters['to_date'] ?? null,
            $filters['ethiopian_fiscal_year'] ?? null,
            $filters['ethiopian_quarter'] ?? null
        );

        $fromDate = $dates['from_date'];
        $toDate = $dates['to_date'];

        $accounts = ChartOfAccount::with(['accountType.category'])
            ->where('created_by', creatorId())
            ->where('is_active', 1)
            ->where(function($q) {
                $q->whereBetween('account_code', [5000, 5999])
                  ->orWhereHas('accountType.category', fn($cq) => $cq->where('type', 'expenses'));
            })
            ->orderBy('account_code')
            ->get();

        $expenses = [];
        $totalExpenses = 0;

        foreach ($accounts as $account) {
            $balance = (float) $this->getAccountBalanceForPeriod($account->id, $fromDate, $toDate);
            
            if (abs($balance) < 0.01) {
                continue;
            }

            $amount = abs($balance);
            $expenses[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'amount' => round($amount, 2),
            ];

            $totalExpenses += $amount;
        }

        usort($expenses, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'expenses' => $expenses,
            'total_expenses' => round($totalExpenses, 2),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $dates['ethiopian_fiscal_year'],
            'ethiopian_quarter' => $dates['ethiopian_quarter'],
            'fiscal_year_label' => $dates['fiscal_year_label'],
            'dual_from' => $dates['dual_from'],
            'dual_to' => $dates['dual_to'],
        ];
    }
}
