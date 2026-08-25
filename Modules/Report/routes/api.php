<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('reports', ReportController::class)->names('report');
});
