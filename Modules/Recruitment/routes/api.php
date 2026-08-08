<?php

use Illuminate\Support\Facades\Route;
use Modules\Recruitment\Http\Controllers\RecruitmentController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('recruitments', RecruitmentController::class)->names('recruitment');
});
