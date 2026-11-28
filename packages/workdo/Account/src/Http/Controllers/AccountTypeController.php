<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Events\CreateAccountType;
use Workdo\Account\Events\DestroyAccountType;
use Workdo\Account\Events\UpdateAccountType;
use Workdo\Account\Http\Requests\StoreAccountTypeRequest;
use Workdo\Account\Http\Requests\UpdateAccountTypeRequest;
use Workdo\Account\Models\AccountCategory;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\ChartOfAccount;

class AccountTypeController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-account-types')) {
            return back()->with('error', __('Permission denied'));
        }

        $accounttypes = AccountType::with(['category'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->latest()
            ->get();

        return Inertia::render('Account/SystemSetup/AccountTypes/Index', [
            'accounttypes'      => $accounttypes,
            'accountcategories' => AccountCategory::where('created_by', creatorId())->select('id', 'name')->get(),
        ]);
    }

    public function store(StoreAccountTypeRequest $request)
    {
        if (!Auth::user()->can('create-account-types')) {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!AccountCategory::where('id', $validated['category_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid account category selected.'));
        }

        $accounttype = new AccountType();
        $accounttype->category_id = $validated['category_id'];
        $accounttype->name = $validated['name'];
        $accounttype->code = $validated['code'];
        $accounttype->normal_balance = $validated['normal_balance'] === '1' ? 'credit' : 'debit';
        $accounttype->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $accounttype->is_active = $request->boolean('is_active', false);
        $accounttype->creator_id = Auth::id();
        $accounttype->created_by = $companyId;
        $accounttype->save();

        CreateAccountType::dispatch($request, $accounttype);

        return redirect()->route('account.account-types.index')->with('success', __('The account type has been created successfully.'));
    }

    public function update(UpdateAccountTypeRequest $request, AccountType $accounttype)
    {
        if (!Auth::user()->can('edit-account-types') || $accounttype->created_by != creatorId()) {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!AccountCategory::where('id', $validated['category_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid account category selected.'));
        }

        $accounttype->category_id = $validated['category_id'];
        $accounttype->name = $validated['name'];
        $accounttype->code = $validated['code'];
        $accounttype->normal_balance = $validated['normal_balance'] === '1' ? 'credit' : 'debit';
        $accounttype->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $accounttype->is_active = $request->boolean('is_active', false);
        $accounttype->save();

        UpdateAccountType::dispatch($request, $accounttype);

        return back()->with('success', __('The account type details are updated successfully.'));
    }

    public function destroy(AccountType $accounttype)
    {
        if (!Auth::user()->can('delete-account-types') || $accounttype->created_by != creatorId()) {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }

        if ($accounttype->is_system_type == 1) {
            return redirect()->back()->with('error', __('System account types cannot be deleted.'));
        }

        if (ChartOfAccount::where('account_type_id', $accounttype->id)->where('created_by', creatorId())->exists()) {
            return redirect()->back()->with('error', __('Cannot delete account type linked to existing accounts.'));
        }

        DestroyAccountType::dispatch($accounttype);
        $accounttype->delete();

        return redirect()->back()->with('success', __('The accounttype has been deleted.'));
    }
}
