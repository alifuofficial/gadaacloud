<?php

namespace Workdo\ProductService\Listeners;

use App\Events\CompleteSalesReturn;
use App\Services\InventoryService;
use Workdo\ProductService\Models\WarehouseStock;

class CompleteSalesReturnListener
{
    public function handle(CompleteSalesReturn $event)
    {
        $salesReturn = $event->salesReturn;
        $companyId = (int) ($salesReturn->created_by ?? creatorId());

        foreach ($salesReturn->items()->get() as $item) {
            if (class_exists(InventoryService::class)) {
                InventoryService::receiveStock(
                    companyId: $companyId,
                    warehouseId: (int) $salesReturn->warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: floatval($item->return_quantity),
                    unitCost: 0.0,
                    actionType: 'sales_return',
                    referenceType: 'SalesReturn',
                    referenceId: (int) $salesReturn->id,
                    notes: 'Sales Return #' . ($salesReturn->return_number ?? $salesReturn->id)
                );
            } else {
                $stock = WarehouseStock::where('warehouse_id', $salesReturn->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->increment('quantity', $item->return_quantity);
                } else {
                    WarehouseStock::create([
                        'warehouse_id' => $salesReturn->warehouse_id,
                        'product_id'   => $item->product_id,
                        'quantity'     => $item->return_quantity,
                    ]);
                }
            }
        }
    }
}
