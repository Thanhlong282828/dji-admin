<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductCategory\Http\Controllers\ProductCategoryController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productcategories', ProductCategoryController::class)->names('productcategory');
});
