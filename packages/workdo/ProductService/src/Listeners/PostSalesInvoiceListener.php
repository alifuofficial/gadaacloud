<?php

namespace Workdo\ProductService\Listeners;

use App\Events\PostSalesInvoice;
use App\Services\InventoryService;
use Workdo\ProductService\Models\WarehouseStock;

class PostSalesInvoiceListener
{
    public function handle(PostSalesInvoice $event)
    {
        $salesInvoice = $event->salesInvoice;

        if ($salesInvoice->type === 'product') {
            $companyId = (int) ($salesInvoice->created_by ?? creatorId());
            foreach ($salesInvoice->items()->get() as $item) {
                if (class_exists(InventoryService::class)) {
                    InventoryService::deductStock(
                        companyId: $companyId,
                        warehouseId: (int) $salesInvoice->warehouse_id,
                        productId: (int) $item->product_id,
                        quantity: floatval($item->quantity),
                        actionType: 'sale',
                        referenceType: 'SalesInvoice',
                        referenceId: (int) $salesInvoice->id,
                        notes: 'Sales Invoice #' . ($salesInvoice->invoice_number ?? $salesInvoice->id),
                        allowNegative: true
                    );
                } else {
                    $stock = WarehouseStock::where('warehouse_id', $salesInvoice->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->first();
                    if ($stock) {
                        $stock->decrement('quantity', $item->quantity);
                    }
                }
            }
        }
    }
}
