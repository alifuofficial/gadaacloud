<?php

use Workdo\Chatter\Http\Controllers\ChatterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('chatter/{model}/{id}', [ChatterController::class, 'getStream'])->name('chatter.stream');
    Route::post('chatter/{model}/{id}/send', [ChatterController::class, 'postMessage'])->name('chatter.send');
    Route::post('chatter/{model}/{id}/activity', [ChatterController::class, 'scheduleActivity'])->name('chatter.activity');
    Route::post('chatter/activity/{id}/toggle', [ChatterController::class, 'toggleActivityStatus'])->name('chatter.activity.toggle');
});
