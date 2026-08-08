<?php

use Illuminate\Support\Facades\Route;
use Modules\ArticleCategory\Http\Controllers\ArticleCategoryController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('articlecategories', ArticleCategoryController::class)->names('articlecategory');
});
