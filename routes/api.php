<?php

use App\Http\Controllers\Api\ArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/articles', [ArticleController::class, 'index'])->name('api.articles.index');
Route::get('/v1/articles/{slug}', [ArticleController::class, 'show'])->name('api.articles.show');
