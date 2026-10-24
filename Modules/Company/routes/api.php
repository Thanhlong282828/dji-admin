<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Presentation\Http\Controllers\CompanyController;

Route::middleware(['auth:api', 'account.enabled'])->prefix('v1')->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->middleware('scope:company:read')->name('company.index');
    Route::post('companies', [CompanyController::class, 'store'])->middleware('scope:company:write')->name('company.store');
});
