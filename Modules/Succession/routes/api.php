<?php

use Illuminate\Support\Facades\Route;
use Modules\Succession\Http\Controllers\SuccessionController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('successions', SuccessionController::class)->names('succession');
});
