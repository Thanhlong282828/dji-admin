<?php

use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Http\Controllers\OnboardingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('onboardings', OnboardingController::class)->names('onboarding');
});
