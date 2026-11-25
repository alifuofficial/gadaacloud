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

        if ($salesInvoice->type === 'product' && !empty($salesInvoice->warehouse_id)) {
            $companyId = (int) ($salesInvoice->created_by ?? creatorId());
            $allowNegative = function_exists('company_setting')
                ? company_setting('allow_negative_stock', $companyId) !== 'off'
                : true;

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
                        allowNegative: $allowNegative
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
