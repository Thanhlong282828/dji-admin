<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Operations\HealthController;
use App\Http\Controllers\Operations\ObservabilityController;
use App\Http\Controllers\Operations\ReleaseDashboardController;
use App\Http\Controllers\Operations\ReliabilityController;
use App\Http\Controllers\Operations\RuntimeConfigurationController;
use App\Http\Controllers\Operations\SecretsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Dashboard\Presentation\Http\Controllers\DashboardController;

Route::prefix('health')->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function (): void {
    Route::get('/live', [HealthController::class, 'live'])->name('health.live');
    Route::get('/ready', [HealthController::class, 'ready'])->name('health.ready');
    Route::get('/metrics-readiness', [HealthController::class, 'metricsReadiness'])->name('health.metrics-readiness');
});

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'));

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'can:dashboard.view'])->name('dashboard');
Route::get('/admin', fn () => to_route('dashboard'))->middleware(['auth', 'verified', 'can:dashboard.view'])->name('admin.index');
Route::get('/admin/operations/reliability', ReliabilityController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.reliability');
Route::get('/admin/operations/observability', [ObservabilityController::class, 'index'])->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.observability');
Route::get('/admin/operations/observability/metrics', [ObservabilityController::class, 'metrics'])->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.observability.metrics');
Route::get('/admin/operations/releases', ReleaseDashboardController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.releases');
Route::get('/admin/operations/configuration', RuntimeConfigurationController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.configuration');
Route::get('/admin/operations/secrets', SecretsController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.secrets');

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
