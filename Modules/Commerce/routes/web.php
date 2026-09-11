<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Presentation\Http\Controllers\SalesReceivableController;

Route::get('/admin/sales', [SalesReceivableController::class, 'index'])->middleware(['auth', 'verified', 'permission:sales.view'])->name('admin.sales.index');
Route::post('/admin/sales', [SalesReceivableController::class, 'mutate'])->middleware(['auth', 'verified', 'permission:sales.manage|customer-invoice.manage|collection.create'])->name('admin.sales.mutate');
