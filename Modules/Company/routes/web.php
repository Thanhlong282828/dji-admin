<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Presentation\Http\Controllers\CompanyController;

Route::middleware(['auth', 'verified'])->prefix('admin/companies')->name('admin.companies.')->group(function (): void {
    Route::get('/', [CompanyController::class, 'index'])->middleware('permission:company.view')->name('index');
    Route::get('/create', [CompanyController::class, 'create'])->middleware('permission:company.create')->name('create');
    Route::post('/', [CompanyController::class, 'store'])->middleware('permission:company.create')->name('store');
    Route::get('/{company}', [CompanyController::class, 'show'])->middleware('permission:company.view')->whereNumber('company')->name('show');
    Route::get('/{company}/edit', [CompanyController::class, 'edit'])->middleware('permission:company.update')->whereNumber('company')->name('edit');
    Route::match(['put', 'patch'], '/{company}', [CompanyController::class, 'update'])->middleware('permission:company.update')->whereNumber('company')->name('update');
    Route::delete('/{company}', [CompanyController::class, 'destroy'])->middleware('permission:company.delete')->whereNumber('company')->name('destroy');
    Route::post('/{company}/restore', [CompanyController::class, 'restore'])->middleware('permission:company.restore')->whereNumber('company')->name('restore');
    Route::patch('/{company}/status', [CompanyController::class, 'changeStatus'])->middleware('permission:company.change-status')->whereNumber('company')->name('change-status');
    Route::delete('/{company}/seller-accounts/{seller}', [CompanyController::class, 'destroySellerAccount'])->middleware('permission:commerce.seller.governance.manage')->whereNumber(['company', 'seller'])->name('seller-accounts.destroy');
});
