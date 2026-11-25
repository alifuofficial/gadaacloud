<?php

use Illuminate\Support\Facades\Route;
use Workdo\Performance\Http\Controllers\CompetencyCategoryController;
use Workdo\Performance\Http\Controllers\CompetencyController;
use Workdo\Performance\Http\Controllers\DashboardController;
use Workdo\Performance\Http\Controllers\EmployeeCompetencyController;
use Workdo\Performance\Http\Controllers\EmployeeFeedbackController;
use Workdo\Performance\Http\Controllers\EmployeeGoalController;
use Workdo\Performance\Http\Controllers\EmployeeReviewController;
use Workdo\Performance\Http\Controllers\FeedbackTypeController;
use Workdo\Performance\Http\Controllers\GoalTypeController;
use Workdo\Performance\Http\Controllers\PerformanceIndicatorCategoryController;
use Workdo\Performance\Http\Controllers\PerformanceIndicatorController;
use Workdo\Performance\Http\Controllers\ReviewCycleController;
use Workdo\Performance\Http\Controllers\ReviewTemplateController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Performance'])->group(function () {
    // Performance Executive Dashboard
    Route::get('performance/dashboard', [DashboardController::class, 'index'])->name('performance.dashboard');

    // Performance Indicators
    Route::prefix('performance/indicators')->name('performance.indicators.')->group(function () {
        Route::get('/', [PerformanceIndicatorController::class, 'index'])->name('index');
        Route::post('/', [PerformanceIndicatorController::class, 'store'])->name('store');
        Route::put('/{indicator}', [PerformanceIndicatorController::class, 'update'])->name('update');
        Route::delete('/{indicator}', [PerformanceIndicatorController::class, 'destroy'])->name('destroy');
    });

    // Employee Goals (+ ERP KPI auto-sync)
    Route::prefix('performance/employee-goals')->name('performance.employee-goals.')->group(function () {
        Route::get('/', [EmployeeGoalController::class, 'index'])->name('index');
        Route::post('/', [EmployeeGoalController::class, 'store'])->name('store');
        Route::put('/{employeeGoal}', [EmployeeGoalController::class, 'update'])->name('update');
        Route::post('/{employeeGoal}/sync-kpi', [EmployeeGoalController::class, 'syncKpi'])->name('sync-kpi');
        Route::delete('/{employeeGoal}', [EmployeeGoalController::class, 'destroy'])->name('destroy');
    });

    // Review Cycles (+ Ethiopian fiscal resolution & bulk review assignment)
    Route::prefix('performance/review-cycles')->name('performance.review-cycles.')->group(function () {
        Route::get('/', [ReviewCycleController::class, 'index'])->name('index');
        Route::post('/', [ReviewCycleController::class, 'store'])->name('store');
        Route::post('/ethiopian-preset', [ReviewCycleController::class, 'ethiopianPreset'])->name('ethiopian-preset');
        Route::post('/{reviewCycle}/bulk-assign', [ReviewCycleController::class, 'bulkAssign'])->name('bulk-assign');
        Route::post('/{reviewCycle}/toggle-lock', [ReviewCycleController::class, 'toggleLock'])->name('toggle-lock');
        Route::get('/{reviewCycle}', [ReviewCycleController::class, 'show'])->name('show');
        Route::put('/{reviewCycle}', [ReviewCycleController::class, 'update'])->name('update');
        Route::delete('/{reviewCycle}', [ReviewCycleController::class, 'destroy'])->name('destroy');
    });

    // Employee Reviews (+ Self-Assessment, Calibration, Acknowledgment, HRM Lifecycle Actions)
    Route::prefix('performance/employee-reviews')->name('performance.employee-reviews.')->group(function () {
        Route::get('/', [EmployeeReviewController::class, 'index'])->name('index');
        Route::post('/', [EmployeeReviewController::class, 'store'])->name('store');
        Route::get('/{employeeReview}/conduct', [EmployeeReviewController::class, 'conduct'])->name('conduct');
        Route::post('/{employeeReview}/conduct', [EmployeeReviewController::class, 'conductStore'])->name('conduct.store');
        Route::post('/{employeeReview}/self-assess', [EmployeeReviewController::class, 'selfAssessStore'])->name('self-assess');
        Route::post('/{employeeReview}/calibrate', [EmployeeReviewController::class, 'calibrate'])->name('calibrate');
        Route::post('/{employeeReview}/acknowledge', [EmployeeReviewController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{employeeReview}/hr-action', [EmployeeReviewController::class, 'hrAction'])->name('hr-action');
        Route::get('/{employeeReview}', [EmployeeReviewController::class, 'show'])->name('show');
        Route::put('/{employeeReview}', [EmployeeReviewController::class, 'update'])->name('update');
        Route::delete('/{employeeReview}', [EmployeeReviewController::class, 'destroy'])->name('destroy');
    });

    // Competencies & Employee Competency Assessments
    Route::prefix('performance/competencies')->name('performance.competencies.')->group(function () {
        Route::get('/', [CompetencyController::class, 'index'])->name('index');
        Route::post('/', [CompetencyController::class, 'store'])->name('store');
        Route::put('/{competency}', [CompetencyController::class, 'update'])->name('update');
        Route::delete('/{competency}', [CompetencyController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('performance/employee-competencies')->name('performance.employee-competencies.')->group(function () {
        Route::get('/', [EmployeeCompetencyController::class, 'index'])->name('index');
        Route::post('/', [EmployeeCompetencyController::class, 'store'])->name('store');
        Route::put('/{employeeCompetency}', [EmployeeCompetencyController::class, 'update'])->name('update');
        Route::delete('/{employeeCompetency}', [EmployeeCompetencyController::class, 'destroy'])->name('destroy');
    });

    // 360-Degree & Continuous Feedback
    Route::prefix('performance/feedback')->name('performance.feedback.')->group(function () {
        Route::get('/', [EmployeeFeedbackController::class, 'index'])->name('index');
        Route::post('/', [EmployeeFeedbackController::class, 'store'])->name('store');
        Route::put('/{employeeFeedback}', [EmployeeFeedbackController::class, 'update'])->name('update');
        Route::delete('/{employeeFeedback}', [EmployeeFeedbackController::class, 'destroy'])->name('destroy');
    });

    // Review Templates
    Route::prefix('performance/review-templates')->name('performance.review-templates.')->group(function () {
        Route::get('/', [ReviewTemplateController::class, 'index'])->name('index');
        Route::post('/', [ReviewTemplateController::class, 'store'])->name('store');
        Route::put('/{reviewTemplate}', [ReviewTemplateController::class, 'update'])->name('update');
        Route::delete('/{reviewTemplate}', [ReviewTemplateController::class, 'destroy'])->name('destroy');
    });

    // System Setup: Performance Indicator Categories, Goal Types, Competency Categories, Feedback Types
    Route::prefix('performance/indicator-categories')->name('performance.indicator-categories.')->group(function () {
        Route::get('/', [PerformanceIndicatorCategoryController::class, 'index'])->name('index');
        Route::post('/', [PerformanceIndicatorCategoryController::class, 'store'])->name('store');
        Route::put('/{indicatorCategory}', [PerformanceIndicatorCategoryController::class, 'update'])->name('update');
        Route::delete('/{indicatorCategory}', [PerformanceIndicatorCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('performance/goal-types')->name('performance.goal-types.')->group(function () {
        Route::get('/', [GoalTypeController::class, 'index'])->name('index');
        Route::post('/', [GoalTypeController::class, 'store'])->name('store');
        Route::put('/{goalType}', [GoalTypeController::class, 'update'])->name('update');
        Route::delete('/{goalType}', [GoalTypeController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('performance/competency-categories')->name('performance.competency-categories.')->group(function () {
        Route::get('/', [CompetencyCategoryController::class, 'index'])->name('index');
        Route::post('/', [CompetencyCategoryController::class, 'store'])->name('store');
        Route::put('/{competencyCategory}', [CompetencyCategoryController::class, 'update'])->name('update');
        Route::delete('/{competencyCategory}', [CompetencyCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('performance/feedback-types')->name('performance.feedback-types.')->group(function () {
        Route::get('/', [FeedbackTypeController::class, 'index'])->name('index');
        Route::post('/', [FeedbackTypeController::class, 'store'])->name('store');
        Route::put('/{feedbackType}', [FeedbackTypeController::class, 'update'])->name('update');
        Route::delete('/{feedbackType}', [FeedbackTypeController::class, 'destroy'])->name('destroy');
    });
});
