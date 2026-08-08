<?php

use Illuminate\Support\Facades\Route;
use Modules\Offboarding\Http\Controllers\OffboardingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('offboardings', OffboardingController::class)->names('offboarding');
});
