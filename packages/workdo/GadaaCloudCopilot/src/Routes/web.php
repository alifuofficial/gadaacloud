<?php

use Workdo\GadaaCloudCopilot\Http\Controllers\CopilotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('settings/copilot', [CopilotController::class, 'index'])->name('settings.copilot.index');
    Route::get('settings/copilot/setup', [CopilotController::class, 'setup'])->name('settings.copilot.setup');
    Route::post('settings/copilot/setup', [CopilotController::class, 'saveSetup'])->name('settings.copilot.setup.save');
    Route::match(['get', 'post'], 'settings/copilot/automation/create', [CopilotController::class, 'createAutomation'])->name('settings.copilot.automation.create');
    Route::match(['get', 'post'], 'settings/copilot/automation/{id}/toggle', [CopilotController::class, 'toggleAutomation'])->name('settings.copilot.automation.toggle');
    Route::post('settings/copilot/query', [CopilotController::class, 'queryCopilot'])->name('settings.copilot.query');
    Route::get('settings/copilot/memories', [CopilotController::class, 'getMemories'])->name('settings.copilot.memories');
    Route::post('settings/copilot/memories/clear', [CopilotController::class, 'clearMemories'])->name('settings.copilot.memories.clear');
});

// Inbound PDF OCR Webhook Route
Route::post('api/webhooks/inbound-invoice', [CopilotController::class, 'processInboundInvoiceWebhook'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
