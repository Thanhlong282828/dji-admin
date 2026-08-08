<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\Http\Controllers\OrganizationUnitController;

Route::middleware(['auth:sanctum', 'permission:organization-unit.view'])->get('v1/organization/tree', [OrganizationUnitController::class, 'treeData'])->name('organization.tree');
