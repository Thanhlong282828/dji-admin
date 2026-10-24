<?php

use Illuminate\Support\Facades\Route;
use Modules\Page\Http\Controllers\PageController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('pages', PageController::class)->names('page');
});
