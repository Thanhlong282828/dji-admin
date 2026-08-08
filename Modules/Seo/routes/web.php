<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\SeoController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('seos', SeoController::class)->names('seo');
});
