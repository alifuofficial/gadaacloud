<?php

use Illuminate\Support\Facades\Route;
use Workdo\EIMS\Http\Controllers\EimsDashboardController;
use Workdo\EIMS\Http\Controllers\EimsInvoiceController;
use Workdo\EIMS\Http\Controllers\EimsOfflineQueueController;
use Workdo\EIMS\Http\Controllers\EimsSettingsController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:EIMS'])->group(function () {
    // EIMS Dashboard
    Route::get('eims', [EimsDashboardController::class, 'index'])->name('eims.dashboard');

    // EIMS Invoices
    Route::get('eims/invoices', [EimsInvoiceController::class, 'index'])->name('eims.invoices.index');
    Route::get('eims/invoices/{id}', [EimsInvoiceController::class, 'show'])->name('eims.invoices.show');
    Route::post('eims/invoices/register/{salesInvoiceId}', [EimsInvoiceController::class, 'registerManual'])->name('eims.invoices.register');
    Route::post('eims/invoices/{id}/cancel', [EimsInvoiceController::class, 'cancel'])->name('eims.invoices.cancel');
    Route::get('eims/invoices/{id}/verify', [EimsInvoiceController::class, 'verify'])->name('eims.invoices.verify');

    // 72-Hour Offline Resiliency Queue
    Route::get('eims/offline-queue', [EimsOfflineQueueController::class, 'index'])->name('eims.offline-queue.index');
    Route::post('eims/offline-queue/flush', [EimsOfflineQueueController::class, 'flush'])->name('eims.offline-queue.flush');
    Route::delete('eims/offline-queue/{id}', [EimsOfflineQueueController::class, 'destroy'])->name('eims.offline-queue.destroy');

    // Settings & INSA Audit Log
    Route::get('eims/settings', [EimsSettingsController::class, 'index'])->name('eims.settings.index');
    Route::post('eims/settings', [EimsSettingsController::class, 'update'])->name('eims.settings.update');
    Route::post('eims/settings/test', [EimsSettingsController::class, 'testConnection'])->name('eims.settings.test');
    Route::get('eims/audit-logs', [EimsSettingsController::class, 'auditLogs'])->name('eims.audit-logs.index');
});
