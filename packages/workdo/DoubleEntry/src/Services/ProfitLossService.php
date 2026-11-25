<?php

namespace Workdo\DoubleEntry\Services;

use Workdo\Account\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;

class ProfitLossService
{
    use DoubleEntryDateHelpers;

    public function generateProfitLoss($fromDate = null, $toDate = null, $ethFiscalYear = null, $ethQuarter = null)
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
                ac.type as category_type,
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
            ORDER BY CAST(coa.account_code AS UNSIGNED) ASC, coa.account_code ASC
        ", [$resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, $resolvedFrom, $resolvedTo, creatorId()]);

        $revenue = [];
        $expenses = [];
        $totalRevenue = 0;
        $totalExpenses = 0;

        foreach ($accounts as $account) {
            $balance = (float) $account->balance;
            if (abs($balance) > 0.01) {
                $code = (int) $account->account_code;
                $catType = $account->category_type;

                if ($catType === 'revenue' || ($catType === null && $code >= 4000 && $code <= 4999)) {
                    $revenue[] = $account;
                    $totalRevenue += $balance;
                } elseif ($catType === 'expenses' || ($catType === null && $code >= 5000 && $code <= 5999)) {
                    $expenses[] = $account;
                    $totalExpenses += $balance;
                }
            }
        }

        $netProfit = $totalRevenue - $totalExpenses;

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
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
