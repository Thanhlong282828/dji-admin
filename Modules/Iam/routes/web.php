<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Iam\Presentation\Http\Controllers\IamController;

if (app()->environment(['local', 'testing'])) {
    Route::get('admin/iam/_fixtures/login', function () {
        abort_unless(str_ends_with(DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'iam.browser@schema.test')->firstOrFail();
        Auth::login($user);
        $company = DB::table('companies')->where('code', 'IAM-FIXTURE')->value('id');
        abort_unless(is_numeric($company), 404);

        return redirect()->route('admin.iam.dashboard', ['company_id' => (int) $company]);
    })->name('iam.fixture.login');
}

Route::prefix('admin/iam')->name('admin.iam.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [IamController::class, 'dashboard'])->middleware('permission:iam.view')->name('dashboard');
    Route::get('/requests', [IamController::class, 'requests'])->middleware('permission:iam.view')->name('requests.index');
    Route::post('/requests', [IamController::class, 'store'])->middleware('permission:iam.request.create')->name('requests.store');
    Route::get('/requests/{request}', [IamController::class, 'show'])->middleware('permission:iam.view')->name('requests.show');
    Route::post('/requests/{request}/submit', [IamController::class, 'transition'])->defaults('action', 'submit')->middleware('permission:iam.request.create')->name('requests.submit');
    Route::post('/requests/{request}/review', [IamController::class, 'transition'])->defaults('action', 'review')->middleware('permission:iam.request.review')->name('requests.review');
    Route::post('/requests/{request}/approve', [IamController::class, 'transition'])->defaults('action', 'approve')->middleware('permission:iam.request.approve')->name('requests.approve');
    Route::post('/requests/{request}/reject', [IamController::class, 'transition'])->defaults('action', 'reject')->middleware('permission:iam.request.approve')->name('requests.reject');
    Route::post('/requests/{request}/provision', [IamController::class, 'transition'])->defaults('action', 'provision')->middleware('permission:iam.assignment.manage')->name('requests.provision');
    Route::post('/assignments/{assignment}/revoke', [IamController::class, 'revoke'])->middleware('permission:iam.assignment.manage')->name('assignments.revoke');
    Route::get('/users/{user}/access-360', [IamController::class, 'access360'])->middleware('permission:iam.view')->name('access-360');
    Route::get('/roles', [IamController::class, 'roles'])->middleware('permission:iam.view')->name('roles');
    Route::get('/privileged', [IamController::class, 'privileged'])->middleware('permission:iam.privileged.manage')->name('privileged');
    Route::get('/sod', [IamController::class, 'sod'])->middleware('permission:iam.sod.manage')->name('sod');
    Route::get('/reviews', [IamController::class, 'reviews'])->middleware('permission:iam.review.manage')->name('reviews');
    Route::post('/reviews', [IamController::class, 'reviewStore'])->middleware('permission:iam.review.manage')->name('reviews.store');
    Route::get('/reviews/{review}', [IamController::class, 'reviewShow'])->middleware('permission:iam.review.manage')->name('reviews.show');
    Route::post('/reviews/{review}/start', [IamController::class, 'reviewStart'])->middleware('permission:iam.review.manage')->name('reviews.start');
    Route::post('/reviews/{review}/items/{item}/decide', [IamController::class, 'reviewDecide'])->middleware('permission:iam.review.manage')->name('reviews.items.decide');
    Route::post('/reviews/{review}/complete', [IamController::class, 'reviewComplete'])->middleware('permission:iam.review.manage')->name('reviews.complete');
});
