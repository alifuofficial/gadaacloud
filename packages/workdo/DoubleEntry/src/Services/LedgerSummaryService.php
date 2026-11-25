<?php

namespace Workdo\DoubleEntry\Services;

use Workdo\Account\Models\JournalEntryItem;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;

class LedgerSummaryService
{
    use DoubleEntryDateHelpers;

    public function getAllLedgerEntries(
        $fromDate = null,
        $toDate = null,
        $accountId = null,
        $paginate = true,
        $ethFiscalYear = null,
        $ethQuarter = null
    ) {
        $dates = $this->resolveDateRange($fromDate, $toDate, $ethFiscalYear, $ethQuarter);
        $resolvedFrom = $dates['from_date'];
        $resolvedTo = $dates['to_date'];

        $sortMap = [
            'journal_date' => 'journal_entries.journal_date',
            'account_code' => 'chart_of_accounts.account_code',
            'account_name' => 'chart_of_accounts.account_name',
            'debit_amount' => 'journal_entry_items.debit_amount',
            'credit_amount' => 'journal_entry_items.credit_amount',
            'id' => 'journal_entry_items.id',
        ];

        $sortParam = request('sort');
        $sortColumn = ($sortParam && isset($sortMap[$sortParam])) ? $sortMap[$sortParam] : null;
        $sortDirection = strtolower(request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = JournalEntryItem::select(
                'journal_entry_items.id',
                'journal_entries.journal_date',
                'journal_entries.reference_type',
                'journal_entries.description as journal_description',
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
            ->when($resolvedFrom, fn($q) => $q->where('journal_entries.journal_date', '>=', $resolvedFrom))
            ->when($resolvedTo, fn($q) => $q->where('journal_entries.journal_date', '<=', $resolvedTo))
            ->when($accountId, fn($q) => $q->where('journal_entry_items.account_id', $accountId))
            ->when(request('search'), fn($q) => $q->where(function($query) {
                $search = request('search');
                $query->where('chart_of_accounts.account_code', 'like', '%' . $search . '%')
                      ->orWhere('chart_of_accounts.account_name', 'like', '%' . $search . '%')
                      ->orWhere('journal_entry_items.description', 'like', '%' . $search . '%')
                      ->orWhere('journal_entries.description', 'like', '%' . $search . '%');
            }));

        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('journal_entries.journal_date', 'desc')->orderBy('journal_entry_items.id', 'desc');
        }

        if ($paginate) {
            $perPage = min(max((int) request('per_page', 10), 1), 100);
            return $query->paginate($perPage)->withQueryString();
        }

        return $query->get();
    }
}
