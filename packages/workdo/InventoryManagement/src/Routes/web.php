<?php

use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'auth', 'verified', 'PlanModuleCheck:InventoryManagement']], function () {
    // Inventory Management Dashboard & Valuation
    Route::get('settings/inventory', [InventoryController::class, 'index'])
        ->name('settings.inventory.index');

    // Physical Stock Adjustments & Approvals
    Route::post('settings/inventory/adjustment', [InventoryController::class, 'storeAdjustment'])
        ->name('settings.inventory.adjustment.store');
    Route::post('settings/inventory/adjustment/{adjustment}/approve', [InventoryController::class, 'approveAdjustment'])
        ->name('settings.inventory.adjustment.approve');

    // Safety Stock Reorder Rules
    Route::post('settings/inventory/reorder', [InventoryController::class, 'storeReorderRule'])
        ->name('settings.inventory.reorder.store');

    // Serial / Chassis Numbers Tracking
    Route::post('settings/inventory/serials', [InventoryController::class, 'storeSerials'])
        ->name('settings.inventory.serials.store');
    Route::get('settings/inventory/serials', [InventoryController::class, 'getProductSerials'])
        ->name('settings.inventory.serials.get');

    // Batch / Lot & Expiry Tracking
    Route::post('settings/inventory/batches', [InventoryController::class, 'storeBatch'])
        ->name('settings.inventory.batches.store');
    Route::post('settings/inventory/batches/{batch}/status', [InventoryController::class, 'updateBatchStatus'])
        ->name('settings.inventory.batches.status');
});
