<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:employee.view'])->prefix('v1')->group(function (): void {});
