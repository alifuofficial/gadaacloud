<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Events\CreateExpenseCategories;
use Workdo\Account\Events\DestroyExpenseCategories;
use Workdo\Account\Events\UpdateExpenseCategories;
use Workdo\Account\Http\Requests\StoreExpenseCategoriesRequest;
use Workdo\Account\Http\Requests\UpdateExpenseCategoriesRequest;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;

class ExpenseCategoriesController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-expense-categories')) {
            return back()->with('error', __('Permission denied'));
        }

        $expensecategories = ExpenseCategories::with('gl_account:id,account_name')
            ->select('id', 'category_name', 'category_code', 'gl_account_id', 'description', 'is_active', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-expense-categories') || Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-expense-categories') || Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->latest()
            ->get();

        return Inertia::render('Account/SystemSetup/ExpenseCategories/Index', [
            'expensecategories' => $expensecategories,
            'chartofaccounts' => ChartOfAccount::where('created_by', creatorId())
                ->whereBetween('account_code', ['5000', '5999'])
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get(),
        ]);
    }

    public function store(StoreExpenseCategoriesRequest $request)
    {
        if (!Auth::user()->can('create-expense-categories')) {
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!empty($validated['gl_account_id']) && !ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $expensecategories = new ExpenseCategories();
        $expensecategories->category_name = $validated['category_name'];
        $expensecategories->category_code = $validated['category_code'];
        $expensecategories->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $expensecategories->gl_account_id = $validated['gl_account_id'];
        $expensecategories->is_active = $request->boolean('is_active', true);
        $expensecategories->creator_id = Auth::id();
        $expensecategories->created_by = $companyId;
        $expensecategories->save();

        CreateExpenseCategories::dispatch($request, $expensecategories);

        return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories has been created successfully.'));
    }

    public function update(UpdateExpenseCategoriesRequest $request, ExpenseCategories $expensecategories)
    {
        if (!Auth::user()->can('edit-expense-categories') || $expensecategories->created_by != creatorId()) {
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!empty($validated['gl_account_id']) && !ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $expensecategories->category_name = $validated['category_name'];
        $expensecategories->category_code = $validated['category_code'];
        $expensecategories->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $expensecategories->gl_account_id = $validated['gl_account_id'];
        $expensecategories->is_active = $request->boolean('is_active', true);
        $expensecategories->save();

        UpdateExpenseCategories::dispatch($request, $expensecategories);

        return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories details are updated successfully.'));
    }

    public function destroy(ExpenseCategories $expensecategories)
    {
        if (!Auth::user()->can('delete-expense-categories') || $expensecategories->created_by != creatorId()) {
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }

        if (Expense::where('category_id', $expensecategories->id)->where('created_by', creatorId())->exists()) {
            return redirect()->route('account.expense-categories.index')->with('error', __('Cannot delete category that is used by expense entries.'));
        }

        DestroyExpenseCategories::dispatch($expensecategories);
        $expensecategories->delete();

        return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories has been deleted.'));
    }
}
