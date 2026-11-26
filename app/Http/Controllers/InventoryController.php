<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryReorderRule;
use App\Models\InventorySerial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class InventoryController extends Controller
{
    /**
     * Enforce InventoryManagement permissions cleanly.
     */
    private function authorizeInventory(string $permission = 'manage-inventory-management'): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can($permission) && !$user->can('manage-inventory-management') && !$user->hasRole('superadmin'))) {
            abort(403, __('Permission denied'));
        }
    }

    /**
     * Inventory Management Control Tower index view.
     */
    public function index()
    {
        $this->authorizeInventory('manage-inventory-management');

        $companyId = creatorId();

        $warehouses = Warehouse::where('created_by', $companyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $products = ProductServiceItem::where('created_by', $companyId)
            ->where('type', '!=', 'service')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'sku', 'sale_price', 'purchase_price', 'inventory_type']);

        $vendors = User::where('created_by', $companyId)
            ->where('type', 'vendor')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $serializedProducts = $products->where('inventory_type', 'serialized')->values();
        $batchProducts = $products->where('inventory_type', 'batch')->values();

        $serials = InventorySerial::with(['product', 'warehouse'])
            ->where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $batches = Schema::hasTable('inventory_batches')
            ? InventoryBatch::with(['product', 'warehouse'])
                ->where('created_by', $companyId)
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('expiry_date', 'asc')
                ->orderBy('id', 'desc')
                ->get()
            : collect([]);

        $adjustments = InventoryAdjustment::with(['product', 'warehouse', 'adjustedBy'])
            ->where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $reorderRules = InventoryReorderRule::with(['product', 'warehouse', 'preferredVendor'])
            ->where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $auditLogs = InventoryAuditLog::with(['product', 'warehouse', 'creator'])
            ->where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->take(500)
            ->get();

        // Strictly tenant-scoped WarehouseStock query
        $stocks = WarehouseStock::forCompany($companyId)
            ->with(['product', 'warehouse'])
            ->get();

        $stockValuation = [];
        $totalUnits = 0.0;
        $totalInventoryValue = 0.0;
        $stockIndex = [];

        foreach ($stocks as $stock) {
            if (!$stock->product || !$stock->warehouse) {
                continue;
            }
            $qty = floatval($stock->quantity);
            $unitCost = floatval($stock->unit_cost ?? 0) > 0
                ? floatval($stock->unit_cost)
                : floatval($stock->product->purchase_price ?? 0);
            $totalValue = floatval($stock->total_value ?? 0) > 0
                ? floatval($stock->total_value)
                : round(max(0, $qty) * $unitCost, 2);

            $totalUnits += max(0, $qty);
            $totalInventoryValue += $totalValue;

            $key = $stock->product_id . ':' . $stock->warehouse_id;
            $stockIndex[$key] = $qty;

            $stockValuation[] = [
                'id'             => $stock->id,
                'product_id'     => $stock->product_id,
                'product_name'   => $stock->product->name,
                'sku'            => $stock->product->sku,
                'inventory_type' => $stock->product->inventory_type ?? 'standard',
                'warehouse_id'   => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse->name,
                'quantity'       => $qty,
                'unit_cost'      => round($unitCost, 2),
                'total_value'    => round($totalValue, 2),
            ];
        }

        // Low stock alerts across BOTH existing WarehouseStock rows AND configured Reorder Rules at 0 stock
        $lowStockAlerts = [];
        $alertedKeys = [];

        foreach ($stocks as $stock) {
            if (!$stock->product || !$stock->warehouse) {
                continue;
            }
            $rule = $reorderRules
                ->where('product_id', $stock->product_id)
                ->where('warehouse_id', $stock->warehouse_id)
                ->first();

            $minLevel = $rule ? floatval($rule->min_stock_level) : 5.0;
            $qty = floatval($stock->quantity);

            if ($qty <= $minLevel) {
                $key = $stock->product_id . ':' . $stock->warehouse_id;
                $alertedKeys[$key] = true;
                $lowStockAlerts[] = [
                    'id'                  => $stock->id,
                    'product_id'          => $stock->product_id,
                    'warehouse_id'        => $stock->warehouse_id,
                    'product_name'        => $stock->product->name,
                    'sku'                 => $stock->product->sku,
                    'warehouse_name'      => $stock->warehouse->name,
                    'current_qty'         => $qty,
                    'min_qty'             => $minLevel,
                    'reorder_qty'         => $rule ? floatval($rule->reorder_qty ?? max(10, $minLevel * 2)) : max(10, $minLevel * 2),
                    'preferred_vendor_id' => $rule ? $rule->preferred_vendor_id : null,
                ];
            }
        }

        foreach ($reorderRules as $rule) {
            if (!$rule->product || !$rule->warehouse) {
                continue;
            }
            $key = $rule->product_id . ':' . $rule->warehouse_id;
            if (!isset($alertedKeys[$key]) && !isset($stockIndex[$key])) {
                $minLevel = floatval($rule->min_stock_level);
                if (0 <= $minLevel) {
                    $lowStockAlerts[] = [
                        'id'                  => 'rule-' . $rule->id,
                        'product_id'          => $rule->product_id,
                        'warehouse_id'        => $rule->warehouse_id,
                        'product_name'        => $rule->product->name,
                        'sku'                 => $rule->product->sku,
                        'warehouse_name'      => $rule->warehouse->name,
                        'current_qty'         => 0.0,
                        'min_qty'             => $minLevel,
                        'reorder_qty'         => floatval($rule->reorder_qty ?? max(10, $minLevel * 2)),
                        'preferred_vendor_id' => $rule->preferred_vendor_id,
                    ];
                }
            }
        }

        $expiringSoonBatches = $batches->filter(function ($b) {
            return $b->status === 'active'
                && $b->expiry_date !== null
                && $b->expiry_date->isBetween(now()->subDay(), now()->addDays(30));
        })->count();

        $stats = [
            'total_skus'            => count($stockValuation),
            'total_units'           => round($totalUnits, 2),
            'total_inventory_value' => round($totalInventoryValue, 2),
            'low_stock_count'       => count($lowStockAlerts),
            'pending_adjustments'   => $adjustments->where('status', 'pending')->count(),
            'active_serials'        => $serials->where('status', 'available')->count(),
            'active_batches'        => $batches->where('status', 'active')->count(),
            'expiring_soon_batches' => $expiringSoonBatches,
        ];

        $hasWorkflow = false;
        if (
            function_exists('module_is_active')
            && module_is_active('GadaacloudStudio', $companyId)
            && class_exists('Workdo\\GadaacloudStudio\\Services\\WorkflowService')
        ) {
            $hasWorkflow = \Workdo\GadaacloudStudio\Services\WorkflowService::getWorkflowForModel(
                InventoryAdjustment::class,
                $companyId
            ) !== null;
        }

        return Inertia::render('settings/inventory', [
            'warehouses'         => $warehouses,
            'products'           => $products,
            'vendors'            => $vendors,
            'serializedProducts' => $serializedProducts,
            'batchProducts'      => $batchProducts,
            'serials'            => $serials,
            'batches'            => $batches,
            'stockValuation'     => $stockValuation,
            'adjustments'        => $adjustments,
            'reorderRules'       => $reorderRules,
            'auditLogs'          => $auditLogs,
            'lowStockAlerts'     => $lowStockAlerts,
            'stats'              => $stats,
            'hasWorkflow'        => $hasWorkflow,
        ]);
    }

    /**
     * Save physical stock count adjustment (with GadaaCloud Studio approval check & Account ledger posting).
     */
    public function storeAdjustment(Request $request)
    {
        $this->authorizeInventory('create-stock-adjustments');

        $companyId = creatorId();

        $request->validate([
            'warehouse_id'    => ['required', Rule::exists('warehouses', 'id')->where('created_by', $companyId)],
            'product_id'      => ['required', Rule::exists('product_service_items', 'id')->where('created_by', $companyId)],
            'adjustment_type' => 'required|in:addition,subtraction',
            'quantity'        => 'required|numeric|min:0.0001',
            'unit_cost'       => 'nullable|numeric|min:0',
            'reason'          => 'required|string|max:255',
        ]);

        $adjQty = floatval($request->quantity);

        // Guard against negative stock on subtraction
        if ($request->adjustment_type === 'subtraction') {
            $currentQty = floatval(
                WarehouseStock::where('product_id', $request->product_id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->value('quantity') ?? 0
            );

            if ($currentQty < $adjQty) {
                return redirect()->back()->with('error', __('Insufficient stock for subtraction. Current warehouse balance: :qty', ['qty' => $currentQty]));
            }
        }

        DB::beginTransaction();
        try {
            $product = ProductServiceItem::where('created_by', $companyId)->find($request->product_id);
            $stock = WarehouseStock::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();

            $resolvedUnitCost = floatval($request->unit_cost ?? 0);
            if ($resolvedUnitCost <= 0) {
                $resolvedUnitCost = $stock && floatval($stock->unit_cost) > 0
                    ? floatval($stock->unit_cost)
                    : floatval($product?->purchase_price ?? 0);
            }

            $adjustment = InventoryAdjustment::create([
                'created_by'      => $companyId,
                'warehouse_id'    => $request->warehouse_id,
                'product_id'      => $request->product_id,
                'adjustment_type' => $request->adjustment_type,
                'quantity'        => $adjQty,
                'unit_cost'       => $resolvedUnitCost,
                'reason'          => $request->reason,
                'status'          => 'approved',
                'creator_id'      => Auth::id(),
            ]);

            // Check if GadaaCloud Studio has an active approval workflow for InventoryAdjustment
            $submittedWorkflow = null;
            if (
                function_exists('module_is_active')
                && module_is_active('GadaacloudStudio', $companyId)
                && class_exists('Workdo\\GadaacloudStudio\\Services\\WorkflowService')
            ) {
                $submittedWorkflow = \Workdo\GadaacloudStudio\Services\WorkflowService::submit(
                    $adjustment,
                    $companyId,
                    ['note' => $request->reason]
                );
            }

            if ($submittedWorkflow && $submittedWorkflow->status === 'pending') {
                $adjustment->updateQuietly([
                    'status'              => 'pending',
                    'workflow_request_id' => $submittedWorkflow->id,
                ]);
                DB::commit();
                return redirect()->back()->with('success', __('Stock adjustment submitted for GadaaCloud Studio approval. Stock & accounting will update once approved.'));
            }

            InventoryService::applyAdjustment($adjustment);

            DB::commit();

            return redirect()->back()->with('success', __('Stock adjusted, audit trail logged, and accounting ledger updated.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Stock adjustment failed: ') . $e->getMessage());
        }
    }

    /**
     * Approve a pending physical stock adjustment directly (when not waiting on Studio).
     */
    public function approveAdjustment(InventoryAdjustment $adjustment)
    {
        $this->authorizeInventory('manage-stock-adjustments');

        $companyId = creatorId();
        if (!Auth::user()->hasRole('superadmin') && (int) $adjustment->created_by !== (int) $companyId) {
            abort(403, __('Permission denied'));
        }

        if ($adjustment->status !== 'pending') {
            return redirect()->back()->with('error', __('Only pending adjustments can be approved.'));
        }

        DB::beginTransaction();
        try {
            $adjustment->status = 'approved';
            $adjustment->save(); // Triggers InventoryManagementServiceProvider hook -> InventoryService::applyAdjustment
            DB::commit();
            return redirect()->back()->with('success', __('Adjustment approved and posted to inventory & accounting ledger.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Approval failed: ') . $e->getMessage());
        }
    }

    /**
     * Save reorder threshold parameters.
     */
    public function storeReorderRule(Request $request)
    {
        $this->authorizeInventory('create-reorder-rules');

        $companyId = creatorId();

        $request->validate([
            'warehouse_id'        => ['required', Rule::exists('warehouses', 'id')->where('created_by', $companyId)],
            'product_id'          => ['required', Rule::exists('product_service_items', 'id')->where('created_by', $companyId)],
            'min_stock_level'     => 'required|numeric|min:0',
            'reorder_qty'         => 'nullable|numeric|min:1',
            'preferred_vendor_id' => ['nullable', Rule::exists('users', 'id')->where('created_by', $companyId)],
        ]);

        DB::transaction(function () use ($companyId, $request) {
            $rule = InventoryReorderRule::firstOrNew([
                'created_by'   => $companyId,
                'product_id'   => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
            ]);

            $rule->created_by          = $companyId;
            $rule->creator_id          = Auth::id();
            $rule->min_stock_level     = $request->min_stock_level;
            $rule->reorder_qty         = $request->reorder_qty ?? max(10, floatval($request->min_stock_level) * 2);
            $rule->preferred_vendor_id = $request->preferred_vendor_id ?: null;
            $rule->save();
        });

        return redirect()->back()->with('success', __('Safety stock reorder threshold rule set successfully.'));
    }

    /**
     * Bulk register serial / chassis numbers for a serialized product.
     */
    public function storeSerials(Request $request)
    {
        $this->authorizeInventory('create-serial-numbers');

        $companyId = creatorId();

        $request->validate([
            'product_id'   => ['required', Rule::exists('product_service_items', 'id')->where('created_by', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('created_by', $companyId)],
            'serials_text' => 'required|string',
            'action'       => 'required|in:add,mark_sold,mark_damaged',
        ]);

        $lines = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $request->serials_text)));

        if (empty($lines)) {
            return redirect()->back()->with('error', __('No valid serial numbers found.'));
        }

        DB::beginTransaction();
        try {
            $added = 0;
            $updated = 0;
            $product = ProductServiceItem::where('created_by', $companyId)->findOrFail($request->product_id);

            foreach ($lines as $serial) {
                if ($request->action === 'add') {
                    $exists = InventorySerial::where('created_by', $companyId)
                        ->where('product_id', $request->product_id)
                        ->where('serial_number', $serial)
                        ->exists();

                    if (!$exists) {
                        InventorySerial::create([
                            'created_by'    => $companyId,
                            'product_id'    => $request->product_id,
                            'warehouse_id'  => $request->warehouse_id,
                            'serial_number' => $serial,
                            'status'        => 'available',
                        ]);
                        $added++;
                    }
                } else {
                    $newStatus = $request->action === 'mark_sold' ? 'sold' : 'damaged';
                    $rows = InventorySerial::where('created_by', $companyId)
                        ->where('product_id', $request->product_id)
                        ->where('warehouse_id', $request->warehouse_id)
                        ->where('serial_number', $serial)
                        ->update(['status' => $newStatus]);
                    $updated += $rows;
                }
            }

            // Sync WarehouseStock quantity = count of 'available' serials and log explicit audit trail
            $availableCount = InventorySerial::where('created_by', $companyId)
                ->where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('status', 'available')
                ->count();

            \App\Services\InventoryAuditContext::run(
                'serial_sync',
                'InventorySerial',
                $request->product_id,
                function () use ($request, $availableCount, $product) {
                    $stock = WarehouseStock::firstOrNew([
                        'product_id'   => $request->product_id,
                        'warehouse_id' => $request->warehouse_id,
                    ]);
                    $unitCost = floatval($stock->unit_cost ?? 0) > 0
                        ? floatval($stock->unit_cost)
                        : floatval($product->purchase_price ?? 0);
                    $stock->quantity    = $availableCount;
                    $stock->unit_cost   = $unitCost;
                    $stock->total_value = round($availableCount * $unitCost, 4);
                    $stock->save();
                },
                "Serial bulk operation: {$request->action}"
            );

            DB::commit();

            $msg = $request->action === 'add'
                ? __(':count serial number(s) registered successfully.', ['count' => $added])
                : __(':count serial number(s) status updated.', ['count' => $updated]);

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Serial operation failed: ') . $e->getMessage());
        }
    }

    /**
     * Get serial numbers for a specific product (JSON).
     */
    public function getProductSerials(Request $request)
    {
        $this->authorizeInventory('manage-serial-numbers');

        $companyId = creatorId();
        $serials = InventorySerial::where('created_by', $companyId)
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->with(['warehouse'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($serials);
    }

    /**
     * Register a batch / lot (e.g. ECX coffee grade, agri lot, expiry-tracked goods) and increment stock.
     */
    public function storeBatch(Request $request)
    {
        $this->authorizeInventory('manage-inventory-management');

        $companyId = creatorId();

        $request->validate([
            'product_id'       => ['required', Rule::exists('product_service_items', 'id')->where('created_by', $companyId)],
            'warehouse_id'     => ['required', Rule::exists('warehouses', 'id')->where('created_by', $companyId)],
            'batch_number'     => 'required|string|max:120',
            'lot_grade'        => 'nullable|string|max:100',
            'origin'           => 'nullable|string|max:120',
            'manufacture_date' => 'nullable|date',
            'expiry_date'      => 'nullable|date|after_or_equal:manufacture_date',
            'quantity'         => 'required|numeric|min:0.0001',
            'unit_cost'        => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($companyId, $request) {
                $product = ProductServiceItem::where('created_by', $companyId)->findOrFail($request->product_id);
                $unitCost = floatval($request->unit_cost ?? 0) > 0
                    ? floatval($request->unit_cost)
                    : floatval($product->purchase_price ?? 0);

                InventoryService::receiveStock(
                    companyId: $companyId,
                    warehouseId: (int) $request->warehouse_id,
                    productId: (int) $request->product_id,
                    quantity: floatval($request->quantity),
                    unitCost: $unitCost,
                    actionType: 'batch_receipt',
                    referenceType: 'InventoryBatch',
                    referenceId: null,
                    notes: "Lot/Batch registered: {$request->batch_number}",
                    serials: [],
                    batchData: $request->only(['batch_number', 'lot_grade', 'origin', 'manufacture_date', 'expiry_date'])
                );
            });

            return redirect()->back()->with('success', __('Batch / Lot registered and added to warehouse valuation.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Batch registration failed: ') . $e->getMessage());
        }
    }

    /**
     * Update batch status (active / quarantined / expired).
     */
    public function updateBatchStatus(Request $request, InventoryBatch $batch)
    {
        $this->authorizeInventory('manage-inventory-management');

        $companyId = creatorId();
        if (!Auth::user()->hasRole('superadmin') && (int) $batch->created_by !== (int) $companyId) {
            abort(403, __('Permission denied'));
        }

        $request->validate([
            'status' => 'required|in:active,quarantined,expired',
        ]);

        DB::transaction(function () use ($batch, $request) {
            $batch->status = $request->status;
            $batch->save();
        });

        return redirect()->back()->with('success', __('Batch status updated to :status.', ['status' => $request->status]));
    }
}
