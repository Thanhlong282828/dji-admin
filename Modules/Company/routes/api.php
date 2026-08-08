<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Presentation\Http\Controllers\CompanyController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->name('company.index');
    Route::post('companies', [CompanyController::class, 'store'])->name('company.store');
});
