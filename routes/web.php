<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Presentation\Http\Controllers\DashboardController;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'));

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'can:dashboard.view'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index'])->middleware('can:article.view')->name('articles.index');
    Route::get('/articles/create', [ArticleController::class, 'create'])->middleware('can:article.create')->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware('can:article.create')->name('articles.store');
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->middleware('can:article.update')->name('articles.edit');
    Route::match(['put', 'patch'], '/articles/{article}', [ArticleController::class, 'update'])->middleware('can:article.update')->name('articles.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
