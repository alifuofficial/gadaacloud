<?php

namespace Workdo\ProductService\Listeners;

use App\Services\InventoryService;
use Workdo\ProductService\Models\WarehouseStock;

/**
 * Handles stock deduction when a sales retainer (if the optional Retainer package is active) converts to an invoice.
 */
class ConvertSalesRetainerListener
{
    public function handle(object $event): void
    {
        $retainer = $event->retainer ?? $event->salesInvoice ?? null;
        if (!$retainer || ($retainer->type ?? 'product') !== 'product' || empty($retainer->warehouse_id)) {
            return;
        }

        $companyId = (int) ($retainer->created_by ?? creatorId());
        $allowNegative = function_exists('company_setting')
            ? company_setting('allow_negative_stock', $companyId) !== 'off'
            : true;

        foreach ($retainer->items()->get() as $item) {
            if (class_exists(InventoryService::class)) {
                InventoryService::deductStock(
                    companyId: $companyId,
                    warehouseId: (int) $retainer->warehouse_id,
                    productId: (int) $item->product_id,
                    quantity: floatval($item->quantity),
                    actionType: 'retainer_conversion',
                    referenceType: 'SalesRetainer',
                    referenceId: (int) $retainer->id,
                    notes: 'Retainer Conversion #' . ($retainer->retainer_number ?? $retainer->id),
                    allowNegative: $allowNegative
                );
            } else {
                $stock = WarehouseStock::where('warehouse_id', $retainer->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($stock) {
                    $stock->decrement('quantity', $item->quantity);
                }
            }
        }
    }
}
