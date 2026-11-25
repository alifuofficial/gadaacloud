<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Events\CreateBankAccount;
use Workdo\Account\Events\DestroyBankAccount;
use Workdo\Account\Events\UpdateBankAccount;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreBankAccountRequest;
use Workdo\Account\Http\Requests\UpdateBankAccountRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\VendorPayment;

class BankAccountController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-bank-accounts')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            request('sort'),
            ['account_number', 'account_name', 'bank_name', 'account_type', 'currency_code', 'opening_balance', 'current_balance', 'is_active', 'created_at'],
            'created_at',
            request('direction', 'desc')
        );

        $bankaccounts = BankAccount::query()
            ->with(['gl_account'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-bank-accounts')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-bank-accounts')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('account_number'), function ($q) {
                $q->where(function ($query) {
                    $query->where('account_number', 'like', '%' . request('account_number') . '%')
                        ->orWhere('account_name', 'like', '%' . request('account_number') . '%')
                        ->orWhere('bank_name', 'like', '%' . request('account_number') . '%');
                });
            })
            ->when(request('bank_name'), fn ($q) => $q->where('bank_name', 'like', '%' . request('bank_name') . '%'))
            ->when(request('account_type') !== null && request('account_type') !== '', fn ($q) => $q->where('account_type', request('account_type')))
            ->when(request('is_active') !== null && request('is_active') !== '', fn ($q) => $q->where('is_active', request('is_active') === '1'))
            ->when(request('sort'), fn ($q) => $q->orderBy($sortCol, $sortDir), fn ($q) => $q->latest())
            ->paginate(request('per_page', 10))
            ->withQueryString();

        return Inertia::render('Account/BankAccounts/Index', [
            'bankaccounts' => $bankaccounts,
            'chartofaccounts' => ChartOfAccount::where('created_by', creatorId())
                ->whereBetween('account_code', ['1000', '1099'])
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get(),
        ]);
    }

    public function store(StoreBankAccountRequest $request)
    {
        if (!Auth::user()->can('create-bank-accounts')) {
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $bankaccount = new BankAccount();
        $bankaccount->account_number = $validated['account_number'];
        $bankaccount->account_name = $validated['account_name'];
        $bankaccount->bank_name = $validated['bank_name'];
        $bankaccount->branch_name = $validated['branch_name'] ?? null;
        $bankaccount->account_type = $validated['account_type'];
        $bankaccount->currency_code = strtoupper($validated['currency_code'] ?? 'ETB');
        $bankaccount->opening_balance = $validated['opening_balance'];
        $bankaccount->current_balance = $validated['current_balance'];
        $bankaccount->iban = $validated['iban'] ?? null;
        $bankaccount->swift_code = $validated['swift_code'] ?? null;
        $bankaccount->routing_number = $validated['routing_number'] ?? null;
        $bankaccount->is_active = $request->boolean('is_active', false);
        $bankaccount->gl_account_id = $validated['gl_account_id'];
        $bankaccount->creator_id = Auth::id();
        $bankaccount->created_by = $companyId;
        $bankaccount->save();

        CreateBankAccount::dispatch($request, $bankaccount);

        return redirect()->route('account.bank-accounts.index')->with('success', __('The bank account has been created successfully.'));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankaccount)
    {
        if (!Auth::user()->can('edit-bank-accounts') || $bankaccount->created_by != creatorId()) {
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $bankaccount->account_number = $validated['account_number'];
        $bankaccount->account_name = $validated['account_name'];
        $bankaccount->bank_name = $validated['bank_name'];
        $bankaccount->branch_name = $validated['branch_name'] ?? null;
        $bankaccount->account_type = $validated['account_type'];
        $bankaccount->currency_code = strtoupper($validated['currency_code'] ?? ($bankaccount->currency_code ?: 'ETB'));

        $hasTransactions = BankTransaction::where('bank_account_id', $bankaccount->id)
            ->where('created_by', $companyId)
            ->exists();

        if (!$hasTransactions) {
            $bankaccount->opening_balance = $validated['opening_balance'];
            $bankaccount->current_balance = $validated['current_balance'];
        }

        $bankaccount->iban = $validated['iban'] ?? null;
        $bankaccount->swift_code = $validated['swift_code'] ?? null;
        $bankaccount->routing_number = $validated['routing_number'] ?? null;
        $bankaccount->is_active = $request->boolean('is_active', false);
        $bankaccount->gl_account_id = $validated['gl_account_id'];
        $bankaccount->save();

        UpdateBankAccount::dispatch($request, $bankaccount);

        return redirect()->back()->with('success', __('The bank account details are updated successfully.'));
    }

    public function destroy(BankAccount $bankaccount)
    {
        if (!Auth::user()->can('delete-bank-accounts') || $bankaccount->created_by != creatorId()) {
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }

        $companyId = creatorId();
        $hasActivity = BankTransaction::where('bank_account_id', $bankaccount->id)->where('created_by', $companyId)->exists()
            || CustomerPayment::where('bank_account_id', $bankaccount->id)->where('created_by', $companyId)->exists()
            || VendorPayment::where('bank_account_id', $bankaccount->id)->where('created_by', $companyId)->exists();

        if ($hasActivity) {
            return redirect()->back()->with('error', __('Cannot delete a bank account with recorded transactions or payments.'));
        }

        DestroyBankAccount::dispatch($bankaccount);
        $bankaccount->delete();

        return redirect()->back()->with('success', __('The bank account has been deleted.'));
    }

    public function bankAccounts()
    {
        $bankAccounts = BankAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'account_name', 'account_number', 'currency_code')
            ->get();

        return response()->json($bankAccounts);
    }
}
