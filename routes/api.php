<?php

use Illuminate\Support\Facades\Route;
use Modules\Article\Presentation\Http\Controllers\ApiArticleController;

Route::get('/v1/articles', [ApiArticleController::class, 'index'])->name('api.articles.index');
Route::get('/v1/articles/{slug}', [ApiArticleController::class, 'show'])->name('api.articles.show');
