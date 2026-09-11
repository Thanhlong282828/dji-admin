<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Modules\Iam\Presentation\Http\Controllers\AccountSecurityController;
use Modules\Iam\Presentation\Http\Controllers\PhoneVerificationController;
use Modules\Iam\Presentation\Http\Controllers\SocialIdentityController;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('phone-login', [PhoneVerificationController::class, 'loginForm'])->name('phone.login');
    Route::post('phone-login/request', [PhoneVerificationController::class, 'requestLogin'])->middleware('throttle:5,1')->name('phone.login.request');
    Route::post('phone-login/verify', [PhoneVerificationController::class, 'verifyLogin'])->middleware('throttle:10,1')->name('phone.login.verify');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('auth/{provider}/redirect', [SocialIdentityController::class, 'redirect'])
    ->whereIn('provider', ['google', 'facebook'])->middleware('throttle:10,1')->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialIdentityController::class, 'callback'])
    ->whereIn('provider', ['google', 'facebook'])->middleware('throttle:20,1')->name('social.callback');

Route::middleware('auth')->group(function () {
    Route::get('account', AccountSecurityController::class)->middleware('verified')->name('account.show');
    Route::delete('account/identities/{provider}', [SocialIdentityController::class, 'unlink'])
        ->whereIn('provider', ['google', 'facebook'])->middleware(['verified', 'password.confirm'])->name('social.unlink');
    Route::post('account/phone/request', [PhoneVerificationController::class, 'requestForAccount'])
        ->middleware(['verified', 'throttle:5,1'])->name('account.phone.request');
    Route::post('account/phone/verify', [PhoneVerificationController::class, 'verifyAccount'])
        ->middleware(['verified', 'throttle:10,1'])->name('account.phone.verify');
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
