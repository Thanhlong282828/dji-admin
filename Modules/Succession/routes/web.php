<?php

use Illuminate\Support\Facades\Route;
use Modules\Succession\Http\Controllers\SuccessionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('successions', SuccessionController::class)->names('succession');
});
