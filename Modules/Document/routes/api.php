<?php

use Illuminate\Support\Facades\Route;
use Modules\Document\Http\Controllers\DocumentController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('documents', DocumentController::class)->names('document');
});
