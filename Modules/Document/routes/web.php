<?php

use Illuminate\Support\Facades\Route;
use Modules\Document\Presentation\Http\Controllers\EnterpriseDocumentController;

Route::middleware(['auth', 'verified'])->prefix('admin/documents')->name('admin.documents.')->group(function (): void {
    Route::get('/', [EnterpriseDocumentController::class, 'index'])->middleware('permission:document.view')->name('index');
    Route::post('/', [EnterpriseDocumentController::class, 'store'])->middleware('permission:document.create')->name('store');
    Route::get('/{document}', [EnterpriseDocumentController::class, 'show'])->whereNumber('document')->middleware('permission:document.view')->name('show');
    Route::post('/{document}/versions', [EnterpriseDocumentController::class, 'upload'])->whereNumber('document')->middleware('permission:document.upload')->name('versions.store');
    Route::post('/{document}/archive', [EnterpriseDocumentController::class, 'archive'])->whereNumber('document')->middleware('permission:document.archive')->name('archive');
    Route::get('/versions/{version}/download', [EnterpriseDocumentController::class, 'download'])->whereNumber('version')->middleware('permission:document.download')->name('download');
});
