<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Presentation\Http\Controllers\CareerController;
use Modules\Performance\Presentation\Http\Controllers\ExecutivePlanningController;
use Modules\Performance\Presentation\Http\Controllers\PerformanceController;
use Modules\Performance\Presentation\Http\Controllers\PlanningExecutionController;
use Modules\Performance\Presentation\Http\Controllers\WorkforceAnalyticsController;
use Modules\Performance\Presentation\Http\Controllers\WorkforceForecastController;

Route::middleware(['auth', 'verified'])->prefix('admin/talent')->name('admin.talent.')->group(function () {
    Route::get('performance', [PerformanceController::class, 'index'])->middleware('permission:talent.performance.view')->name('performance.index');
    Route::get('performance/employees/{employee}', [PerformanceController::class, 'show'])->middleware('permission:talent.performance.view')->name('performance.employee');
    Route::post('performance/{action}/{subject?}', [PerformanceController::class, 'action'])->where('action', 'cycle\.create|goal\.assign|goal\.progress|review\.start|review\.self|review\.manager|review\.approve|cycle\.close')->name('performance.action');
});
Route::middleware(['web', 'auth', 'verified'])->prefix('admin/planning')->name('admin.planning.')->group(function (): void {
    Route::get('/', [ExecutivePlanningController::class, 'index'])->middleware('permission:analytics.view')->name('executive');
    Route::get('/export', [ExecutivePlanningController::class, 'export'])->middleware('permission:analytics.export')->name('export');
    Route::get('/execution', [PlanningExecutionController::class, 'index'])->middleware('permission:analytics.view')->name('execution');
    Route::get('/execution/export', [PlanningExecutionController::class, 'export'])->middleware('permission:analytics.export')->name('execution.export');
});
Route::middleware(['web', 'auth', 'verified'])->prefix('admin/analytics')->name('admin.analytics.')->group(function (): void {
    Route::get('/workforce', [WorkforceAnalyticsController::class, 'index'])->middleware('permission:analytics.view')->name('workforce');
    Route::get('/workforce/export', [WorkforceAnalyticsController::class, 'export'])->middleware('permission:analytics.export')->name('export');
    Route::post('/workforce/snapshots', [WorkforceAnalyticsController::class, 'generate'])->middleware('permission:analytics.manage')->name('generate');
    Route::post('/workforce/{action}', [WorkforceAnalyticsController::class, 'mutate'])->where('action', 'refresh|rebuild')->middleware('permission:analytics.manage')->name('mutate');
    Route::get('/forecasting', [WorkforceForecastController::class, 'index'])->middleware('permission:analytics.view')->name('forecasting');
    Route::post('/forecasting/export', [WorkforceForecastController::class, 'export'])->middleware('permission:analytics.export')->name('forecasting.export');
});

Route::middleware(['web', 'auth', 'verified'])->prefix('admin/career')->name('admin.career.')->group(function (): void {
    Route::get('/', [CareerController::class, 'index'])->middleware('permission:career.view')->name('index');
    Route::get('/employees/{employee}', [CareerController::class, 'employee'])->middleware('permission:career.view')->name('employee');
    Route::post('/{action}/{subject?}', [CareerController::class, 'action'])->where('action', 'path\.create|plan\.assign|plan\.progress|succession\.create|succession\.nominate|succession\.assess|succession\.approve')->name('action');
});
