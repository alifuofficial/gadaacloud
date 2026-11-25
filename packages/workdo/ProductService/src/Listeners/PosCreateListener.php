<?php

namespace Workdo\ProductService\Listeners;

use App\Services\InventoryService;
use Workdo\Pos\Events\CreatePos;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class PosCreateListener
{
    public function handle(CreatePos $event)
    {
        $posSale = $event->posSale;
        if (empty($posSale->warehouse_id)) {
            return;
        }

        $companyId = (int) ($posSale->created_by ?? creatorId());
        $allowNegative = function_exists('company_setting')
            ? company_setting('allow_negative_stock', $companyId) !== 'off'
            : true;

        $items = $posSale->items()->get();
        $productTypes = ProductServiceItem::whereIn('id', $items->pluck('product_id')->filter()->unique())
            ->pluck('type', 'id');

        foreach ($items as $item) {
            // Do not deduct inventory for non-stock service lines sold on POS
            if (($productTypes[$item->product_id] ?? 'product') === 'service') {
                continue;
            }

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
                    allowNegative: $allowNegative
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
