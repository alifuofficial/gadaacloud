<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryReorderRule;
use App\Models\InventoryAuditLog;
use App\Models\InventorySerial;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class InventoryController extends Controller
{
    /**
     * Inventory Dashboard index view
     */
    public function index()
    {
        $companyId = creatorId();

        $warehouses = Warehouse::orderBy('name', 'asc')->get(['id', 'name']);
        
        $products = ProductServiceItem::where('created_by', $companyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'sku', 'sale_price', 'inventory_type']);

        // Serialized products & their registered serial numbers
        $serializedProducts = $products->where('inventory_type', 'serialized')->values();
        $serials = InventorySerial::with(['product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->get();

        $adjustments = InventoryAdjustment::with(['product', 'warehouse', 'adjustedBy'])
            ->orderBy('id', 'desc')
            ->get();

        $reorderRules = InventoryReorderRule::with(['product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->get();

        $auditLogs = InventoryAuditLog::with(['product', 'warehouse', 'creator'])
            ->orderBy('id', 'desc')
            ->take(500)
            ->get();

        // Calculate low stock alert items
        // Fetch current stocks and match with reorder rule limits
        $stocks = WarehouseStock::with(['product', 'warehouse'])->get();
        $lowStockAlerts = [];

        foreach ($stocks as $stock) {
            // Find specific rule for this product & warehouse
            $rule = InventoryReorderRule::where('product_id', $stock->product_id)
                ->where('warehouse_id', $stock->warehouse_id)
                ->first();

            $minLevel = $rule ? floatval($rule->min_stock_level) : 5.0; // 5 is system default fallback limit

            if (floatval($stock->quantity) <= $minLevel) {
                $lowStockAlerts[] = [
                    'id' => $stock->id,
                    'product_name' => $stock->product ? $stock->product->name : __('Unknown Product'),
                    'sku' => $stock->product ? $stock->product->sku : __('N/A'),
                    'warehouse_name' => $stock->warehouse ? $stock->warehouse->name : __('Unknown Warehouse'),
                    'current_qty' => floatval($stock->quantity),
                    'min_qty' => $minLevel,
                ];
            }
        }

        return Inertia::render('settings/inventory', [
            'warehouses' => $warehouses,
            'products' => $products,
            'serializedProducts' => $serializedProducts,
            'serials' => $serials,
            'adjustments' => $adjustments,
            'reorderRules' => $reorderRules,
            'auditLogs' => $auditLogs,
            'lowStockAlerts' => $lowStockAlerts
        ]);
    }

    /**
     * Save physical stock count adjustment (Stocktake)
     */
    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:product_service_items,id',
            'adjustment_type' => 'required|in:addition,subtraction',
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'required|string|max:255',
        ]);

        $companyId = creatorId();

        DB::beginTransaction();
        try {
            // Fetch or create raw warehouse stock entry
            $stock = WarehouseStock::firstOrNew([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id
            ]);

            $startQty = floatval($stock->quantity ?? 0.0);
            $adjQty = floatval($request->quantity);

            if ($request->adjustment_type === 'addition') {
                $finalQty = $startQty + $adjQty;
                $delta = $adjQty;
            } else {
                $finalQty = $startQty - $adjQty;
                $delta = -$adjQty;
            }

            // Save new stock count
            $stock->quantity = $finalQty;
            $stock->save();

             // Create adjustment log
            $adjustment = new InventoryAdjustment();
            $adjustment->created_by = $companyId;
            $adjustment->warehouse_id = $request->warehouse_id;
            $adjustment->product_id = $request->product_id;
            $adjustment->adjustment_type = $request->adjustment_type;
            $adjustment->quantity = $adjQty;
            $adjustment->reason = $request->reason;
            $adjustment->creator_id = Auth::id();
            $adjustment->save();

            // Create Inventory Audit Trail Log
            InventoryAuditLog::create([
                'created_by' => $companyId,
                'warehouse_id' => $request->warehouse_id,
                'product_id' => $request->product_id,
                'action_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'qty_delta' => $delta,
                'final_qty' => $finalQty,
                'creator_id' => Auth::id()
            ]);

            DB::commit();

            return redirect()->back()->with('success', __('Stock adjusted and logged in audit trails successfully.'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Stock adjustment failed: ') . $e->getMessage());
        }
    }

    /**
     * Save reorder threshold parameters
     */
    public function storeReorderRule(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:product_service_items,id',
            'min_stock_level' => 'required|numeric|min:0',
        ]);

        $companyId = creatorId();

        $rule = InventoryReorderRule::firstOrNew([
            'product_id' => $request->product_id,
            'warehouse_id' => $request->warehouse_id
        ]);

        $rule->created_by = $companyId;
        $rule->creator_id = Auth::id();
        $rule->min_stock_level = $request->min_stock_level;
        $rule->save();

        return redirect()->back()->with('success', __('Safety stock reorder threshold rule set successfully.'));
    }

    /**
     * Bulk register serial / chassis numbers for a serialized product
     */
    public function storeSerials(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|exists:product_service_items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'serials_text' => 'required|string',
            'action'       => 'required|in:add,mark_sold,mark_damaged',
        ]);

        $companyId = creatorId();
        $lines = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $request->serials_text)));

        if (empty($lines)) {
            return redirect()->back()->with('error', __('No valid serial numbers found.'));
        }

        DB::beginTransaction();
        try {
            $added = 0;
            $updated = 0;

            foreach ($lines as $serial) {
                if ($request->action === 'add') {
                    $exists = InventorySerial::where('created_by', $companyId)
                        ->where('product_id', $request->product_id)
                        ->where('serial_number', $serial)
                        ->exists();

                    if (!$exists) {
                        InventorySerial::create([
                            'created_by'   => $companyId,
                            'product_id'   => $request->product_id,
                            'warehouse_id' => $request->warehouse_id,
                            'serial_number' => $serial,
                            'status'       => 'available',
                        ]);
                        $added++;
                    }
                } else {
                    $newStatus = $request->action === 'mark_sold' ? 'sold' : 'damaged';
                    $rows = InventorySerial::where('created_by', $companyId)
                        ->where('product_id', $request->product_id)
                        ->where('serial_number', $serial)
                        ->update(['status' => $newStatus]);
                    $updated += $rows;
                }
            }

            // Sync WarehouseStock quantity = count of 'available' serials
            $availableCount = InventorySerial::where('created_by', $companyId)
                ->where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('status', 'available')
                ->count();

            WarehouseStock::updateOrCreate(
                ['product_id' => $request->product_id, 'warehouse_id' => $request->warehouse_id],
                ['quantity' => $availableCount]
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
     * Get serial numbers for a specific product (JSON)
     */
    public function getProductSerials(Request $request)
    {
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
}
