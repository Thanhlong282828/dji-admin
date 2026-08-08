<?php

use Illuminate\Support\Facades\Route;
use Modules\Menu\Http\Controllers\MenuController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('menus', MenuController::class)->names('menu');
});
