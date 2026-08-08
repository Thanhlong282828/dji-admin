<?php

use Illuminate\Support\Facades\Route;
use Modules\Position\Http\Controllers\PositionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('positions', PositionController::class)->names('position');
});
