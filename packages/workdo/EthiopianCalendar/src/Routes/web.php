<?php

use Illuminate\Support\Facades\Route;
use Workdo\EthiopianCalendar\Http\Controllers\EthiopianCalendarController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:EthiopianCalendar'])
    ->prefix('ethiopian-calendar')
    ->name('ethiopian-calendar.')
    ->group(function () {
        Route::get('/', [EthiopianCalendarController::class, 'index'])->name('index');
        Route::post('/preferences', [EthiopianCalendarController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/sync-holidays', [EthiopianCalendarController::class, 'syncHrmHolidays'])->name('sync-holidays');
    });
