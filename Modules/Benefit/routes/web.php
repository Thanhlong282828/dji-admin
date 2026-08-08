<?php

use Illuminate\Support\Facades\Route;
use Modules\Benefit\Http\Controllers\BenefitController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('benefits', BenefitController::class)->names('benefit');
});
