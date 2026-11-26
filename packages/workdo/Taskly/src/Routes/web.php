<?php

use Illuminate\Support\Facades\Route;
use Workdo\Taskly\Http\Controllers\DashboardController;
use Workdo\Taskly\Http\Controllers\ProjectController;
use Workdo\Taskly\Http\Controllers\ProjectTaskController;
use Workdo\Taskly\Http\Controllers\TaskStageController;
use Workdo\Taskly\Http\Controllers\BugStageController;
use Workdo\Taskly\Http\Controllers\ProjectBugController;
use Workdo\Taskly\Http\Controllers\ProjectReportController;
use Workdo\Taskly\Http\Controllers\TaskTimesheetController;
use Workdo\Taskly\Http\Controllers\ProjectWorkspaceController;
use Workdo\Taskly\Http\Controllers\ProjectMagicLinkController;
use Workdo\Taskly\Http\Controllers\ProjectSettingsController;

// Public Client Tracking Portal (No Login Required)
Route::middleware(['web'])->group(function () {
    Route::get('/p/track/{token}', [ProjectMagicLinkController::class, 'track'])->name('project.public.track');
    Route::post('/p/track/{token}/approve/{milestone}', [ProjectMagicLinkController::class, 'clientApproveMilestone'])->name('project.public.approve-milestone');
});

// API Routes for other packages
Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Taskly'])->prefix('api/taskly')->name('api.taskly.')->group(function () {
    Route::get('/projects', [ProjectController::class, 'apiIndex'])->name('projects.index');
    Route::get('/projects/{project}/tasks', [ProjectTaskController::class, 'apiTasks'])->name('projects.tasks');
});

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Taskly'])->group(function () {
    Route::get('/dashboard/project', [DashboardController::class, 'index'])->name('project.dashboard.index');
    Route::get('/project', [ProjectController::class, 'index'])->name('project.index');

    // Task routes - must come before generic project routes
    Route::get('/project/tasks', [ProjectTaskController::class, 'index'])->name('project.tasks.index');
    Route::post('/project/tasks/store', [ProjectTaskController::class, 'store'])->name('project.tasks.store');
    Route::get('/project/tasks/{task}', [ProjectTaskController::class, 'show'])->name('project.tasks.show');
    Route::get('/project/tasks/kanban/{project}', [ProjectTaskController::class, 'kanban'])->name('project.tasks.kanban');
    Route::get('/project/tasks/calendar/{project}', [ProjectTaskController::class, 'calendar'])->name('project.tasks.calendar');
    Route::patch('/project/tasks/{task}/move', [ProjectTaskController::class, 'move'])->name('project.tasks.move');
    Route::put('/project/tasks/{task}', [ProjectTaskController::class, 'update'])->name('project.tasks.update');
    Route::delete('/project/tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('project.tasks.destroy');
    Route::get('/project/{project}/tasks/api', [ProjectTaskController::class, 'getTasks'])->name('project.tasks.api');

    // Project report routes
    Route::get('/project-report', [ProjectReportController::class, 'index'])->name('project.report.index');
    Route::get('/project-report/{id}', [ProjectReportController::class, 'show'])->name('project.report.show');

    // Task comments and subtasks
    Route::get('/project/tasks/{task}/comments', [ProjectTaskController::class, 'getComments'])->name('project.tasks.comments.index');
    Route::post('/project/tasks/{task}/comments', [ProjectTaskController::class, 'storeComment'])->name('project.tasks.comments.store');
    Route::delete('/project/tasks/comments/{comment}', [ProjectTaskController::class, 'destroyComment'])->name('project.tasks.comments.destroy');
    Route::get('/project/tasks/{task}/subtasks', [ProjectTaskController::class, 'getSubtasks'])->name('project.tasks.subtasks.index');
    Route::post('/project/tasks/{task}/subtasks', [ProjectTaskController::class, 'storeSubtask'])->name('project.tasks.subtasks.store');
    Route::patch('/project/tasks/subtasks/{subtask}/toggle', [ProjectTaskController::class, 'toggleSubtask'])->name('project.tasks.subtasks.toggle');

    // Bug routes - must come before generic project routes
    Route::get('/project/bugs', [ProjectBugController::class, 'index'])->name('project.bugs.index');
    Route::get('/project/bugs/kanban/{project}', [ProjectBugController::class, 'kanban'])->name('project.bugs.kanban');
    Route::post('/project/bugs', [ProjectBugController::class, 'store'])->name('project.bugs.store');
    Route::get('/project/bugs/{bug}', [ProjectBugController::class, 'show'])->name('project.bugs.show');
    Route::put('/project/bugs/{bug}', [ProjectBugController::class, 'update'])->name('project.bugs.update');
    Route::delete('/project/bugs/{bug}', [ProjectBugController::class, 'destroy'])->name('project.bugs.destroy');
    Route::patch('/project/bugs/{bug}/move', [ProjectBugController::class, 'move'])->name('project.bugs.move');
    Route::get('/project/{project}/bugs/api', [ProjectBugController::class, 'getBugs'])->name('project.bugs.api');

    // Bug comments
    Route::get('/project/bugs/{bug}/comments', [ProjectBugController::class, 'getComments'])->name('project.bugs.comments.index');
    Route::post('/project/bugs/{bug}/comments', [ProjectBugController::class, 'storeComment'])->name('project.bugs.comments.store');
    Route::delete('/project/bugs/comments/{comment}', [ProjectBugController::class, 'destroyComment'])->name('project.bugs.comments.destroy');

    // Project files
    Route::post('/project/{project}/files', [ProjectController::class, 'storeFiles'])->name('project.files.store');
    Route::delete('/project/files/{file}', [ProjectController::class, 'deleteFile'])->name('project.files.delete');

    // Project timesheets (Phase 1)
    Route::post('/project/{project}/timesheets', [TaskTimesheetController::class, 'store'])->name('project.timesheets.store');
    Route::post('/project/timesheets/{timesheet}/approve', [TaskTimesheetController::class, 'approve'])->name('project.timesheets.approve');
    Route::delete('/project/timesheets/{timesheet}', [TaskTimesheetController::class, 'destroy'])->name('project.timesheets.destroy');

    // Construction Workspace: BoQ & Site Diary (Phase 3)
    Route::post('/project/{project}/boqs', [ProjectWorkspaceController::class, 'storeBoq'])->name('project.boqs.store');
    Route::put('/project/boqs/{boq}', [ProjectWorkspaceController::class, 'updateBoq'])->name('project.boqs.update');
    Route::delete('/project/boqs/{boq}', [ProjectWorkspaceController::class, 'destroyBoq'])->name('project.boqs.destroy');
    Route::post('/project/{project}/site-diaries', [ProjectWorkspaceController::class, 'storeSiteDiary'])->name('project.site-diaries.store');
    Route::delete('/project/site-diaries/{diary}', [ProjectWorkspaceController::class, 'destroySiteDiary'])->name('project.site-diaries.destroy');

    // Import/Export Workspace: Logistics & Demurrage (Phase 3)
    Route::post('/project/{project}/logistics', [ProjectWorkspaceController::class, 'updateLogistics'])->name('project.logistics.update');

    // Stage-Gate Milestone Approvals (Phase 2)
    Route::post('/project/milestones/{milestone}/approve', [ProjectWorkspaceController::class, 'approveMilestone'])->name('project.milestones.approve');
    Route::post('/project/milestones/{milestone}/reject', [ProjectWorkspaceController::class, 'rejectMilestone'])->name('project.milestones.reject');

    // Project routes - must come after task and bug routes
    Route::get('/project/{project}', [ProjectController::class, 'show'])->name('project.show');
    Route::get('/project/{project}/edit', [ProjectController::class, 'edit'])->name('project.edit');
    Route::post('/project/{project}/invite', [ProjectController::class, 'invite'])->name('project.invite');
    Route::post('/project/{project}/reassign-member', [ProjectController::class, 'reassignMemberWork'])->name('project.reassign-member');
    Route::delete('/project/{project}/delete-member', [ProjectController::class, 'deleteMember'])->name('project.delete-member');
    Route::post('/project/{project}/invite-client', [ProjectController::class, 'inviteClient'])->name('project.invite-client');
    Route::delete('/project/{project}/delete-client', [ProjectController::class, 'deleteClient'])->name('project.delete-client');
    Route::post('/project/{project}/milestones', [ProjectController::class, 'storeMilestone'])->name('project.milestones.store');
    Route::put('/project/{project}/milestones', [ProjectController::class, 'updateMilestone'])->name('project.milestones.update');
    Route::delete('/project/{project}/milestones', [ProjectController::class, 'deleteMilestone'])->name('project.milestones.delete');
    Route::post('/project', [ProjectController::class, 'store'])->name('project.store');
    Route::put('/project/{project}', [ProjectController::class, 'update'])->name('project.update');
    Route::delete('/project/{project}', [ProjectController::class, 'destroy'])->name('project.destroy');
    Route::post('/project/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('project.duplicate');

    // Setup routes
    Route::get('/project-setup/settings', [ProjectSettingsController::class, 'index'])->name('project.settings.index');
    Route::post('/project-setup/settings', [ProjectSettingsController::class, 'store'])->name('project.settings.store');

    Route::get('/project-setup/task-stages', [TaskStageController::class, 'index'])->name('project.task-stages.index');
    Route::post('/project-setup/task-stages/store', [TaskStageController::class, 'store'])->name('project.task-stages.store');
    Route::put('/project-setup/task-stages/reorder', [TaskStageController::class, 'reorder'])->name('project.task-stages.reorder');
    Route::put('/project-setup/task-stages/{taskStage}', [TaskStageController::class, 'update'])->name('project.task-stages.update');
    Route::delete('/project-setup/task-stages/{taskStage}', [TaskStageController::class, 'destroy'])->name('project.task-stages.destroy');

    Route::get('/project-setup/bug-stages', [BugStageController::class, 'index'])->name('project.bug-stages.index');
    Route::post('/project-setup/bug-stages/store', [BugStageController::class, 'store'])->name('project.bug-stages.store');
    Route::put('/project-setup/bug-stages/reorder', [BugStageController::class, 'reorder'])->name('project.bug-stages.reorder');
    Route::put('/project-setup/bug-stages/{bugStage}', [BugStageController::class, 'update'])->name('project.bug-stages.update');
    Route::delete('/project-setup/bug-stages/{bugStage}', [BugStageController::class, 'destroy'])->name('project.bug-stages.destroy');
});
