<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:employee.view'])->prefix('v1')->group(function (): void {});
