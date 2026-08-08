<?php

use Illuminate\Support\Facades\Route;
use Modules\Offboarding\Http\Controllers\OffboardingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('offboardings', OffboardingController::class)->names('offboarding');
});
