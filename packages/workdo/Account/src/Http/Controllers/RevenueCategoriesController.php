<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Events\CreateRevenueCategories;
use Workdo\Account\Events\DestroyRevenueCategories;
use Workdo\Account\Events\UpdateRevenueCategories;
use Workdo\Account\Http\Requests\StoreRevenueCategoriesRequest;
use Workdo\Account\Http\Requests\UpdateRevenueCategoriesRequest;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\RevenueCategories;

class RevenueCategoriesController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-revenue-categories')) {
            return back()->with('error', __('Permission denied'));
        }

        $revenuecategories = RevenueCategories::with('gl_account:id,account_name')
            ->select('id', 'category_name', 'category_code', 'gl_account_id', 'description', 'is_active', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-revenue-categories') || Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-revenue-categories') || Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->latest()
            ->get();

        return Inertia::render('Account/SystemSetup/RevenueCategories/Index', [
            'revenuecategories' => $revenuecategories,
            'chartofaccounts' => ChartOfAccount::where('created_by', creatorId())
                ->whereBetween('account_code', ['4000', '4999'])
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get(),
        ]);
    }

    public function store(StoreRevenueCategoriesRequest $request)
    {
        if (!Auth::user()->can('create-revenue-categories')) {
            return redirect()->route('account.revenue-categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!empty($validated['gl_account_id']) && !ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $revenuecategories = new RevenueCategories();
        $revenuecategories->category_name = $validated['category_name'];
        $revenuecategories->category_code = $validated['category_code'];
        $revenuecategories->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $revenuecategories->gl_account_id = $validated['gl_account_id'];
        $revenuecategories->is_active = $request->boolean('is_active', true);
        $revenuecategories->creator_id = Auth::id();
        $revenuecategories->created_by = $companyId;
        $revenuecategories->save();

        CreateRevenueCategories::dispatch($request, $revenuecategories);

        return redirect()->route('account.revenue-categories.index')->with('success', __('The revenue categories has been created successfully.'));
    }

    public function update(UpdateRevenueCategoriesRequest $request, RevenueCategories $revenuecategories)
    {
        if (!Auth::user()->can('edit-revenue-categories') || $revenuecategories->created_by != creatorId()) {
            return redirect()->route('account.revenue-categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if (!empty($validated['gl_account_id']) && !ChartOfAccount::where('id', $validated['gl_account_id'])->where('created_by', $companyId)->exists()) {
            return redirect()->back()->with('error', __('Invalid GL account selected.'));
        }

        $revenuecategories->category_name = $validated['category_name'];
        $revenuecategories->category_code = $validated['category_code'];
        $revenuecategories->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $revenuecategories->gl_account_id = $validated['gl_account_id'];
        $revenuecategories->is_active = $request->boolean('is_active', true);
        $revenuecategories->save();

        UpdateRevenueCategories::dispatch($request, $revenuecategories);

        return redirect()->route('account.revenue-categories.index')->with('success', __('The revenue categories details are updated successfully.'));
    }

    public function destroy(RevenueCategories $revenuecategories)
    {
        if (!Auth::user()->can('delete-revenue-categories') || $revenuecategories->created_by != creatorId()) {
            return redirect()->route('account.revenue-categories.index')->with('error', __('Permission denied'));
        }

        if (Revenue::where('category_id', $revenuecategories->id)->where('created_by', creatorId())->exists()) {
            return redirect()->route('account.revenue-categories.index')->with('error', __('Cannot delete category that is used by revenue entries.'));
        }

        DestroyRevenueCategories::dispatch($revenuecategories);
        $revenuecategories->delete();

        return redirect()->route('account.revenue-categories.index')->with('success', __('The revenue categories has been deleted.'));
    }
}
