<?php

namespace Workdo\ProductService\Http\Controllers;

use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\ProductService\Events\CreateProductServiceItem;
use Workdo\ProductService\Events\DestroyProductServiceItem;
use Workdo\ProductService\Events\UpdateProductServiceItem;
use Workdo\ProductService\Http\Requests\StoreProductServiceItemRequest;
use Workdo\ProductService\Http\Requests\UpdateProductServiceItemRequest;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;
use Workdo\ProductService\Traits\ProductServiceControllerHelpers;
use Workdo\ProductService\Traits\ProductServiceDateHelpers;

class ProductServiceItemController extends Controller
{
    use ProductServiceControllerHelpers, ProductServiceDateHelpers;

    private function isTenantOwner(ProductServiceItem $item): bool
    {
        return Auth::user()?->hasRole('superadmin') || (int) $item->created_by === (int) creatorId();
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || $value === 'none') {
            return null;
        }
        return (int) $value;
    }

    /**
     * Load optional Chart of Accounts grouped cleanly when Account module is active.
     */
    private function getGlAccountOptions(): array
    {
        if (!module_is_active('Account') || !class_exists(\Workdo\Account\Models\ChartOfAccount::class)) {
            return [
                'hasAccountModule' => false,
                'revenueAccounts' => [],
                'expenseAccounts' => [],
                'assetAccounts' => [],
            ];
        }

        try {
            $accounts = \Workdo\Account\Models\ChartOfAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']);

            return [
                'hasAccountModule' => true,
                'revenueAccounts' => $accounts->filter(fn ($a) => str_starts_with((string) $a->account_code, '4'))->values(),
                'expenseAccounts' => $accounts->filter(fn ($a) => str_starts_with((string) $a->account_code, '5') || str_starts_with((string) $a->account_code, '6'))->values(),
                'assetAccounts' => $accounts->filter(fn ($a) => str_starts_with((string) $a->account_code, '1'))->values(),
            ];
        } catch (\Throwable $e) {
            return [
                'hasAccountModule' => false,
                'revenueAccounts' => [],
                'expenseAccounts' => [],
                'assetAccounts' => [],
            ];
        }
    }

    public function index()
    {
        if (Auth::user()->can('manage-product-service-item')) {
            $items = ProductServiceItem::select(
                'id', 'name', 'sku', 'barcode', 'hs_code', 'sale_price', 'purchase_price',
                'tax_ids', 'tax_classification', 'category_id', 'unit', 'type', 'inventory_type',
                'is_active', 'reorder_level', 'image', 'description', 'created_at'
            )
                ->with(['category:id,name', 'unitRelation:id,unit_name', 'warehouseStocks:product_id,quantity,unit_cost'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-product-service-item')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-product-service-item')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), function ($q) {
                    $term = request('name');
                    $q->where(function ($sub) use ($term) {
                        $sub->where('name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%");
                    });
                })
                ->when(request('type'), fn ($q) => $q->where('type', request('type')))
                ->when(request('category_id'), fn ($q) => $q->where('category_id', request('category_id')))
                ->when(request('is_active') !== null && request('is_active') !== '', fn ($q) => $q->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOLEAN)))
                ->tap(fn ($q) => $this->applySafeSort($q, ['name', 'sku', 'barcode', 'sale_price', 'purchase_price', 'type', 'is_active', 'reorder_level', 'created_at']))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $items->getCollection()->transform(function ($item) {
                $item->total_quantity = round((float) $item->warehouseStocks->sum('quantity'), 4);
                return $item;
            });

            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);

            return Inertia::render('ProductService/Items/Index', [
                'items' => $items,
                'categories' => $categories,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function create()
    {
        if (Auth::user()->can('create-product-service-item')) {
            $taxes = ProductServiceTax::where('created_by', creatorId())->get(['id', 'tax_name', 'rate']);
            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);
            $units = ProductServiceUnit::where('created_by', creatorId())->get(['id', 'unit_name']);
            $warehouses = Warehouse::where('is_active', true)->where('created_by', creatorId())->get(['id', 'name']);

            return Inertia::render('ProductService/Items/Create', array_merge([
                'taxes' => $taxes,
                'categories' => $categories,
                'units' => $units,
                'warehouses' => $warehouses,
            ], $this->getGlAccountOptions()));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function store(StoreProductServiceItemRequest $request)
    {
        if (Auth::user()->can('create-product-service-item')) {
            $validated = $request->validated();
            $item = new ProductServiceItem();
            $item->name = $validated['name'];
            $item->sku = $validated['sku'];
            $item->barcode = $validated['barcode'] ?? null;
            $item->hs_code = $validated['hs_code'] ?? null;
            $item->tax_ids = !empty($validated['tax_ids']) ? array_map('intval', $validated['tax_ids']) : null;
            $item->tax_classification = $validated['tax_classification'] ?? 'VAT15';
            $item->excise_rate = $validated['excise_rate'] ?? 0;
            $item->is_tax_inclusive = (bool) ($validated['is_tax_inclusive'] ?? false);
            $item->category_id = $this->nullableInt($validated['category_id'] ?? null);
            $item->description = $validated['description'] ?? null;
            $item->long_description = $validated['long_description'] ?? null;
            $item->sale_price = $validated['sale_price'];
            $item->min_sale_price = $validated['min_sale_price'] ?? null;
            $item->wholesale_price = $validated['wholesale_price'] ?? null;
            $item->wholesale_min_qty = $validated['wholesale_min_qty'] ?? null;
            $item->purchase_price = $validated['purchase_price'];
            $item->sale_chartaccount_id = $this->nullableInt($validated['sale_chartaccount_id'] ?? null);
            $item->expense_chartaccount_id = $this->nullableInt($validated['expense_chartaccount_id'] ?? null);
            $item->inventory_chartaccount_id = $this->nullableInt($validated['inventory_chartaccount_id'] ?? null);
            $item->unit = $validated['unit'] === 'none' ? null : $validated['unit'];
            $item->purchase_unit_id = $this->nullableInt($validated['purchase_unit_id'] ?? null);
            $item->unit_conversion_factor = $validated['unit_conversion_factor'] ?? 1;
            $item->reorder_level = $validated['reorder_level'] ?? 0;
            $item->type = $validated['type'] ?? 'product';
            $item->inventory_type = $validated['inventory_type'] ?? 'standard';
            $item->is_active = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
            $item->creator_id = Auth::id();
            $item->created_by = creatorId();

            if (!empty($validated['image'])) {
                $item->image = basename($validated['image']);
            }

            if (!empty($validated['images'])) {
                $item->images = array_map('basename', $validated['images']);
            }

            DB::transaction(function () use ($item, $validated, $request) {
                $item->save();

                // Create initial warehouse stock entry and sync optional reorder rule
                $warehouseId = $this->nullableInt($validated['warehouse_id'] ?? null);
                if ($item->type !== 'service' && $warehouseId && isset($validated['quantity'])) {
                    $qty = floatval($validated['quantity'] ?? 0);
                    $cost = floatval($item->purchase_price ?? 0);

                    if ($qty > 0 && class_exists(InventoryService::class)) {
                        InventoryService::receiveStock(
                            companyId: (int) creatorId(),
                            warehouseId: $warehouseId,
                            productId: (int) $item->id,
                            quantity: $qty,
                            unitCost: $cost,
                            actionType: 'initial_stock',
                            referenceType: 'ProductServiceItem',
                            referenceId: (int) $item->id,
                            notes: 'Initial item stock setup'
                        );
                    } elseif ($qty > 0) {
                        WarehouseStock::create([
                            'product_id'   => $item->id,
                            'warehouse_id' => $warehouseId,
                            'quantity'     => $qty,
                            'unit_cost'    => $cost,
                            'total_value'  => round($qty * $cost, 4),
                            'creator_id'   => Auth::id(),
                            'created_by'   => creatorId(),
                        ]);
                    }

                    if (floatval($item->reorder_level) > 0 && class_exists(\App\Models\InventoryReorderRule::class)) {
                        \App\Models\InventoryReorderRule::updateOrCreate(
                            [
                                'created_by' => creatorId(),
                                'product_id' => $item->id,
                                'warehouse_id' => $warehouseId,
                            ],
                            [
                                'min_stock_level' => floatval($item->reorder_level),
                                'reorder_quantity' => max(floatval($item->reorder_level) * 2, 1),
                            ]
                        );
                    }
                }
            });

            try {
                CreateProductServiceItem::dispatch($request, $item);
                event(new \App\Events\CustomFieldSaved($item, $request->custom_fields ?? [], 'created'));
            } catch (\Throwable $e) {
                // Non-blocking event dispatch
            }

            return redirect()->route('product-service.items.index')->with('success', __('The item has been created successfully.'));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function show(ProductServiceItem $item)
    {
        if (Auth::user()->can('view-product-service-item')) {
            if (!$this->isTenantOwner($item)) {
                return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
            }

            $item->load(['category', 'unitRelation', 'purchaseUnit', 'warehouseStocks.warehouse:id,name']);

            $taxes = [];
            if ($item->tax_ids && is_array($item->tax_ids) && count($item->tax_ids) > 0) {
                $taxes = ProductServiceTax::whereIn('id', $item->tax_ids)
                    ->where('created_by', creatorId())
                    ->get(['id', 'tax_name', 'rate'])
                    ->toArray();
            }

            $totalValuation = $item->warehouseStocks->sum(function ($stock) use ($item) {
                $qty = (float) $stock->quantity;
                $cost = (float) ($stock->unit_cost ?? $item->purchase_price ?? 0);
                return $stock->total_value !== null ? (float) $stock->total_value : ($qty * $cost);
            });

            $auditLogs = [];
            if (class_exists(\App\Models\InventoryAuditLog::class)) {
                $auditLogs = \App\Models\InventoryAuditLog::with('warehouse:id,name')
                    ->where('created_by', creatorId())
                    ->where('product_id', $item->id)
                    ->latest()
                    ->limit(15)
                    ->get(['id', 'warehouse_id', 'action_type', 'quantity_change', 'old_quantity', 'new_quantity', 'reference_type', 'notes', 'created_at'])
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'warehouse_name' => $log->warehouse->name ?? 'N/A',
                        'action_type' => $log->action_type,
                        'quantity_change' => (float) $log->quantity_change,
                        'new_quantity' => (float) $log->new_quantity,
                        'reference_type' => $log->reference_type,
                        'notes' => $log->notes,
                        'created_at' => $log->created_at?->toDateTimeString(),
                    ])
                    ->toArray();
            }

            $itemData = $item->toArray();
            $itemData['taxes'] = $taxes;
            $itemData['total_quantity'] = round((float) $item->warehouseStocks->sum('quantity'), 4);
            $itemData['total_valuation'] = round((float) $totalValuation, 2);
            $itemData['audit_logs'] = $auditLogs;
            $itemData['warehouse_stocks'] = $item->warehouseStocks->map(function ($stock) use ($item) {
                $qty = (float) $stock->quantity;
                $unitCost = (float) ($stock->unit_cost ?? $item->purchase_price ?? 0);
                return [
                    'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_value' => round($stock->total_value !== null ? (float) $stock->total_value : ($qty * $unitCost), 2),
                ];
            });

            return Inertia::render('ProductService/Items/Show', [
                'item' => $itemData,
            ]);
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function edit(ProductServiceItem $item)
    {
        if (Auth::user()->can('edit-product-service-item')) {
            if (!$this->isTenantOwner($item)) {
                return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
            }

            $taxes = ProductServiceTax::where('created_by', creatorId())->get(['id', 'tax_name', 'rate']);
            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);
            $units = ProductServiceUnit::where('created_by', creatorId())->get(['id', 'unit_name']);
            $warehouses = Warehouse::where('is_active', true)->where('created_by', creatorId())->get(['id', 'name']);

            $item->load(['category', 'unitRelation', 'purchaseUnit']);

            return Inertia::render('ProductService/Items/Edit', array_merge([
                'item' => $item,
                'taxes' => $taxes,
                'categories' => $categories,
                'units' => $units,
                'warehouses' => $warehouses,
            ], $this->getGlAccountOptions()));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateProductServiceItemRequest $request, ProductServiceItem $item)
    {
        if (Auth::user()->can('edit-product-service-item')) {
            if (!$this->isTenantOwner($item)) {
                return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
            }

            $validated = $request->validated();

            $item->name = $validated['name'];
            $item->sku = $validated['sku'];
            $item->barcode = $validated['barcode'] ?? null;
            $item->hs_code = $validated['hs_code'] ?? null;
            $item->tax_ids = !empty($validated['tax_ids']) ? array_map('intval', $validated['tax_ids']) : null;
            $item->tax_classification = $validated['tax_classification'] ?? ($item->tax_classification ?? 'VAT15');
            $item->excise_rate = $validated['excise_rate'] ?? 0;
            $item->is_tax_inclusive = (bool) ($validated['is_tax_inclusive'] ?? false);
            $item->category_id = $this->nullableInt($validated['category_id'] ?? null);
            $item->description = $validated['description'] ?? null;
            $item->long_description = $validated['long_description'] ?? null;
            $item->sale_price = $validated['sale_price'];
            $item->min_sale_price = $validated['min_sale_price'] ?? null;
            $item->wholesale_price = $validated['wholesale_price'] ?? null;
            $item->wholesale_min_qty = $validated['wholesale_min_qty'] ?? null;
            $item->purchase_price = $validated['purchase_price'];
            $item->sale_chartaccount_id = $this->nullableInt($validated['sale_chartaccount_id'] ?? null);
            $item->expense_chartaccount_id = $this->nullableInt($validated['expense_chartaccount_id'] ?? null);
            $item->inventory_chartaccount_id = $this->nullableInt($validated['inventory_chartaccount_id'] ?? null);
            $item->unit = $validated['unit'] === 'none' ? null : $validated['unit'];
            $item->purchase_unit_id = $this->nullableInt($validated['purchase_unit_id'] ?? null);
            $item->unit_conversion_factor = $validated['unit_conversion_factor'] ?? 1;
            $item->reorder_level = $validated['reorder_level'] ?? 0;
            $item->type = $validated['type'] ?? $item->type;
            $item->inventory_type = $validated['inventory_type'] ?? ($item->inventory_type ?? 'standard');
            if (array_key_exists('is_active', $validated)) {
                $item->is_active = (bool) $validated['is_active'];
            }

            if (array_key_exists('image', $validated)) {
                $item->image = !empty($validated['image']) ? basename($validated['image']) : null;
            }

            if (array_key_exists('images', $validated)) {
                $item->images = !empty($validated['images']) ? array_map('basename', $validated['images']) : [];
            }

            DB::transaction(function () use ($item) {
                $item->save();
            });

            try {
                UpdateProductServiceItem::dispatch($request, $item);
                event(new \App\Events\CustomFieldSaved($item, $request->custom_fields ?? [], 'updated'));
            } catch (\Throwable $e) {
                // Non-blocking
            }

            return redirect()->route('product-service.items.index')->with('success', __('The item details are updated successfully.'));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function toggleStatus(ProductServiceItem $item)
    {
        if (!Auth::user()->can('edit-product-service-item') || !$this->isTenantOwner($item)) {
            return back()->with('error', __('Permission denied'));
        }

        $item->is_active = !$item->is_active;
        $item->save();

        return back()->with('success', $item->is_active ? __('Item activated successfully.') : __('Item archived (deactivated) successfully.'));
    }

    public function destroy(ProductServiceItem $item)
    {
        if (Auth::user()->can('delete-product-service-item')) {
            if (!$this->isTenantOwner($item)) {
                return back()->with('error', __('Permission denied'));
            }

            $hasSales = class_exists(\App\Models\SalesInvoiceItem::class)
                && \App\Models\SalesInvoiceItem::where('product_id', $item->id)->exists();
            $hasPurchases = class_exists(\App\Models\PurchaseInvoiceItem::class)
                && \App\Models\PurchaseInvoiceItem::where('product_id', $item->id)->exists();
            $hasAuditLogs = class_exists(\App\Models\InventoryAuditLog::class)
                && \App\Models\InventoryAuditLog::where('product_id', $item->id)->exists();

            if ($hasSales || $hasPurchases || $hasAuditLogs) {
                DB::transaction(function () use ($item) {
                    $item->is_active = false;
                    $item->save();
                    $item->delete(); // Soft-delete preserves ERP financial & stock history
                });

                try {
                    DestroyProductServiceItem::dispatch($item);
                } catch (\Throwable $e) {
                    // Non-blocking
                }
                return back()->with('success', __('Item archived to preserve historical invoice and inventory records.'));
            }

            DB::transaction(function () use ($item) {
                if ($item->image) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
                }

                WarehouseStock::where('product_id', $item->id)->delete();
                try {
                    DestroyProductServiceItem::dispatch($item);
                } catch (\Throwable $e) {
                    // Non-blocking
                }
                $item->forceDelete();
            });

            return back()->with('success', __('The item has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function stockIndex()
    {
        if (Auth::user()->can('manage-stock')) {
            $stocks = ProductServiceItem::select('id', 'name', 'sku', 'purchase_price', 'reorder_level')
                ->with(['warehouseStocks:product_id,warehouse_id,quantity'])
                ->where('created_by', creatorId())
                ->where('type', '!=', 'service')
                ->when(request('name'), fn ($q) => $q->where(function ($sub) {
                    $term = request('name');
                    $sub->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
                }))
                ->latest()
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $stocks->getCollection()->transform(function ($item) {
                $item->total_quantity = round((float) $item->warehouseStocks->sum('quantity'), 4);
                return $item;
            });

            $warehouses = Warehouse::where('created_by', creatorId())->where('is_active', true)->get(['id', 'name']);

            return Inertia::render('ProductService/Stock/Index', [
                'stocks' => $stocks,
                'warehouses' => $warehouses,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function apiIndex()
    {
        if (Auth::user()->can('manage-product-service-item') || Auth::user()->can('manage-any-product-service-item') || Auth::user()->can('manage-own-product-service-item')) {
            try {
                $query = ProductServiceItem::query()->where('is_active', true);

                if (Auth::user()->can('manage-any-product-service-item')) {
                    $query->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-product-service-item')) {
                    $query->where('creator_id', Auth::id());
                } else {
                    $query->where('created_by', creatorId());
                }

                $items = $query->get([
                    'id', 'name', 'sku', 'barcode', 'hs_code',
                    'sale_price', 'min_sale_price', 'wholesale_price', 'wholesale_min_qty',
                    'tax_ids', 'tax_classification', 'excise_rate', 'is_tax_inclusive', 'description',
                ]);

                // Preload tenant taxes once instead of N+1 queries inside map()
                $tenantTaxes = ProductServiceTax::where('created_by', creatorId())
                    ->get(['id', 'tax_name', 'rate'])
                    ->keyBy('id');

                $transformedItems = $items->map(function ($item) use ($tenantTaxes) {
                    $taxes = [];
                    if (!empty($item->tax_ids) && is_array($item->tax_ids)) {
                        foreach ($item->tax_ids as $tid) {
                            if (isset($tenantTaxes[$tid])) {
                                $taxes[] = $tenantTaxes[$tid]->toArray();
                            }
                        }
                    }

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'barcode' => $item->barcode,
                        'hs_code' => $item->hs_code,
                        'sale_price' => $item->sale_price,
                        'min_sale_price' => $item->min_sale_price,
                        'wholesale_price' => $item->wholesale_price,
                        'wholesale_min_qty' => $item->wholesale_min_qty,
                        'tax_classification' => $item->tax_classification,
                        'excise_rate' => $item->excise_rate,
                        'is_tax_inclusive' => $item->is_tax_inclusive,
                        'description' => $item->description,
                        'taxes' => $taxes,
                    ];
                });

                return response()->json($transformedItems);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to fetch products'], 500);
            }
        }
        return response()->json(['error' => 'Permission denied'], 403);
    }

    public function stockStore(Request $request)
    {
        if (Auth::user()->can('create-stock')) {
            $tenantId = (int) creatorId();
            $validated = $request->validate([
                'product_id' => [
                    'required',
                    Rule::exists('product_service_items', 'id')->where('created_by', $tenantId)->whereNull('deleted_at'),
                ],
                'warehouse_id' => [
                    'required',
                    Rule::exists('warehouses', 'id')->where('created_by', $tenantId),
                ],
                'quantity' => 'required|numeric|gt:0',
                'unit_cost' => 'nullable|numeric|min:0',
            ]);

            $product = ProductServiceItem::where('created_by', $tenantId)->findOrFail($validated['product_id']);
            $qty = floatval($validated['quantity']);
            $cost = isset($validated['unit_cost']) ? floatval($validated['unit_cost']) : floatval($product->purchase_price ?? 0);

            if (class_exists(InventoryService::class)) {
                InventoryService::receiveStock(
                    companyId: $tenantId,
                    warehouseId: (int) $validated['warehouse_id'],
                    productId: (int) $product->id,
                    quantity: $qty,
                    unitCost: $cost,
                    actionType: 'manual_addition',
                    referenceType: 'ProductServiceItem',
                    referenceId: (int) $product->id,
                    notes: 'Direct stock entry via Product Stock screen'
                );
            } else {
                DB::transaction(function () use ($product, $validated, $qty, $cost, $tenantId) {
                    $existingStock = WarehouseStock::where('product_id', $product->id)
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingStock) {
                        $existingStock->increment('quantity', $qty);
                    } else {
                        WarehouseStock::create([
                            'product_id' => $product->id,
                            'warehouse_id' => $validated['warehouse_id'],
                            'quantity' => $qty,
                            'unit_cost' => $cost,
                            'total_value' => round($qty * $cost, 4),
                            'creator_id' => Auth::id() ?? $tenantId,
                            'created_by' => $tenantId,
                        ]);
                    }
                });
            }

            return back()->with('success', __('Stock entry created successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    /**
     * Export tenant Product & Service catalog cleanly to UTF-8 CSV.
     */
    public function exportCsv()
    {
        if (!Auth::user()->can('manage-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $items = ProductServiceItem::with(['category:id,name', 'unitRelation:id,unit_name', 'warehouseStocks:product_id,quantity'])
            ->where('created_by', creatorId())
            ->orderBy('name')
            ->get();

        $filename = 'product_service_catalog_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Name', 'SKU', 'Barcode', 'HS Code', 'Type', 'Inventory Tracking',
                'Category', 'Unit', 'Sale Price', 'Purchase Price', 'Min Sale Price',
                'Wholesale Price', 'Tax Classification', 'Excise Rate (%)', 'Reorder Level',
                'Total Stock', 'Active', 'Description',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->name,
                    $item->sku,
                    $item->barcode ?? '',
                    $item->hs_code ?? '',
                    $item->type ?? 'product',
                    $item->inventory_type ?? 'standard',
                    $item->category->name ?? '',
                    $item->unitRelation->unit_name ?? '',
                    $item->sale_price ?? 0,
                    $item->purchase_price ?? 0,
                    $item->min_sale_price ?? '',
                    $item->wholesale_price ?? '',
                    $item->tax_classification ?? 'VAT15',
                    $item->excise_rate ?? 0,
                    $item->reorder_level ?? 0,
                    $item->warehouseStocks->sum('quantity'),
                    $item->is_active ? 'Yes' : 'No',
                    $item->description ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Bulk import/upsert items by SKU from CSV.
     */
    public function importCsv(Request $request)
    {
        if (!Auth::user()->can('create-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $tenantId = (int) creatorId();
        $userId = (int) Auth::id();
        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return back()->with('error', __('Unable to read CSV file.'));
        }

        $header = fgetcsv($handle);
        if (!$header || count($header) < 2) {
            fclose($handle);
            return back()->with('error', __('Invalid CSV format. Expected headers including Name and SKU.'));
        }

        // Strip UTF-8 BOM from first header cell if present
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        $colMap = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $header));

        $defaultCategory = ProductServiceCategory::where('created_by', $tenantId)->first();
        $defaultUnit = ProductServiceUnit::where('created_by', $tenantId)->first();
        $defaultTax = ProductServiceTax::where('created_by', $tenantId)->first();

        $imported = 0;
        DB::transaction(function () use ($handle, $colMap, $tenantId, $userId, $defaultCategory, $defaultUnit, $defaultTax, &$imported) {
            while (($row = fgetcsv($handle)) !== false) {
                $name = trim((string) ($row[$colMap['name'] ?? 0] ?? ''));
                $sku = trim((string) ($row[$colMap['sku'] ?? 1] ?? ''));
                if ($name === '' || $sku === '') {
                    continue;
                }

                $catName = trim((string) ($row[$colMap['category'] ?? -1] ?? ''));
                $category = $catName !== ''
                    ? ProductServiceCategory::firstOrCreate(
                        ['created_by' => $tenantId, 'name' => $catName],
                        ['color' => '#10b981', 'creator_id' => $userId]
                    )
                    : $defaultCategory;

                $unitName = trim((string) ($row[$colMap['unit'] ?? -1] ?? ''));
                $unit = $unitName !== ''
                    ? ProductServiceUnit::firstOrCreate(
                        ['created_by' => $tenantId, 'unit_name' => $unitName],
                        ['creator_id' => $userId]
                    )
                    : $defaultUnit;

                $typeRaw = strtolower(trim((string) ($row[$colMap['type'] ?? -1] ?? 'product')));
                $type = in_array($typeRaw, ['product', 'service', 'part'], true) ? $typeRaw : 'product';

                $taxClass = strtoupper(trim((string) ($row[$colMap['tax classification'] ?? -1] ?? 'VAT15')));
                if (!in_array($taxClass, ['VAT15', 'VAT0', 'EXEMPT', 'EXCISE'], true)) {
                    $taxClass = 'VAT15';
                }

                ProductServiceItem::updateOrCreate(
                    ['created_by' => $tenantId, 'sku' => $sku],
                    [
                        'name' => $name,
                        'barcode' => trim((string) ($row[$colMap['barcode'] ?? -1] ?? '')) ?: null,
                        'hs_code' => trim((string) ($row[$colMap['hs code'] ?? -1] ?? '')) ?: null,
                        'type' => $type,
                        'category_id' => $category?->id,
                        'unit' => $unit ? (string) $unit->id : null,
                        'tax_ids' => $defaultTax ? [$defaultTax->id] : null,
                        'tax_classification' => $taxClass,
                        'excise_rate' => floatval($row[$colMap['excise rate (%)'] ?? -1] ?? 0),
                        'sale_price' => floatval($row[$colMap['sale price'] ?? -1] ?? 0),
                        'purchase_price' => floatval($row[$colMap['purchase price'] ?? -1] ?? 0),
                        'reorder_level' => floatval($row[$colMap['reorder level'] ?? -1] ?? 0),
                        'description' => trim((string) ($row[$colMap['description'] ?? -1] ?? '')) ?: null,
                        'is_active' => true,
                        'creator_id' => $userId,
                    ]
                );
                $imported++;
            }
        });

        fclose($handle);

        return back()->with('success', __(':count items imported/updated successfully.', ['count' => $imported]));
    }
}
