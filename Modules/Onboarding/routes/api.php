<?php

use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Http\Controllers\OnboardingController;

Route::middleware(['auth:api', 'account.enabled'])->prefix('v1')->group(function () {
    Route::apiResource('onboardings', OnboardingController::class)->only(['index', 'show'])->middleware('scope:onboarding:read')->names('onboarding');
    Route::apiResource('onboardings', OnboardingController::class)->only(['store', 'update', 'destroy'])->middleware('scope:onboarding:write')->names('onboarding');
});
