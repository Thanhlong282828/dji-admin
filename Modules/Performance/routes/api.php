<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Http\Controllers\PerformanceController;

Route::middleware(['auth:api', 'account.enabled'])->prefix('v1')->group(function () {
    Route::apiResource('performances', PerformanceController::class)->only(['index', 'show'])->middleware('scope:performance:read')->names('performance');
    Route::apiResource('performances', PerformanceController::class)->only(['store', 'update', 'destroy'])->middleware('scope:performance:write')->names('performance');
});
