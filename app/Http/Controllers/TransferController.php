<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;
use App\Http\Requests\StoreTransferRequest;
use App\Events\CreateTransfer;
use App\Events\DestroyTransfer;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-transfers')){
            $transfers = Transfer::query()
                ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'product:id,name,sku'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-transfers')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-transfers')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('product_name'), function($q) {
                    $q->whereHas('product', function($query) {
                        $query->where('name', 'like', '%' . request('product_name') . '%');
                    });
                })
                ->when(request('from_warehouse'), function($q) {
                    $q->where('from_warehouse', request('from_warehouse'));
                })
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $warehouses = Warehouse::where('created_by', creatorId())->where('is_active', true)->get(['id', 'name']);
            $products = ProductServiceItem::where('created_by', creatorId())->get(['id', 'name', 'sku']);
            $warehouseStocks = WarehouseStock::with('product:id,name,sku')
                ->whereHas('product', function($q) {
                    $q->where('created_by', creatorId());
                })
                ->where('quantity', '>', 0)
                ->get();

            return Inertia::render('Transfers/Index', [
                'transfers' => $transfers,
                'warehouses' => $warehouses,
                'products' => $products,
                'warehouseStocks' => $warehouseStocks,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        // Modal-based create, no separate page needed
        return back();
    }

    public function store(StoreTransferRequest $request)
    {
        if(Auth::user()->can('create-transfers')){
            $validated = $request->validated();

            $transfer = new Transfer();
            $transfer->waybill_number = Transfer::generateWaybillNumber();
            $transfer->from_warehouse = $validated['from_warehouse'];
            $transfer->to_warehouse = $validated['to_warehouse'];
            $transfer->product_id = $validated['product_id'];
            $transfer->quantity = $validated['quantity'];
            $transfer->date = $validated['date'];
            $transfer->status = 'in_transit'; // Goods are in transit on road
            $transfer->driver_name = $request->driver_name;
            $transfer->driver_phone = $request->driver_phone;
            $transfer->vehicle_plate_number = $request->vehicle_plate_number;
            $transfer->dispatched_at = now();
            $transfer->notes = $request->notes;
            $transfer->creator_id = Auth::id();
            $transfer->created_by = creatorId();
            $transfer->save();

            // Update warehouse stocks: decrease from source warehouse only
            // Destination warehouse stock is NOT incremented until goods are physically received
            $fromStock = WarehouseStock::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['from_warehouse'])
                ->first();

            if ($fromStock) {
                $fromStock->quantity = max(0, $fromStock->quantity - $validated['quantity']);
                $fromStock->save();
            }

            // Dispatch event for packages to handle their fields
            CreateTransfer::dispatch($request, $transfer);

            return back()->with('success', __('Shipment dispatched with Waybill :wb.', ['wb' => $transfer->waybill_number]));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    /**
     * Confirm receipt of goods at destination warehouse (with transit damage/shortage tracking).
     */
    public function receive(\Illuminate\Http\Request $request, Transfer $transfer)
    {
        if (!Auth::user()->can('edit-transfers') && !Auth::user()->can('manage-transfers')) {
            return back()->with('error', __('Permission denied'));
        }

        if (!Auth::user()->hasRole('superadmin') && $transfer->created_by !== creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($transfer->status === 'received') {
            return back()->with('error', __('This transfer has already been received.'));
        }

        $receivedQty = (float) $request->input('received_quantity', $transfer->quantity);
        $damagedQty = (float) $request->input('damaged_quantity', 0);
        $reason = $request->input('discrepancy_reason');

        // Increase in destination warehouse for actually received undamaged goods
        $toStock = WarehouseStock::firstOrCreate(
            [
                'product_id' => $transfer->product_id,
                'warehouse_id' => $transfer->to_warehouse,
            ],
            ['quantity' => 0]
        );
        $toStock->quantity += $receivedQty;
        $toStock->save();

        $transfer->received_quantity = $receivedQty;
        $transfer->damaged_quantity = $damagedQty;
        $transfer->discrepancy_reason = $reason;
        $transfer->status = $damagedQty > 0 ? 'discrepancy' : 'received';
        $transfer->received_at = now();
        $transfer->save();

        $msg = __('Shipment received successfully.');
        if ($damagedQty > 0) {
            $msg .= ' ' . __('Transit discrepancy noted: :qty damaged/short.', ['qty' => $damagedQty]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Render / Print Official Ethiopian Inter-Branch Waybill (የጭነት ማስተላለፊያ ደረሰኝ).
     */
    public function waybill(Transfer $transfer)
    {
        if (!Auth::user()->can('view-transfers')) {
            return back()->with('error', __('Permission denied'));
        }

        if (!Auth::user()->hasRole('superadmin') && $transfer->created_by !== creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $transfer->load(['fromWarehouse', 'toWarehouse', 'product']);

        $settings = getCompanyAllSetting($transfer->created_by);
        $companyName = $settings['company_name'] ?? config('app.name', 'GadaaCloud');
        $companyVat = $settings['vat_number'] ?? $settings['tax_number'] ?? 'N/A';

        return response()->view('transfers.waybill', [
            'transfer' => $transfer,
            'companyName' => $companyName,
            'companyVat' => $companyVat,
        ]);
    }

    public function show(Transfer $transfer)
    {
        if(Auth::user()->can('view-transfers')){
            $transfer->load(['fromWarehouse', 'toWarehouse', 'product']);

            return Inertia::render('Transfers/Show', [
                'transfer' => $transfer,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Transfer $transfer)
    {
        if(Auth::user()->can('delete-transfers')){
            if (!Auth::user()->hasRole('superadmin') && $transfer->created_by !== creatorId()) {
                return back()->with('error', __('Permission denied'));
            }

            // Restore stock to source warehouse
            $fromStock = WarehouseStock::firstOrCreate(
                [
                    'product_id' => $transfer->product_id,
                    'warehouse_id' => $transfer->from_warehouse,
                ],
                ['quantity' => 0]
            );
            $fromStock->quantity += $transfer->quantity;
            $fromStock->save();

            // If the transfer was already received at destination, deduct from destination
            if ($transfer->status === 'received' || $transfer->status === 'discrepancy') {
                $toStock = WarehouseStock::where('product_id', $transfer->product_id)
                    ->where('warehouse_id', $transfer->to_warehouse)
                    ->first();

                $receivedQty = $transfer->received_quantity ?: $transfer->quantity;
                if ($toStock) {
                    $toStock->quantity = max(0, $toStock->quantity - $receivedQty);
                    $toStock->save();
                }
            }

            DestroyTransfer::dispatch($transfer);

            $transfer->delete();

            return back()->with('success', __('The transfer has been deleted.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
