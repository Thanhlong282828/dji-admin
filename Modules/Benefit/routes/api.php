<?php

use Illuminate\Support\Facades\Route;
use Modules\Benefit\Http\Controllers\BenefitController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('benefits', BenefitController::class)->names('benefit');
});
