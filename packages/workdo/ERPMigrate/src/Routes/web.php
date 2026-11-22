<?php

use Workdo\ERPMigrate\Http\Controllers\MigrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:ERPMigrate'])->group(function () {
    Route::get('erp-migrate', [MigrationController::class, 'index'])->name('erp-migrate.index');
    Route::get('erp-migrate/wizard', [MigrationController::class, 'wizard'])->name('erp-migrate.wizard');
    Route::get('erp-migrate/connections', [MigrationController::class, 'connections'])->name('erp-migrate.connections');
    Route::post('erp-migrate/connections', [MigrationController::class, 'storeConnection'])->name('erp-migrate.connections.store');
    Route::post('erp-migrate/connections/test', [MigrationController::class, 'testConnection'])->name('erp-migrate.connections.test');
    Route::delete('erp-migrate/connections/{id}', [MigrationController::class, 'destroyConnection'])->name('erp-migrate.connections.destroy');

    Route::post('erp-migrate/upload', [MigrationController::class, 'uploadFile'])->name('erp-migrate.upload');
    Route::post('erp-migrate/fetch-remote', [MigrationController::class, 'fetchRemote'])->name('erp-migrate.fetch-remote');
    Route::post('erp-migrate/dry-run', [MigrationController::class, 'dryRun'])->name('erp-migrate.dry-run');
    Route::post('erp-migrate/execute', [MigrationController::class, 'execute'])->name('erp-migrate.execute');

    Route::get('erp-migrate/history', [MigrationController::class, 'history'])->name('erp-migrate.history');
    Route::get('erp-migrate/batches/{id}', [MigrationController::class, 'batchDetails'])->name('erp-migrate.batches.show');
    Route::post('erp-migrate/batches/{id}/rollback', [MigrationController::class, 'rollback'])->name('erp-migrate.batches.rollback');
});
