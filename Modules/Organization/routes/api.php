<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\Http\Controllers\OrganizationUnitController;

Route::middleware(['auth:api', 'account.enabled', 'scope:organization:read', 'permission:organization-unit.view'])->get('v1/organization/tree', [OrganizationUnitController::class, 'treeData'])->name('organization.tree');
