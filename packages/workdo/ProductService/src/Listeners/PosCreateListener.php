<?php

namespace Workdo\ProductService\Listeners;

use App\Services\InventoryService;
use Workdo\Pos\Events\CreatePos;
use Workdo\ProductService\Models\WarehouseStock;

class PosCreateListener
{
    public function handle(CreatePos $event)
    {
        $posSale = $event->posSale;
        $companyId = (int) ($posSale->created_by ?? creatorId());

        foreach ($posSale->items()->get() as $item) {
            if (class_exists(InventoryService::class)) {
                InventoryService::deductStock(
                    companyId: $companyId,
                    warehouseId: (int) $posSale->warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: floatval($item->quantity),
                    actionType: 'pos_sale',
                    referenceType: 'PosSale',
                    referenceId: (int) $posSale->id,
                    notes: 'POS Sale #' . ($posSale->sale_number ?? $posSale->id),
                    allowNegative: true
                );
            } else {
                $stock = WarehouseStock::where('warehouse_id', $posSale->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->decrement('quantity', $item->quantity);
                }
            }
        }
    }
}
