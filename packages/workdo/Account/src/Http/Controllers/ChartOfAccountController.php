<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Events\CreateChartOfAccount;
use Workdo\Account\Events\DestroyChartOfAccount;
use Workdo\Account\Events\UpdateChartOfAccount;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreChartOfAccountRequest;
use Workdo\Account\Http\Requests\UpdateChartOfAccountRequest;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Account\Models\RevenueCategories;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-chart-of-accounts')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            request('sort'),
            ['account_code', 'account_name', 'normal_balance', 'opening_balance', 'current_balance', 'is_active', 'created_at'],
            'created_at',
            request('direction', 'desc')
        );

        $chartofaccounts = ChartOfAccount::query()
            ->with(['account_type', 'parent_account'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-chart-of-accounts')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-chart-of-accounts')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('account_code'), function ($q) {
                $q->where(function ($query) {
                    $query->where('account_code', 'like', '%' . request('account_code') . '%')
                        ->orWhere('account_name', 'like', '%' . request('account_code') . '%');
                });
            })
            ->when(request('account_type_id') && request('account_type_id') !== 'all', fn ($q) => $q->where('account_type_id', request('account_type_id')))
            ->when(request('normal_balance') && request('normal_balance') !== 'all', fn ($q) => $q->where('normal_balance', request('normal_balance')))
            ->when(request('is_active') !== null && request('is_active') !== 'all', fn ($q) => $q->where('is_active', request('is_active') === '1'))
            ->when(request('sort'), fn ($q) => $q->orderBy($sortCol, $sortDir), fn ($q) => $q->latest())
            ->paginate(request('per_page', 10))
            ->withQueryString();

        return Inertia::render('Account/ChartOfAccounts/Index', [
            'chartofaccounts' => $chartofaccounts,
            'accounttypes' => AccountType::where('created_by', creatorId())->select('id', 'name')->get(),
            'parentaccounts' => ChartOfAccount::where('created_by', creatorId())->select('id', 'account_name')->get(),
        ]);
    }

    public function store(StoreChartOfAccountRequest $request)
    {
        if (!Auth::user()->can('create-chart-of-accounts')) {
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!AccountType::where('id', $validated['account_type_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid account type selected.'));
        }

        $parentId = (!empty($validated['parent_account_id']) && $validated['parent_account_id'] !== '0')
            ? $validated['parent_account_id']
            : null;
        if ($parentId && !ChartOfAccount::where('id', $parentId)->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid parent account selected.'));
        }

        $chartofaccount = new ChartOfAccount();
        $chartofaccount->account_code = $validated['account_code'];
        $chartofaccount->account_name = $validated['account_name'];
        $chartofaccount->level = $parentId ? 2 : 1;
        $chartofaccount->parent_account_id = $parentId;
        $chartofaccount->normal_balance = $validated['normal_balance'];
        $chartofaccount->opening_balance = $validated['opening_balance'];
        $chartofaccount->current_balance = $validated['current_balance'];
        $chartofaccount->is_active = $request->boolean('is_active', false);
        $chartofaccount->description = $validated['description'] ?? null;
        $chartofaccount->account_type_id = $validated['account_type_id'];
        $chartofaccount->creator_id = Auth::id();
        $chartofaccount->created_by = $companyId;
        $chartofaccount->save();

        CreateChartOfAccount::dispatch($request, $chartofaccount);

        return redirect()->route('account.chart-of-accounts.index')->with('success', __('The chart of account has been created successfully.'));
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartofaccount)
    {
        if (!Auth::user()->can('edit-chart-of-accounts') || $chartofaccount->created_by != creatorId()) {
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!AccountType::where('id', $validated['account_type_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid account type selected.'));
        }

        $parentId = (!empty($validated['parent_account_id']) && $validated['parent_account_id'] !== '0')
            ? $validated['parent_account_id']
            : null;
        if ($parentId && ((int) $parentId === (int) $chartofaccount->id || !ChartOfAccount::where('id', $parentId)->where('created_by', $companyId)->exists())) {
            return redirect()->back()->with('error', __('Invalid parent account selected.'));
        }

        if ($chartofaccount->is_system_account != 1) {
            $chartofaccount->account_code = $validated['account_code'];
            $chartofaccount->account_name = $validated['account_name'];
        }

        $chartofaccount->level = $parentId ? 2 : 1;
        $chartofaccount->parent_account_id = $parentId;
        $chartofaccount->normal_balance = $validated['normal_balance'];

        // Preserve ledger integrity: never manually overwrite running balances once posted journal history exists
        $hasPostedEntries = JournalEntryItem::where('account_id', $chartofaccount->id)
            ->where('created_by', $companyId)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->exists();

        if (!$hasPostedEntries) {
            $chartofaccount->opening_balance = $validated['opening_balance'];
            $chartofaccount->current_balance = $validated['current_balance'];
        }

        $chartofaccount->is_active = $request->boolean('is_active', false);
        $chartofaccount->description = $validated['description'] ?? null;
        $chartofaccount->account_type_id = $validated['account_type_id'];
        $chartofaccount->save();

        UpdateChartOfAccount::dispatch($request, $chartofaccount);

        return redirect()->back()->with('success', __('The chart of account details are updated successfully.'));
    }

    public function show(ChartOfAccount $chartofaccount)
    {
        if (!Auth::user()->can('view-chart-of-accounts') || $chartofaccount->created_by != creatorId()) {
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }

        $companyId = creatorId();
        $postedItemsQuery = JournalEntryItem::where('account_id', $chartofaccount->id)
            ->where('created_by', $companyId)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->where('created_by', $companyId));

        $history = (clone $postedItemsQuery)
            ->with(['journalEntry'])
            ->orderByDesc('created_at')
            ->paginate(10);

        $totalDebits = (float) (clone $postedItemsQuery)->sum('debit_amount');
        $totalCredits = (float) (clone $postedItemsQuery)->sum('credit_amount');

        $calculatedBalance = $chartofaccount->normal_balance === 'debit'
            ? ((float) $chartofaccount->opening_balance + $totalDebits - $totalCredits)
            : ((float) $chartofaccount->opening_balance + $totalCredits - $totalDebits);

        return Inertia::render('Account/ChartOfAccounts/Show', [
            'chartofaccount'    => $chartofaccount->load(['account_type', 'parent_account']),
            'history'           => $history,
            'calculatedBalance' => round($calculatedBalance, 2),
            'totalDebits'       => round($totalDebits, 2),
            'totalCredits'      => round($totalCredits, 2),
        ]);
    }

    public function destroy(ChartOfAccount $chartofaccount)
    {
        if (!Auth::user()->can('delete-chart-of-accounts') || $chartofaccount->created_by != creatorId()) {
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }

        if ($chartofaccount->is_system_account == 1) {
            return redirect()->back()->with('error', __('System accounts cannot be deleted.'));
        }

        $companyId = creatorId();
        $inUse = JournalEntryItem::where('account_id', $chartofaccount->id)->exists()
            || BankAccount::where('gl_account_id', $chartofaccount->id)->where('created_by', $companyId)->exists()
            || ExpenseCategories::where('gl_account_id', $chartofaccount->id)->where('created_by', $companyId)->exists()
            || RevenueCategories::where('gl_account_id', $chartofaccount->id)->where('created_by', $companyId)->exists()
            || ChartOfAccount::where('parent_account_id', $chartofaccount->id)->where('created_by', $companyId)->exists();

        if ($inUse) {
            return redirect()->back()->with('error', __('Cannot delete an account with ledger activity, sub-accounts, or bank/category links.'));
        }

        DestroyChartOfAccount::dispatch($chartofaccount);
        $chartofaccount->delete();

        return redirect()->back()->with('success', __('The chart of account has been deleted.'));
    }
}
