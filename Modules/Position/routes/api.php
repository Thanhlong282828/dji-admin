<?php

use Illuminate\Support\Facades\Route;
use Modules\Position\Http\Controllers\PositionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('positions', PositionController::class)->names('position');
});
