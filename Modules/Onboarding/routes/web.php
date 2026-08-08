<?php

use Illuminate\Support\Facades\Route;
use Modules\Onboarding\Presentation\Http\Controllers\OnboardingController;

Route::middleware(['auth', 'verified', 'permission:onboarding.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('onboarding/dashboard', [OnboardingController::class, 'dashboard'])->name('onboarding.dashboard');
    Route::get('onboarding-cases', [OnboardingController::class, 'index'])->name('onboarding-cases.index');
});
