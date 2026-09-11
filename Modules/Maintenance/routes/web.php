<?php

use Illuminate\Support\Facades\Route;
use Modules\Maintenance\Presentation\Http\Controllers\MaintenanceController;

Route::middleware(['auth', 'verified'])->prefix('admin/maintenance')->name('admin.maintenance.')->group(function (): void {
    Route::get('/overview', [MaintenanceController::class, 'overview'])->middleware('permission:maintenance.view')->name('overview');
    Route::get('/requests', [MaintenanceController::class, 'requests'])->middleware('permission:maintenance.view')->name('requests');
    Route::post('/requests', [MaintenanceController::class, 'storeRequest'])->middleware('permission:maintenance.request.create')->name('requests.store');
    Route::post('/requests/{request}/submit', [MaintenanceController::class, 'submit'])->middleware('permission:maintenance.request.submit')->name('requests.submit');
    Route::post('/requests/{request}/decision', [MaintenanceController::class, 'decision'])->middleware('permission:maintenance.request.approve')->name('requests.decision');
    Route::post('/requests/{request}/convert', [MaintenanceController::class, 'convert'])->middleware('permission:maintenance.work-order.create')->name('requests.convert');
    Route::get('/work-orders', [MaintenanceController::class, 'orders'])->middleware('permission:maintenance.view')->name('work-orders');
    Route::post('/work-orders', [MaintenanceController::class, 'storeOrder'])->middleware('permission:maintenance.work-order.create')->name('work-orders.store');
    Route::get('/work-orders/{order}', [MaintenanceController::class, 'show'])->middleware('permission:maintenance.view')->name('work-orders.show');
    Route::post('/work-orders/{order}/assign', [MaintenanceController::class, 'assign'])->middleware('permission:maintenance.work-order.assign')->name('work-orders.assign');
    Route::post('/work-orders/{order}/status', [MaintenanceController::class, 'status'])->middleware('permission:maintenance.work-order.execute')->name('work-orders.status');
    Route::post('/work-orders/{order}/parts', [MaintenanceController::class, 'part'])->middleware('permission:maintenance.part.issue')->name('parts.store');
    Route::post('/work-orders/{order}/parts/{part}/issue', [MaintenanceController::class, 'issue'])->middleware('permission:maintenance.part.issue')->name('parts.issue');
    Route::post('/work-orders/{order}/expenses', [MaintenanceController::class, 'expense'])->middleware('permission:finance.operations.manage')->name('expenses.store');
    Route::post('/work-orders/{order}/purchase-requests', [MaintenanceController::class, 'purchaseRequest'])->middleware('permission:procurement.manage')->name('purchase-requests.store');
    Route::get('/preventive-plans', [MaintenanceController::class, 'plans'])->middleware('permission:maintenance.view')->name('plans');
    Route::post('/preventive-plans', [MaintenanceController::class, 'storePlan'])->middleware('permission:maintenance.plan.manage')->name('plans.store');
    Route::post('/preventive-plans/{plan}/generate', [MaintenanceController::class, 'generate'])->middleware('permission:maintenance.work-order.create')->name('plans.generate');
});
