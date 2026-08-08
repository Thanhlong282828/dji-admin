<?php

use Illuminate\Support\Facades\Route;
use Modules\Contract\Http\Controllers\ContractController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contracts', ContractController::class)->names('contract');
});
