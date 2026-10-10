<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Presentation\Http\Controllers\CrmController;

Route::get('/admin/crm', [CrmController::class, 'pipeline'])->middleware(['auth', 'verified', 'permission:crm.view'])->name('admin.crm.index');
Route::get('/admin/crm/leads', [CrmController::class, 'leads'])->middleware(['auth', 'verified', 'permission:crm.view'])->name('admin.crm.leads');
Route::get('/admin/crm/opportunities', [CrmController::class, 'opportunities'])->middleware(['auth', 'verified', 'permission:crm.view'])->name('admin.crm.opportunities');
Route::post('/admin/crm/commands/{action}', [CrmController::class, 'mutate'])->where('action', 'lead-create|lead-status|lead-convert|opportunity-create|opportunity-stage')->middleware(['auth', 'verified', 'permission:crm.manage'])->name('admin.crm.mutate');
