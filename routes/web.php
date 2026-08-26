<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CompensationReferenceController;
use App\Http\Controllers\Operations\ApiContractsController;
use App\Http\Controllers\Operations\ApiDocumentationController;
use App\Http\Controllers\Operations\ArchitectureController;
use App\Http\Controllers\Operations\ContinuityController;
use App\Http\Controllers\Operations\DataLifecycleController;
use App\Http\Controllers\Operations\DataProtectionController;
use App\Http\Controllers\Operations\DataQualityController;
use App\Http\Controllers\Operations\GoLiveReadinessController;
use App\Http\Controllers\Operations\HealthController;
use App\Http\Controllers\Operations\ObservabilityController;
use App\Http\Controllers\Operations\PerformanceController;
use App\Http\Controllers\Operations\ReleaseDashboardController;
use App\Http\Controllers\Operations\ReliabilityController;
use App\Http\Controllers\Operations\RuntimeConfigurationController;
use App\Http\Controllers\Operations\SecretsController;
use App\Http\Controllers\Operations\SecurityAssuranceController;
use App\Http\Controllers\Operations\TestQualityController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\SellerCenterController;
use App\Http\Controllers\SellerGovernanceController;
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

Route::get('/', [PublicContentController::class, 'home'])->name('public.home');
Route::get('/stories', [PublicContentController::class, 'articles'])->name('public.articles.index');
Route::get('/stories/{slug}', [PublicContentController::class, 'article'])->name('public.articles.show');
Route::get('/stores', [PublicContentController::class, 'stores'])->name('public.stores.index');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'can:dashboard.view'])->name('dashboard');
Route::get('/admin', fn () => to_route('dashboard'))->middleware(['auth', 'verified', 'can:dashboard.view'])->name('admin.index');
Route::get('/admin/operations/reliability', ReliabilityController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.reliability');
Route::get('/admin/operations/observability', [ObservabilityController::class, 'index'])->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.observability');
Route::get('/admin/operations/observability/metrics', [ObservabilityController::class, 'metrics'])->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.observability.metrics');
Route::get('/admin/operations/performance', PerformanceController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.performance');
Route::get('/admin/operations/architecture', ArchitectureController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.architecture');
Route::get('/admin/operations/api-contracts', ApiContractsController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.api-contracts');
Route::get('/admin/operations/api-documentation', ApiDocumentationController::class)->middleware(['auth', 'verified', 'permission:api.documentation.view'])->name('admin.operations.api-documentation');
Route::get('/admin/operations/test-quality', TestQualityController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.test-quality');
Route::get('/admin/operations/releases', ReleaseDashboardController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.releases');
Route::get('/admin/operations/configuration', RuntimeConfigurationController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.configuration');
Route::get('/admin/operations/secrets', SecretsController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.secrets');
Route::get('/admin/operations/data-protection', DataProtectionController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.data-protection');
Route::get('/admin/operations/data-lifecycle', DataLifecycleController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.data-lifecycle');
Route::get('/admin/operations/data-quality', DataQualityController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.data-quality');
Route::get('/admin/operations/continuity', ContinuityController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.continuity');
Route::get('/admin/operations/security-assurance', SecurityAssuranceController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.security-assurance');
Route::get('/admin/operations/go-live', GoLiveReadinessController::class)->middleware(['auth', 'verified', 'permission:operations.view'])->name('admin.operations.go-live');
Route::get('/admin/compensation-reference', CompensationReferenceController::class)->middleware(['auth', 'verified', 'permission:payroll.assignment.view'])->name('admin.compensation-reference');

Route::middleware('auth')->group(function () {
    Route::get('/admin/branches', [BranchController::class, 'index'])->middleware(['verified', 'permission:branch.view'])->name('admin.branches.index');
    Route::get('/admin/branches/create', [BranchController::class, 'create'])->middleware(['verified', 'permission:branch.create'])->name('admin.branches.create');
    Route::post('/admin/branches', [BranchController::class, 'store'])->middleware(['verified', 'permission:branch.create'])->name('admin.branches.store');
    Route::get('/admin/branches/{branch}', [BranchController::class, 'show'])->middleware(['verified', 'permission:branch.view'])->whereNumber('branch')->name('admin.branches.show');
    Route::get('/admin/branches/{branch}/edit', [BranchController::class, 'edit'])->middleware(['verified', 'permission:branch.update'])->name('admin.branches.edit');
    Route::put('/admin/branches/{branch}', [BranchController::class, 'update'])->middleware(['verified', 'permission:branch.update'])->name('admin.branches.update');
    Route::get('/admin/seller', [SellerCenterController::class, 'dashboard'])->middleware(['verified', 'permission:commerce.store.view'])->name('admin.seller.dashboard');
    Route::get('/admin/seller/store', [SellerCenterController::class, 'storeProfile'])->middleware(['verified', 'permission:commerce.store.view'])->name('admin.seller.store');
    Route::put('/admin/seller/store', [SellerCenterController::class, 'saveStore'])->middleware(['verified', 'permission:commerce.store.update'])->name('admin.seller.store.update');
    Route::get('/admin/commerce/sellers', [SellerGovernanceController::class, 'index'])->middleware(['verified', 'permission:commerce.seller.governance.view'])->name('admin.commerce.sellers.index');
    Route::patch('/admin/commerce/sellers/{seller}/status', [SellerGovernanceController::class, 'transition'])->middleware(['verified', 'permission:commerce.seller.governance.manage'])->name('admin.commerce.sellers.transition');
    Route::get('/admin/products', [ProductCatalogController::class, 'index'])->middleware(['verified', 'permission:product.view'])->name('admin.products.index');
    Route::get('/admin/products/create', [ProductCatalogController::class, 'create'])->middleware(['verified', 'permission:product.create'])->name('admin.products.create');
    Route::post('/admin/products', [ProductCatalogController::class, 'store'])->middleware(['verified', 'permission:product.create'])->name('admin.products.store');
    Route::get('/admin/products/{product}/edit', [ProductCatalogController::class, 'edit'])->middleware(['verified', 'permission:product.update'])->name('admin.products.edit');
    Route::put('/admin/products/{product}', [ProductCatalogController::class, 'update'])->middleware(['verified', 'permission:product.update'])->name('admin.products.update');
    Route::get('/admin/product-categories', [ProductCategoryController::class, 'index'])->middleware(['verified', 'permission:product-category.view'])->name('admin.product-categories.index');
    Route::post('/admin/product-categories', [ProductCategoryController::class, 'store'])->middleware(['verified', 'permission:product-category.create'])->name('admin.product-categories.store');
    Route::get('/articles', [ArticleController::class, 'index'])->middleware(['verified', 'can:article.view'])->name('articles.index');
    Route::get('/articles/create', [ArticleController::class, 'create'])->middleware(['verified', 'can:article.create'])->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware(['verified', 'can:article.create'])->name('articles.store');
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->middleware(['verified', 'can:article.update'])->name('articles.edit');
    Route::match(['put', 'patch'], '/articles/{article}', [ArticleController::class, 'update'])->middleware(['verified', 'can:article.update'])->name('articles.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/products', [PublicContentController::class, 'products'])->name('public.products.index');
Route::get('/products/{slug}', [PublicContentController::class, 'product'])->name('public.products.show');

require __DIR__.'/auth.php';
