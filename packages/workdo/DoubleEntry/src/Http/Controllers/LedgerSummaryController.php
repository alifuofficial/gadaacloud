<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\LedgerSummaryService;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\DoubleEntry\Traits\DoubleEntryControllerHelpers;

class LedgerSummaryController extends Controller
{
    use DoubleEntryControllerHelpers;

    protected $ledgerSummaryService;

    public function __construct(LedgerSummaryService $ledgerSummaryService)
    {
        $this->ledgerSummaryService = $ledgerSummaryService;
    }

    public function index(Request $request)
    {
        if (Auth::user()->can('manage-ledger-summary')) {
            // IDOR Protection: if account_id is supplied, verify it belongs to tenant
            $accountId = $request->account_id;
            if ($accountId) {
                $account = ChartOfAccount::where('id', $accountId)
                    ->where('created_by', creatorId())
                    ->first();

                if (!$account) {
                    $accountId = null;
                }
            }

            $entries = $this->ledgerSummaryService->getAllLedgerEntries(
                $request->from_date,
                $request->to_date,
                $accountId,
                true,
                $request->ethiopian_fiscal_year,
                $request->ethiopian_quarter
            );

            $accounts = ChartOfAccount::query()
                ->where('created_by', creatorId())
                ->where('is_active', 1)
                ->orderBy('account_code', 'asc')
                ->select('id', 'account_code', 'account_name')
                ->get();

            return Inertia::render('DoubleEntry/LedgerSummary/Index', [
                'entries' => $entries,
                'accounts' => $accounts,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function print(Request $request)
    {
        if (Auth::user()->can('print-ledger-summary')) {
            $accountId = $request->account_id;
            $selectedAccount = null;

            if ($accountId) {
                $selectedAccount = ChartOfAccount::where('id', $accountId)
                    ->where('created_by', creatorId())
                    ->first();
                if (!$selectedAccount) {
                    $accountId = null;
                }
            }

            $entries = $this->ledgerSummaryService->getAllLedgerEntries(
                $request->from_date,
                $request->to_date,
                $accountId,
                false,
                $request->ethiopian_fiscal_year,
                $request->ethiopian_quarter
            );

            $accounts = ChartOfAccount::query()
                ->where('created_by', creatorId())
                ->where('is_active', 1)
                ->orderBy('account_code', 'asc')
                ->select('id', 'account_code', 'account_name')
                ->get();

            return Inertia::render('DoubleEntry/LedgerSummary/Print', [
                'entries' => $entries,
                'selectedAccount' => $selectedAccount,
                'filters' => [
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'account_id' => $accountId,
                    'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
                ],
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }
}
