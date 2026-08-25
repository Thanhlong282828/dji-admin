<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class)->names('product');
});
