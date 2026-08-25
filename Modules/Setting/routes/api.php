<?php

use Illuminate\Support\Facades\Route;
use Modules\Setting\Http\Controllers\SettingController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('settings', SettingController::class)->names('setting');
});
