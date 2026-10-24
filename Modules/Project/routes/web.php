<?php

use Illuminate\Support\Facades\Route;
use Modules\Project\Presentation\Http\Controllers\ProjectController;

Route::middleware(['auth', 'verified'])->prefix('admin/projects')->name('admin.projects.')->group(function (): void {
    Route::get('/overview', [ProjectController::class, 'overview'])->middleware('permission:project.view')->name('overview');
    Route::get('/', [ProjectController::class, 'index'])->middleware('permission:project.view')->name('index');
    Route::post('/', [ProjectController::class, 'store'])->middleware('permission:project.create')->name('store');
    Route::get('/{project}', [ProjectController::class, 'show'])->whereNumber('project')->middleware('permission:project.view')->name('show');
    Route::put('/{project}', [ProjectController::class, 'update'])->whereNumber('project')->middleware('permission:project.update')->name('update');
    Route::post('/{project}/status', [ProjectController::class, 'status'])->middleware('permission:project.complete')->name('status');
    Route::post('/{project}/members', [ProjectController::class, 'member'])->middleware('permission:project.manage-members')->name('members.store');
    Route::post('/{project}/milestones', [ProjectController::class, 'milestone'])->middleware('permission:project.manage-task')->name('milestones.store');
    Route::post('/{project}/milestones/{milestone}/status', [ProjectController::class, 'milestoneStatus'])->middleware('permission:project.manage-task')->name('milestones.status');
    Route::post('/{project}/tasks', [ProjectController::class, 'task'])->middleware('permission:project.manage-task')->name('tasks.store');
    Route::post('/{project}/tasks/{task}/status', [ProjectController::class, 'taskStatus'])->middleware('permission:project.manage-task')->name('tasks.status');
    Route::post('/{project}/dependencies', [ProjectController::class, 'dependency'])->middleware('permission:project.manage-task')->name('dependencies.store');
    Route::post('/{project}/timesheets', [ProjectController::class, 'timesheet'])->middleware('permission:project.timesheet.create')->name('timesheets.store');
    Route::post('/{project}/timesheets/{timesheet}/submit', [ProjectController::class, 'submitTimesheet'])->middleware('permission:project.timesheet.create')->name('timesheets.submit');
    Route::post('/{project}/timesheets/{timesheet}/decision', [ProjectController::class, 'decideTimesheet'])->middleware('permission:project.timesheet.approve')->name('timesheets.decision');
    Route::post('/{project}/expenses', [ProjectController::class, 'expense'])->middleware('permission:finance.operations.manage')->name('expenses.store');
    Route::post('/{project}/purchase-requests', [ProjectController::class, 'purchaseRequest'])->middleware('permission:procurement.manage')->name('purchase-requests.store');
});
