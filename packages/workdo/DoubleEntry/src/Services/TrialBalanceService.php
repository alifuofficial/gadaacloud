<?php

namespace Workdo\DoubleEntry\Services;

use Illuminate\Support\Facades\DB;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;

class TrialBalanceService
{
    use DoubleEntryDateHelpers;

    public function generateTrialBalance($fromDate = null, $toDate = null, $ethFiscalYear = null, $ethQuarter = null)
    {
        $dates = $this->resolveDateRange($fromDate, $toDate, $ethFiscalYear, $ethQuarter);
        $resolvedFrom = $dates['from_date'];
        $resolvedTo = $dates['to_date'];

        $accounts = DB::select("
            SELECT
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.normal_balance,
                CASE
                    WHEN coa.normal_balance = 'debit' THEN
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0)
                    ELSE
                        COALESCE(coa.opening_balance, 0) +
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN (ob.effective_date IS NULL OR je.journal_date >= ob.effective_date) AND je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0)
                END as balance
            FROM chart_of_accounts coa
            LEFT JOIN opening_balances ob ON coa.id = ob.account_id
                AND ob.created_by = coa.created_by
                AND ob.id = (SELECT MAX(id) FROM opening_balances WHERE account_id = coa.id AND created_by = coa.created_by)
            LEFT JOIN journal_entry_items jei ON coa.id = jei.account_id
            LEFT JOIN journal_entries je ON jei.journal_entry_id = je.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
            GROUP BY coa.id, coa.account_code, coa.account_name, coa.normal_balance, coa.opening_balance, ob.effective_date
            ORDER BY CAST(coa.account_code AS UNSIGNED) ASC, coa.account_code ASC
        ", [$resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, creatorId()]);

        $totalDebit = 0;
        $totalCredit = 0;
        $accountsList = [];

        foreach ($accounts as $account) {
            $balance = (float) $account->balance;

            if (abs($balance) > 0.01) {
                $debit = 0;
                $credit = 0;

                if ($balance > 0) {
                    if ($account->normal_balance === 'debit') {
                        $debit = $balance;
                        $totalDebit += $debit;
                    } else {
                        $credit = $balance;
                        $totalCredit += $credit;
                    }
                } else {
                    // Negative balance goes to opposite side
                    if ($account->normal_balance === 'debit') {
                        $credit = abs($balance);
                        $totalCredit += $credit;
                    } else {
                        $debit = abs($balance);
                        $totalDebit += $debit;
                    }
                }

                $accountsList[] = [
                    'id' => $account->id,
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                ];
            }
        }

        return [
            'accounts' => $accountsList,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'from_date' => $resolvedFrom,
            'to_date' => $resolvedTo,
            'ethiopian_fiscal_year' => $dates['ethiopian_fiscal_year'],
            'ethiopian_quarter' => $dates['ethiopian_quarter'],
            'fiscal_year_label' => $dates['fiscal_year_label'],
            'dual_from' => $dates['dual_from'],
            'dual_to' => $dates['dual_to'],
        ];
    }
}
