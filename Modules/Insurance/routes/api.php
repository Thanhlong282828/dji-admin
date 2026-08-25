<?php

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\InsuranceController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('insurances', InsuranceController::class)->names('insurance');
});
