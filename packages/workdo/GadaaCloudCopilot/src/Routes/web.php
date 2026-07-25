<?php

use Workdo\GadaaCloudCopilot\Http\Controllers\CopilotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('settings/copilot', [CopilotController::class, 'index'])->name('settings.copilot.index');
    Route::post('settings/copilot/automation/{id}/toggle', [CopilotController::class, 'toggleAutomation'])->name('settings.copilot.automation.toggle');
    Route::post('settings/copilot/query', [CopilotController::class, 'queryCopilot'])->name('settings.copilot.query');
});
