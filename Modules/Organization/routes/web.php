<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\Http\Controllers\CostCenterController;
use Modules\Organization\Presentation\Http\Controllers\HeadcountController;
use Modules\Organization\Presentation\Http\Controllers\JobPositionController;
use Modules\Organization\Presentation\Http\Controllers\OrganizationExportController;
use Modules\Organization\Presentation\Http\Controllers\OrganizationUnitController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('cost-centers/export', [OrganizationExportController::class, 'costCenters'])->middleware('permission:cost-center.export')->name('cost-centers.export');
    Route::resource('cost-centers', CostCenterController::class)->parameters(['cost-centers' => 'cost_center'])->middleware('permission:cost-center.view');
    Route::get('organization/tree', [OrganizationUnitController::class, 'tree'])->middleware('permission:organization-unit.view')->name('organization-units.tree');
    Route::get('organization/tree-data', [OrganizationUnitController::class, 'treeData'])->middleware('permission:organization-unit.view')->name('organization-units.tree-data');
    Route::get('organization/headcount', HeadcountController::class)->middleware('permission:organization-unit.view')->name('organization.headcount');
    Route::patch('organization-units/{organization_unit}/move', [OrganizationUnitController::class, 'move'])->middleware('permission:organization-unit.move')->name('organization-units.move');
    Route::get('organization-units/export', [OrganizationExportController::class, 'organizationUnits'])->middleware('permission:organization-unit.export')->name('organization-units.export');
    Route::get('organization-units/export-tree', [OrganizationExportController::class, 'organizationTree'])->middleware('permission:organization-unit.export')->name('organization-units.export-tree');
    Route::resource('organization-units', OrganizationUnitController::class)->middleware('permission:organization-unit.view');
    Route::patch('job-positions/{job_position}/critical', [JobPositionController::class, 'changeCritical'])->middleware('permission:job-position.change-critical')->name('job-positions.change-critical');
    Route::patch('job-positions/{job_position}/headcount', [JobPositionController::class, 'changeHeadcount'])->middleware('permission:job-position.change-headcount')->name('job-positions.change-headcount');
    Route::get('job-positions/export', [OrganizationExportController::class, 'jobPositions'])->middleware('permission:job-position.export')->name('job-positions.export');
    Route::get('organization/export-headcount', [OrganizationExportController::class, 'headcount'])->middleware('permission:organization-unit.export')->name('organization.export-headcount');
    Route::resource('job-positions', JobPositionController::class)->middleware('permission:job-position.view');
});
