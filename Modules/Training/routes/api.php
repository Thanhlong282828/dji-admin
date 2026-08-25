<?php

use Illuminate\Support\Facades\Route;
use Modules\Training\Http\Controllers\TrainingController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('trainings', TrainingController::class)->names('training');
});
