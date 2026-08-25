<?php

use Illuminate\Support\Facades\Route;
use Modules\ArticleCategory\Http\Controllers\ArticleCategoryController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('articlecategories', ArticleCategoryController::class)->names('articlecategory');
});
