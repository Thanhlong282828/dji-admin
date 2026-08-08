<?php

use Illuminate\Support\Facades\Route;
use Modules\Document\Http\Controllers\DocumentController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('documents', DocumentController::class)->names('document');
});
