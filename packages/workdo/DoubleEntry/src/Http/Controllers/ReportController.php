<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\ReportService;
use Workdo\Account\Models\ChartOfAccount;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        if (Auth::user()->can('manage-double-entry-reports')) {
            $currentYear = date('Y');
            $financialYear = [
                'year_start_date' => "$currentYear-01-01",
                'year_end_date' => "$currentYear-12-31",
            ];

            return Inertia::render('DoubleEntry/Reports/Index', [
                'financialYear' => $financialYear,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function generalLedger(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-general-ledger')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $accounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currentYear = date('Y');
        $financialYear = [
            'year_start_date' => "$currentYear-01-01",
            'year_end_date' => "$currentYear-12-31",
        ];

        $firstAccount = $accounts->first();
        $accountId = $request->account_id ?: ($firstAccount ? $firstAccount->id : null);

        // IDOR Protection: ensure accountId belongs to current tenant
        $selectedAccount = null;
        if ($accountId) {
            $selectedAccount = ChartOfAccount::where('id', $accountId)
                ->where('created_by', creatorId())
                ->first();

            if (!$selectedAccount) {
                return response()->json(['error' => __('Account not found')], 404);
            }
        }

        $filters = [
            'account_id' => $selectedAccount ? $selectedAccount->id : null,
            'from_date' => $request->from_date ?: $financialYear['year_start_date'],
            'to_date' => $request->to_date ?: $financialYear['year_end_date'],
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ];

        $data = $selectedAccount ? $this->reportService->getGeneralLedger($filters) : null;

        return response()->json([
            'data' => $data,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'financialYear' => $financialYear,
        ]);
    }

    public function printGeneralLedger(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-general-ledger')) {
            return back()->with('error', __('Permission denied'));
        }

        $selectedAccount = null;
        if ($request->account_id) {
            $selectedAccount = ChartOfAccount::where('id', $request->account_id)
                ->where('created_by', creatorId())
                ->firstOrFail();
        }

        $filters = [
            'account_id' => $selectedAccount ? $selectedAccount->id : null,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ];

        $data = $this->reportService->getGeneralLedger($filters);

        return Inertia::render('DoubleEntry/Reports/Print/GeneralLedger', [
            'data' => $data,
            'selectedAccount' => $selectedAccount,
            'filters' => $filters,
        ]);
    }

    public function accountStatement(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-account-statement')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $accounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currentYear = date('Y');
        $financialYear = [
            'year_start_date' => "$currentYear-01-01",
            'year_end_date' => "$currentYear-12-31",
        ];

        $firstAccount = $accounts->first();
        $accountId = $request->account_id ?: ($firstAccount ? $firstAccount->id : null);

        // IDOR Protection: ensure accountId belongs to current tenant
        $selectedAccount = null;
        if ($accountId) {
            $selectedAccount = ChartOfAccount::where('id', $accountId)
                ->where('created_by', creatorId())
                ->first();

            if (!$selectedAccount) {
                return response()->json(['error' => __('Account not found')], 404);
            }
        }

        $filters = [
            'account_id' => $selectedAccount ? $selectedAccount->id : null,
            'from_date' => $request->from_date ?: $financialYear['year_start_date'],
            'to_date' => $request->to_date ?: $financialYear['year_end_date'],
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ];

        $data = $selectedAccount ? $this->reportService->getGeneralLedger($filters) : null;

        return response()->json([
            'data' => $data,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'financialYear' => $financialYear,
        ]);
    }

    public function printAccountStatement(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-account-statement')) {
            return back()->with('error', __('Permission denied'));
        }

        $selectedAccount = null;
        if ($request->account_id) {
            $selectedAccount = ChartOfAccount::where('id', $request->account_id)
                ->where('created_by', creatorId())
                ->firstOrFail();
        }

        $filters = [
            'account_id' => $selectedAccount ? $selectedAccount->id : null,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ];

        $data = $this->reportService->getGeneralLedger($filters);

        return Inertia::render('DoubleEntry/Reports/Print/AccountStatement', [
            'data' => $data,
            'selectedAccount' => $selectedAccount,
            'filters' => $filters,
        ]);
    }

    public function journalEntry(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-journal-entry')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getJournalEntries([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => $request->status,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return response()->json($data);
    }

    public function printJournalEntry(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-journal-entry')) {
            return back()->with('error', __('Permission denied'));
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getJournalEntries([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => $request->status,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/JournalEntry', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $request->status,
                'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            ],
        ]);
    }

    public function accountBalance(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-account-balance')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $currentYear = date('Y');
        $asOfDate = $request->as_of_date ?: "$currentYear-12-31";
        $accountType = $request->account_type;
        $showZeroBalances = $request->show_zero_balances === 'true';

        $data = $this->reportService->getAccountBalances([
            'as_of_date' => $asOfDate,
            'account_type' => $accountType,
            'show_zero_balances' => $showZeroBalances,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
        ]);

        return response()->json($data);
    }

    public function printAccountBalance(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-account-balance')) {
            return back()->with('error', __('Permission denied'));
        }

        $currentYear = date('Y');
        $asOfDate = $request->as_of_date ?: "$currentYear-12-31";
        $accountType = $request->account_type;
        $showZeroBalances = $request->show_zero_balances === 'true';

        $data = $this->reportService->getAccountBalances([
            'as_of_date' => $asOfDate,
            'account_type' => $accountType,
            'show_zero_balances' => $showZeroBalances,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/AccountBalance', [
            'data' => $data,
            'filters' => [
                'as_of_date' => $asOfDate,
                'account_type' => $accountType,
                'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            ],
        ]);
    }

    public function cashFlow(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-cash-flow')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getCashFlow([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return response()->json($data);
    }

    public function printCashFlow(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-cash-flow')) {
            return back()->with('error', __('Permission denied'));
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getCashFlow([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/CashFlow', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            ],
        ]);
    }

    public function expenseReport(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-expense-report')) {
            return response()->json(['error' => __('Permission denied')], 403);
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getExpenseReport([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return response()->json($data);
    }

    public function printExpenseReport(Request $request)
    {
        if (!Auth::user()->can('manage-double-entry-reports') && !Auth::user()->can('view-expense-report')) {
            return back()->with('error', __('Permission denied'));
        }

        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getExpenseReport([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            'ethiopian_quarter' => $request->ethiopian_quarter,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/ExpenseReport', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'ethiopian_fiscal_year' => $request->ethiopian_fiscal_year,
            ],
        ]);
    }
}
