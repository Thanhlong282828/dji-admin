<?php

use Illuminate\Support\Facades\Route;
use Modules\LaborRelation\Http\Controllers\LaborRelationController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('laborrelations', LaborRelationController::class)->names('laborrelation');
});
