<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductCategory\Http\Controllers\ProductCategoryController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('productcategories', ProductCategoryController::class)->names('productcategory');
});
