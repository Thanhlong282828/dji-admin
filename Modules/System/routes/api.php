<?php

use Illuminate\Support\Facades\Route;
use Modules\System\Http\Controllers\SystemController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('systems', SystemController::class)->names('system');
});
