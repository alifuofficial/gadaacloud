<?php

namespace Workdo\ProductService\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\ProductService\Events\CreateProductServiceTax;
use Workdo\ProductService\Events\DestroyProductServiceTax;
use Workdo\ProductService\Events\UpdateProductServiceTax;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Traits\ProductServiceControllerHelpers;
use Workdo\ProductService\Traits\ProductServiceDateHelpers;

class TaxController extends Controller
{
    use ProductServiceControllerHelpers, ProductServiceDateHelpers;

    public function index()
    {
        if(Auth::user()->can('manage-product-service-taxes')){
            $taxes = ProductServiceTax::select('id', 'tax_name', 'rate', 'created_at')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-product-service-taxes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-product-service-taxes')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->latest()
                ->get();

            return Inertia::render('ProductService/SystemSetup/Taxes/Index', [
                'taxes' => $taxes,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function store(Request $request)
    {
        if(Auth::user()->can('create-product-service-taxes')){
            $validated = $request->validate([
                'tax_name' => 'required|string|max:255',
                'rate' => 'required|numeric|min:0|max:100',
            ]);

            $tax = DB::transaction(function () use ($validated) {
                $tax = new ProductServiceTax();
                $tax->tax_name = $validated['tax_name'];
                $tax->rate = $validated['rate'];
                $tax->creator_id = Auth::id();
                $tax->created_by = creatorId();
                $tax->save();
                return $tax;
            });

            // Dispatch event for packages to handle their fields
            CreateProductServiceTax::dispatch($request, $tax);

            return redirect()->route('product-service.taxes.index')->with('success', __('The tax has been created successfully.'));
        }
        return redirect()->route('product-service.taxes.index')->with('error', __('Permission denied'));
    }

    public function update(Request $request, ProductServiceTax $tax)
    {
        if(Auth::user()->can('edit-product-service-taxes')){
            if (!Auth::user()->hasRole('superadmin') && (int) $tax->created_by !== (int) creatorId()) {
                return redirect()->route('product-service.taxes.index')->with('error', __('Permission denied'));
            }
            $validated = $request->validate([
                'tax_name' => 'required|string|max:255',
                'rate' => 'required|numeric|min:0|max:100',
            ]);

            DB::transaction(function () use ($tax, $validated) {
                $tax->tax_name = $validated['tax_name'];
                $tax->rate = $validated['rate'];
                $tax->save();
            });

            // Dispatch event for packages to handle their fields
            UpdateProductServiceTax::dispatch($request, $tax);

            return redirect()->route('product-service.taxes.index')->with('success', __('The tax details are updated successfully.'));
        }
        return redirect()->route('product-service.taxes.index')->with('error', __('Permission denied'));
    }

    public function destroy(ProductServiceTax $tax)
    {
        if(Auth::user()->can('delete-product-service-taxes')){
            if (!Auth::user()->hasRole('superadmin') && (int) $tax->created_by !== (int) creatorId()) {
                return redirect()->route('product-service.taxes.index')->with('error', __('Permission denied'));
            }
            // Dispatch event for packages to handle their fields
            DestroyProductServiceTax::dispatch($tax);

            DB::transaction(function () use ($tax) {
                $tax->delete();
            });

            return redirect()->route('product-service.taxes.index')->with('success', __('The tax has been deleted.'));
        }
        return redirect()->route('product-service.taxes.index')->with('error', __('Permission denied'));
    }
}
