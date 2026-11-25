<?php

namespace Workdo\ProductService\Listeners;

use App\Events\PostPurchaseInvoice;
use App\Services\InventoryService;
use Workdo\ProductService\Models\WarehouseStock;

class PostPurchaseInvoiceListener
{
    public function handle(PostPurchaseInvoice $event)
    {
        $purchaseInvoice = $event->purchaseInvoice;
        $companyId = (int) ($purchaseInvoice->created_by ?? creatorId());

        foreach ($purchaseInvoice->items()->get() as $item) {
            if (class_exists(InventoryService::class)) {
                InventoryService::receiveStock(
                    companyId: $companyId,
                    warehouseId: (int) $purchaseInvoice->warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: floatval($item->quantity),
                    unitCost: floatval($item->unit_price ?? 0),
                    actionType: 'purchase',
                    referenceType: 'PurchaseInvoice',
                    referenceId: (int) $purchaseInvoice->id,
                    notes: 'Purchase Invoice #' . ($purchaseInvoice->invoice_number ?? $purchaseInvoice->id)
                );
            } else {
                $stock = WarehouseStock::where('warehouse_id', $purchaseInvoice->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->increment('quantity', $item->quantity);
                } else {
                    WarehouseStock::create([
                        'warehouse_id' => $purchaseInvoice->warehouse_id,
                        'product_id'   => $item->product_id,
                        'quantity'     => $item->quantity,
                    ]);
                }
            }
        }
    }
}
