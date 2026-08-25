<?php

use Illuminate\Support\Facades\Route;
use Modules\Recruitment\Http\Controllers\RecruitmentController;

Route::middleware(['auth:api', 'account.enabled'])->prefix('v1')->group(function () {
    Route::apiResource('recruitments', RecruitmentController::class)->only(['index', 'show'])->middleware('scope:recruitment:read')->names('recruitment');
    Route::apiResource('recruitments', RecruitmentController::class)->only(['store', 'update', 'destroy'])->middleware('scope:recruitment:write')->names('recruitment');
});
