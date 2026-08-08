<?php

use Illuminate\Support\Facades\Route;
use Modules\Training\Http\Controllers\TrainingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('trainings', TrainingController::class)->names('training');
});
