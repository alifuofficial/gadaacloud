<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\ApproveExpense;
use Workdo\Account\Events\CreateExpense;
use Workdo\Account\Events\DestroyExpense;
use Workdo\Account\Events\PostExpense;
use Workdo\Account\Events\UpdateExpense;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreExpenseRequest;
use Workdo\Account\Http\Requests\UpdateExpenseRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Services\BankTransactionsService;
use Workdo\Account\Services\JournalService;
use Workdo\Account\Traits\AccountControllerHelpers;
use Workdo\Account\Traits\AccountDateHelpers;

class ExpenseController extends Controller
{
    use AccountControllerHelpers, AccountDateHelpers;

    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-expenses')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            $request->sort,
            ['expense_number', 'expense_date', 'amount', 'status', 'created_at'],
            'created_at',
            $request->direction ?? 'desc'
        );

        $query = Expense::with(['category:id,category_name', 'bankAccount:id,account_name', 'chartOfAccount:id,account_code,account_name', 'approvedBy:id,name'])
            ->select('id', 'expense_number', 'expense_date', 'category_id', 'bank_account_id', 'chart_of_account_id', 'amount', 'description', 'reference_number', 'status', 'approved_by', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-expenses')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-expenses')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($request->search) {
            $query->where('expense_number', 'like', '%' . $request->search . '%');
        }
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('expense_date', [$request->date_from, $request->date_to]);
        }
        if ($request->bank_account_id) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->sort) {
            $query->orderBy($sortCol, $sortDir);
        } else {
            $query->latest();
        }

        $expenses = $query->paginate($this->safePerPage(10, 100))->withQueryString();

        $categories = ExpenseCategories::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'category_name')
            ->get();

        $bankAccounts = BankAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'account_name')
            ->get();

        $chartOfAccounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->whereBetween('account_code', ['5000', '6999'])
            ->select('id', 'account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        return Inertia::render('Account/Expenses/Index', [
            'expenses'        => $expenses,
            'categories'      => $categories,
            'bankAccounts'    => $bankAccounts,
            'chartOfAccounts' => $chartOfAccounts,
            'filters'         => $request->only(['search', 'category_id', 'status', 'date_from', 'date_to', 'bank_account_id']),
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        if (!Auth::user()->can('create-expenses')) {
            return redirect()->route('account.expenses.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!BankAccount::where('id', $validated['bank_account_id'])->where('created_by', $companyId)->exists()
            || !ExpenseCategories::where('id', $validated['category_id'])->where('created_by', $companyId)->exists()
            || !ChartOfAccount::where('id', $validated['chart_of_account_id'])->where('created_by', $companyId)->exists()
        ) {
            return redirect()->back()->with('error', __('Invalid category, bank account, or GL account selected.'));
        }

        $expense = new Expense();
        $expense->expense_date = $validated['expense_date'];
        $expense->category_id = $validated['category_id'];
        $expense->bank_account_id = $validated['bank_account_id'];
        $expense->chart_of_account_id = $validated['chart_of_account_id'];
        $expense->amount = $validated['amount'];
        $expense->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $expense->reference_number = $validated['reference_number'] ?? null;
        $expense->status = 'draft';
        $expense->creator_id = Auth::id();
        $expense->created_by = $companyId;
        $expense->save();

        CreateExpense::dispatch($request, $expense);

        return redirect()->route('account.expenses.index')->with('success', __('The expense has been created successfully.'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        if (!Auth::user()->can('edit-expenses') || $expense->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($expense->status != 'draft') {
            return redirect()->route('account.expenses.index')->with('error', __('Cannot update posted expense.'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!BankAccount::where('id', $validated['bank_account_id'])->where('created_by', $companyId)->exists()
            || !ExpenseCategories::where('id', $validated['category_id'])->where('created_by', $companyId)->exists()
            || !ChartOfAccount::where('id', $validated['chart_of_account_id'])->where('created_by', $companyId)->exists()
        ) {
            return redirect()->back()->with('error', __('Invalid category, bank account, or GL account selected.'));
        }

        $expense->expense_date = $validated['expense_date'];
        $expense->category_id = $validated['category_id'];
        $expense->bank_account_id = $validated['bank_account_id'];
        $expense->chart_of_account_id = $validated['chart_of_account_id'];
        $expense->amount = $validated['amount'];
        $expense->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $expense->reference_number = $validated['reference_number'] ?? null;
        $expense->save();

        UpdateExpense::dispatch($request, $expense);

        return back()->with('success', __('The expense details are updated successfully.'));
    }

    public function destroy(Expense $expense)
    {
        if (!Auth::user()->can('delete-expenses') || $expense->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($expense->status != 'draft') {
            return back()->with('error', __('Cannot delete posted expense.'));
        }

        DestroyExpense::dispatch($expense);
        $expense->delete();

        return back()->with('success', __('Expense deleted successfully.'));
    }

    public function approve(Expense $expense)
    {
        if (!Auth::user()->can('approve-expenses') || $expense->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($expense->status !== 'draft') {
            return back()->with('error', __('Only draft expenses can be approved.'));
        }

        ApproveExpense::dispatch($expense);

        $expense->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', __('Expense approved successfully.'));
    }

    public function post(Expense $expense)
    {
        if (!Auth::user()->can('post-expenses') || $expense->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        try {
            DB::transaction(function () use ($expense) {
                $lockedExpense = Expense::where('id', $expense->id)
                    ->where('created_by', creatorId())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedExpense->status === 'posted') {
                    throw new \Exception(__('Expense is already posted.'));
                }

                $this->journalService->createExpenseEntryJournal($lockedExpense);
                $this->bankTransactionsService->createExpensePayment($lockedExpense);

                $lockedExpense->update(['status' => 'posted']);
            });

            $expense->refresh();
            PostExpense::dispatch($expense);

            return back()->with('success', __('Expense posted successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
