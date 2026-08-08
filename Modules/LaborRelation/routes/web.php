<?php

use Illuminate\Support\Facades\Route;
use Modules\LaborRelation\Http\Controllers\LaborRelationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('laborrelations', LaborRelationController::class)->names('laborrelation');
});
