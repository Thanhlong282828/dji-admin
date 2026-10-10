<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Presentation\Http\Controllers\CustomerCreditNoteController;
use Modules\Commerce\Presentation\Http\Controllers\CustomerRefundController;
use Modules\Commerce\Presentation\Http\Controllers\SalesReceivableController;

Route::get('/admin/sales', [SalesReceivableController::class, 'index'])->middleware(['auth', 'verified', 'permission:sales.view'])->name('admin.sales.index');
Route::post('/admin/sales', [SalesReceivableController::class, 'mutate'])->middleware(['auth', 'verified', 'permission:sales.manage|customer-invoice.manage|collection.create'])->name('admin.sales.mutate');
Route::post('/admin/sales/customer-credit-notes', CustomerCreditNoteController::class)->middleware(['auth', 'verified', 'permission:customer-credit-note.create|customer-credit-note.submit|customer-credit-note.approve|customer-credit-note.post'])->name('admin.sales.customer-credit-notes.mutate');
Route::post('/admin/sales/customer-refunds', CustomerRefundController::class)->middleware(['auth', 'verified', 'permission:customer-refund.create|customer-refund.submit|customer-refund.approve|customer-refund.post'])->name('admin.sales.customer-refunds.mutate');
