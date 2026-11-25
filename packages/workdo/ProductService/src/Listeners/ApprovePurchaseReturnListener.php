<?php

namespace Workdo\ProductService\Listeners;

use App\Events\ApprovePurchaseReturn;
use App\Services\InventoryService;
use Workdo\ProductService\Models\WarehouseStock;

class ApprovePurchaseReturnListener
{
    public function handle(ApprovePurchaseReturn $event)
    {
        $return = $event->return;
        $companyId = (int) ($return->created_by ?? creatorId());

        foreach ($return->items()->get() as $item) {
            if (class_exists(InventoryService::class)) {
                InventoryService::deductStock(
                    companyId: $companyId,
                    warehouseId: (int) $return->warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: floatval($item->return_quantity),
                    actionType: 'purchase_return',
                    referenceType: 'PurchaseReturn',
                    referenceId: (int) $return->id,
                    notes: 'Purchase Return #' . ($return->return_number ?? $return->id),
                    allowNegative: true
                );
            } else {
                $stock = WarehouseStock::where('warehouse_id', $return->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->decrement('quantity', $item->return_quantity);
                }
            }
        }
    }
}
