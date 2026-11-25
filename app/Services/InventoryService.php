<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAuditLog;
use App\Models\InventoryBatch;
use App\Models\InventorySerial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class InventoryService
{
    /**
     * Receive stock into a warehouse with weighted-average costing, optional serials, and optional batch lot tracking.
     */
    public static function receiveStock(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        float $unitCost = 0.0,
        string $actionType = 'purchase',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        array $serials = [],
        ?array $batchData = null
    ): WarehouseStock {
        return DB::transaction(function () use (
            $companyId, $warehouseId, $productId, $quantity, $unitCost,
            $actionType, $referenceType, $referenceId, $notes, $serials, $batchData
        ) {
            return InventoryAuditContext::run(
                $actionType,
                $referenceType,
                $referenceId,
                function () use (
                    $companyId, $warehouseId, $productId, $quantity, $unitCost,
                    $actionType, $referenceType, $referenceId, $notes, $serials, $batchData
                ) {
                    $product = ProductServiceItem::where('created_by', $companyId)->find($productId);

                    $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        $stock = new WarehouseStock([
                            'warehouse_id' => $warehouseId,
                            'product_id'   => $productId,
                            'quantity'     => 0,
                            'unit_cost'    => 0,
                            'total_value'  => 0,
                        ]);
                    }

                    $oldQty  = floatval($stock->quantity ?? 0);
                    $oldCost = floatval($stock->unit_cost ?? ($product ? $product->purchase_price : 0));
                    $inCost  = $unitCost > 0 ? $unitCost : $oldCost;
                    $newQty  = $oldQty + $quantity;

                    if ($newQty > 0 && $oldQty >= 0) {
                        $weightedCost = (($oldQty * $oldCost) + ($quantity * $inCost)) / $newQty;
                    } else {
                        $weightedCost = $inCost;
                    }

                    $stock->quantity    = $newQty;
                    $stock->unit_cost   = round($weightedCost, 4);
                    $stock->total_value = round(max(0, $newQty) * $stock->unit_cost, 4);
                    $stock->save();

                    // Register serial numbers if provided
                    if (!empty($serials)) {
                        foreach ($serials as $serialNo) {
                            $serialNo = trim((string) $serialNo);
                            if ($serialNo === '') {
                                continue;
                            }
                            InventorySerial::firstOrCreate(
                                [
                                    'created_by'    => $companyId,
                                    'product_id'    => $productId,
                                    'serial_number' => $serialNo,
                                ],
                                [
                                    'warehouse_id'        => $warehouseId,
                                    'status'              => 'available',
                                    'purchase_invoice_id' => $referenceType === 'PurchaseInvoice' ? $referenceId : null,
                                ]
                            );
                        }
                    }

                    // Register or top up batch/lot if provided
                    if (!empty($batchData['batch_number'])) {
                        $batch = InventoryBatch::where('created_by', $companyId)
                            ->where('product_id', $productId)
                            ->where('warehouse_id', $warehouseId)
                            ->where('batch_number', $batchData['batch_number'])
                            ->lockForUpdate()
                            ->first();

                        if ($batch) {
                            $batch->initial_quantity   = floatval($batch->initial_quantity) + $quantity;
                            $batch->remaining_quantity = floatval($batch->remaining_quantity) + $quantity;
                            $batch->status             = 'active';
                            if ($inCost > 0) {
                                $batch->unit_cost = $inCost;
                            }
                            $batch->save();
                        } else {
                            InventoryBatch::create([
                                'created_by'          => $companyId,
                                'creator_id'          => Auth::id() ?? $companyId,
                                'product_id'          => $productId,
                                'warehouse_id'        => $warehouseId,
                                'batch_number'        => $batchData['batch_number'],
                                'lot_grade'           => $batchData['lot_grade'] ?? null,
                                'origin'              => $batchData['origin'] ?? null,
                                'manufacture_date'    => $batchData['manufacture_date'] ?? null,
                                'expiry_date'         => $batchData['expiry_date'] ?? null,
                                'initial_quantity'    => $quantity,
                                'remaining_quantity'  => $quantity,
                                'unit_cost'           => $inCost,
                                'purchase_invoice_id' => $referenceType === 'PurchaseInvoice' ? $referenceId : null,
                                'status'              => 'active',
                            ]);
                        }
                    }

                    InventoryAuditLog::create([
                        'created_by'     => $companyId,
                        'warehouse_id'   => $warehouseId,
                        'product_id'     => $productId,
                        'action_type'    => $actionType,
                        'reference_type' => $referenceType,
                        'reference_id'   => $referenceId,
                        'qty_delta'      => $quantity,
                        'final_qty'      => $newQty,
                        'notes'          => $notes,
                        'creator_id'     => Auth::id() ?? $companyId,
                    ]);

                    return $stock;
                },
                $notes,
                suppressObserver: true
            );
        });
    }

    /**
     * Deduct stock from a warehouse, syncing serialized & FEFO batch records and logging an explicit audit trail.
     */
    public static function deductStock(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $quantity,
        string $actionType = 'sale',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        array $serials = [],
        bool $allowNegative = true
    ): ?WarehouseStock {
        return DB::transaction(function () use (
            $companyId, $warehouseId, $productId, $quantity,
            $actionType, $referenceType, $referenceId, $notes, $serials, $allowNegative
        ) {
            return InventoryAuditContext::run(
                $actionType,
                $referenceType,
                $referenceId,
                function () use (
                    $companyId, $warehouseId, $productId, $quantity,
                    $actionType, $referenceType, $referenceId, $notes, $serials, $allowNegative
                ) {
                    $product = ProductServiceItem::where('created_by', $companyId)->find($productId);

                    $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        if (!$allowNegative) {
                            throw new \RuntimeException(__('Insufficient stock: no stock found in the selected warehouse.'));
                        }
                        return null;
                    }

                    $oldQty = floatval($stock->quantity ?? 0);
                    if (!$allowNegative && $oldQty < $quantity) {
                        throw new \RuntimeException(__('Insufficient stock in warehouse. Available: :avail, Requested: :req', [
                            'avail' => $oldQty,
                            'req'   => $quantity,
                        ]));
                    }

                    $newQty = $oldQty - $quantity;
                    $unitCost = floatval($stock->unit_cost ?? ($product ? $product->purchase_price : 0));

                    $stock->quantity    = $newQty;
                    $stock->unit_cost   = $unitCost;
                    $stock->total_value = round(max(0, $newQty) * $unitCost, 4);
                    $stock->save();

                    // Sync serialized tracking (explicit serials or FIFO auto-allocation)
                    if ($product && $product->inventory_type === 'serialized') {
                        $targetStatus = ($actionType === 'adjustment' || str_contains((string) $notes, 'damaged'))
                            ? 'damaged'
                            : 'sold';

                        $intCount = (int) round($quantity);
                        $serialQuery = InventorySerial::where('created_by', $companyId)
                            ->where('product_id', $productId)
                            ->where('warehouse_id', $warehouseId)
                            ->where('status', 'available');

                        if (!empty($serials)) {
                            $serialQuery->whereIn('serial_number', $serials);
                        } else {
                            $serialQuery->orderBy('id', 'asc')->limit(max(0, $intCount));
                        }

                        $matchedSerials = $serialQuery->get();
                        foreach ($matchedSerials as $serialModel) {
                            $serialModel->status = $targetStatus;
                            if ($referenceType === 'SalesInvoice' && $referenceId) {
                                $serialModel->sales_invoice_id = $referenceId;
                            }
                            $serialModel->save();
                        }
                    }

                    // Sync batch tracking via FEFO (First-Expired, First-Out)
                    if ($product && $product->inventory_type === 'batch') {
                        $remainingToDeduct = $quantity;
                        $batches = InventoryBatch::where('created_by', $companyId)
                            ->where('product_id', $productId)
                            ->where('warehouse_id', $warehouseId)
                            ->where('status', 'active')
                            ->where('remaining_quantity', '>', 0)
                            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
                            ->orderBy('expiry_date', 'asc')
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->get();

                        foreach ($batches as $batch) {
                            if ($remainingToDeduct <= 0) {
                                break;
                            }
                            $avail = floatval($batch->remaining_quantity);
                            $take = min($avail, $remainingToDeduct);
                            $batch->remaining_quantity = $avail - $take;
                            if ($batch->remaining_quantity <= 0.0001) {
                                $batch->remaining_quantity = 0;
                                $batch->status = 'depleted';
                            }
                            $batch->save();
                            $remainingToDeduct -= $take;
                        }
                    }

                    InventoryAuditLog::create([
                        'created_by'     => $companyId,
                        'warehouse_id'   => $warehouseId,
                        'product_id'     => $productId,
                        'action_type'    => $actionType,
                        'reference_type' => $referenceType,
                        'reference_id'   => $referenceId,
                        'qty_delta'      => -$quantity,
                        'final_qty'      => $newQty,
                        'notes'          => $notes,
                        'creator_id'     => Auth::id() ?? $companyId,
                    ]);

                    return $stock;
                },
                $notes,
                suppressObserver: true
            );
        });
    }

    /**
     * Apply an approved InventoryAdjustment to WarehouseStock + post Account ledger entry.
     */
    public static function applyAdjustment(InventoryAdjustment $adjustment): void
    {
        $companyId = (int) $adjustment->created_by;
        $qty = floatval($adjustment->quantity);

        if ($adjustment->adjustment_type === 'addition') {
            $stock = self::receiveStock(
                companyId: $companyId,
                warehouseId: (int) $adjustment->warehouse_id,
                productId: (int) $adjustment->product_id,
                quantity: $qty,
                unitCost: floatval($adjustment->unit_cost ?? 0),
                actionType: 'adjustment',
                referenceType: 'InventoryAdjustment',
                referenceId: $adjustment->id,
                notes: $adjustment->reason
            );
        } else {
            $stock = self::deductStock(
                companyId: $companyId,
                warehouseId: (int) $adjustment->warehouse_id,
                productId: (int) $adjustment->product_id,
                quantity: $qty,
                actionType: 'adjustment',
                referenceType: 'InventoryAdjustment',
                referenceId: $adjustment->id,
                notes: $adjustment->reason,
                allowNegative: false
            );
        }

        // Capture resolved unit_cost on adjustment if not already stored
        if (floatval($adjustment->unit_cost ?? 0) <= 0 && $stock && floatval($stock->unit_cost) > 0) {
            $adjustment->unit_cost = $stock->unit_cost;
            $adjustment->saveQuietly();
        }

        // Post accounting journal if Account module is active
        self::postAccountingAdjustment($adjustment);
    }

    /**
     * Safely post accounting journal entry when Account module is active.
     */
    public static function postAccountingAdjustment(InventoryAdjustment $adjustment): void
    {
        try {
            $companyId = (int) $adjustment->created_by;
            if (
                function_exists('module_is_active')
                && module_is_active('Account', $companyId)
                && class_exists('Workdo\\Account\\Services\\JournalService')
            ) {
                $journalService = app(\Workdo\Account\Services\JournalService::class);
                if (method_exists($journalService, 'createInventoryAdjustmentJournal')) {
                    $journalService->createInventoryAdjustmentJournal($adjustment);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Inventory adjustment accounting journal skipped for #{$adjustment->id}: " . $e->getMessage());
        }
    }

    /**
     * Move serialized and batch records when an inter-branch Transfer is received.
     */
    public static function syncTransferReceipt(
        int $companyId,
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $receivedQty,
        float $damagedQty,
        int $transferId,
        ?string $waybillNumber = null,
        ?string $discrepancyReason = null
    ): void {
        $product = ProductServiceItem::where('created_by', $companyId)->find($productId);

        if ($product && $product->inventory_type === 'serialized') {
            $moveCount = (int) round($receivedQty);
            if ($moveCount > 0) {
                $serialsToMove = InventorySerial::where('created_by', $companyId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $fromWarehouseId)
                    ->where('status', 'available')
                    ->orderBy('id', 'asc')
                    ->limit($moveCount)
                    ->get();

                foreach ($serialsToMove as $s) {
                    $s->warehouse_id = $toWarehouseId;
                    $s->save();
                }
            }

            $damageCount = (int) round($damagedQty);
            if ($damageCount > 0) {
                $damagedSerials = InventorySerial::where('created_by', $companyId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $fromWarehouseId)
                    ->where('status', 'available')
                    ->orderBy('id', 'asc')
                    ->limit($damageCount)
                    ->get();

                foreach ($damagedSerials as $s) {
                    $s->status = 'damaged';
                    $s->save();
                }
            }
        }

        if ($product && $product->inventory_type === 'batch' && $receivedQty > 0) {
            $remaining = $receivedQty;
            $sourceBatches = InventoryBatch::where('created_by', $companyId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($sourceBatches as $srcBatch) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min(floatval($srcBatch->remaining_quantity), $remaining);
                $srcBatch->remaining_quantity = floatval($srcBatch->remaining_quantity) - $take;
                if ($srcBatch->remaining_quantity <= 0.0001) {
                    $srcBatch->remaining_quantity = 0;
                    $srcBatch->status = 'depleted';
                }
                $srcBatch->save();

                // Mirror batch in destination warehouse
                $destBatch = InventoryBatch::where('created_by', $companyId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $toWarehouseId)
                    ->where('batch_number', $srcBatch->batch_number)
                    ->first();

                if ($destBatch) {
                    $destBatch->initial_quantity   = floatval($destBatch->initial_quantity) + $take;
                    $destBatch->remaining_quantity = floatval($destBatch->remaining_quantity) + $take;
                    $destBatch->status             = 'active';
                    $destBatch->save();
                } else {
                    InventoryBatch::create([
                        'created_by'         => $companyId,
                        'creator_id'         => Auth::id() ?? $companyId,
                        'product_id'         => $productId,
                        'warehouse_id'       => $toWarehouseId,
                        'batch_number'       => $srcBatch->batch_number,
                        'lot_grade'          => $srcBatch->lot_grade,
                        'origin'             => $srcBatch->origin,
                        'manufacture_date'   => $srcBatch->manufacture_date,
                        'expiry_date'        => $srcBatch->expiry_date,
                        'initial_quantity'   => $take,
                        'remaining_quantity' => $take,
                        'unit_cost'          => $srcBatch->unit_cost,
                        'status'             => 'active',
                    ]);
                }

                $remaining -= $take;
            }
        }

        // If transit damage occurred, record shrinkage adjustment & accounting journal
        if ($damagedQty > 0) {
            $sourceStock = WarehouseStock::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->first();
            $unitCost = $sourceStock && floatval($sourceStock->unit_cost) > 0
                ? floatval($sourceStock->unit_cost)
                : ($product ? floatval($product->purchase_price ?? 0) : 0);

            $adj = InventoryAdjustment::create([
                'created_by'      => $companyId,
                'warehouse_id'    => $toWarehouseId,
                'product_id'      => $productId,
                'adjustment_type' => 'subtraction',
                'quantity'        => $damagedQty,
                'unit_cost'       => $unitCost,
                'reason'          => trim("Transit damage on Waybill {$waybillNumber}: " . ($discrepancyReason ?: 'Reported on receipt')),
                'status'          => 'approved',
                'creator_id'      => Auth::id() ?? $companyId,
            ]);

            InventoryAuditLog::create([
                'created_by'     => $companyId,
                'warehouse_id'   => $toWarehouseId,
                'product_id'     => $productId,
                'action_type'    => 'transfer_damage',
                'reference_type' => 'Transfer',
                'reference_id'   => $transferId,
                'qty_delta'      => -$damagedQty,
                'final_qty'      => floatval(WarehouseStock::where('warehouse_id', $toWarehouseId)->where('product_id', $productId)->value('quantity') ?? 0),
                'notes'          => $adj->reason,
                'creator_id'     => Auth::id() ?? $companyId,
            ]);

            self::postAccountingAdjustment($adj);
        }
    }

    /**
     * Capitalize landed cost (freight, customs duty, demurrage) from ImportExport onto WarehouseStock unit_cost.
     */
    public static function capitalizeLandedCost(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $addedTotalCost,
        int $landedCostId
    ): ?WarehouseStock {
        if ($addedTotalCost <= 0) {
            return null;
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $productId, $addedTotalCost, $landedCostId) {
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$stock || floatval($stock->quantity) <= 0) {
                return null;
            }

            $qty = floatval($stock->quantity);
            $oldTotalValue = floatval($stock->total_value) > 0
                ? floatval($stock->total_value)
                : ($qty * floatval($stock->unit_cost ?? 0));

            $newTotalValue = $oldTotalValue + $addedTotalCost;
            $newUnitCost = $newTotalValue / $qty;

            $stock->unit_cost   = round($newUnitCost, 4);
            $stock->total_value = round($newTotalValue, 4);
            $stock->saveQuietly();

            InventoryAuditLog::create([
                'created_by'     => $companyId,
                'warehouse_id'   => $warehouseId,
                'product_id'     => $productId,
                'action_type'    => 'landed_cost',
                'reference_type' => 'TradeLandedCost',
                'reference_id'   => $landedCostId,
                'qty_delta'      => 0,
                'final_qty'      => $qty,
                'notes'          => 'Capitalized import landed cost: +' . number_format($addedTotalCost, 2),
                'creator_id'     => Auth::id() ?? $companyId,
            ]);

            return $stock;
        });
    }
}
