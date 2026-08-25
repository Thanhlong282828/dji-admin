<?php

use Illuminate\Support\Facades\Route;
use Modules\Contract\Http\Controllers\ContractController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('contracts', ContractController::class)->names('contract');
});
