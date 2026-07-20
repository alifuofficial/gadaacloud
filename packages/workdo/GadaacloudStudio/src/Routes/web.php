<?php

use Illuminate\Support\Facades\Route;
use Workdo\GadaacloudStudio\Http\Controllers\WorkflowController;
use Workdo\GadaacloudStudio\Http\Controllers\ApprovalController;

Route::group(['middleware' => ['web', 'auth']], function () {

    // ── Studio Dashboard ─────────────────────────────────────────────────────
    Route::get('gadaacloud-studio', [WorkflowController::class, 'dashboard'])
        ->name('gadaacloud-studio.dashboard');

    // ── Workflow Templates ────────────────────────────────────────────────────
    Route::get('gadaacloud-studio/templates', [WorkflowController::class, 'templates'])
        ->name('gadaacloud-studio.templates');
    Route::post('gadaacloud-studio/templates/clone', [WorkflowController::class, 'cloneFromTemplate'])
        ->name('gadaacloud-studio.templates.clone');

    // ── Workflow CRUD ─────────────────────────────────────────────────────────
    Route::get('gadaacloud-studio/workflows', [WorkflowController::class, 'index'])
        ->name('gadaacloud-studio.workflows.index');
    Route::get('gadaacloud-studio/workflows/create', [WorkflowController::class, 'create'])
        ->name('gadaacloud-studio.workflows.create');
    Route::post('gadaacloud-studio/workflows', [WorkflowController::class, 'store'])
        ->name('gadaacloud-studio.workflows.store');
    Route::get('gadaacloud-studio/workflows/{id}/edit', [WorkflowController::class, 'edit'])
        ->name('gadaacloud-studio.workflows.edit');
    Route::post('gadaacloud-studio/workflows/{id}/update', [WorkflowController::class, 'update'])
        ->name('gadaacloud-studio.workflows.update');
    Route::delete('gadaacloud-studio/workflows/{id}', [WorkflowController::class, 'destroy'])
        ->name('gadaacloud-studio.workflows.destroy');
    Route::post('gadaacloud-studio/workflows/{id}/toggle', [WorkflowController::class, 'toggleStatus'])
        ->name('gadaacloud-studio.workflows.toggle');

    // ── Approval Center ───────────────────────────────────────────────────────
    Route::get('gadaacloud-studio/approvals', [ApprovalController::class, 'index'])
        ->name('gadaacloud-studio.approvals.index');
    Route::get('gadaacloud-studio/approvals/{id}', [ApprovalController::class, 'show'])
        ->name('gadaacloud-studio.approvals.show');
    Route::post('gadaacloud-studio/approvals/{id}/approve', [ApprovalController::class, 'approve'])
        ->name('gadaacloud-studio.approvals.approve');
    Route::post('gadaacloud-studio/approvals/{id}/reject', [ApprovalController::class, 'reject'])
        ->name('gadaacloud-studio.approvals.reject');
    Route::post('gadaacloud-studio/approvals/{id}/delegate', [ApprovalController::class, 'delegate'])
        ->name('gadaacloud-studio.approvals.delegate');
});
