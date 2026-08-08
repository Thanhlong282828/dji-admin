<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\LeaveAttachmentController;
use Modules\Leave\Http\Controllers\LeaveController;
use Modules\Leave\Http\Controllers\LeaveManagementController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('leave', [LeaveController::class, 'index'])->middleware('permission:leave.view')->name('leave.index');
    Route::post('leave', [LeaveController::class, 'store'])->middleware('permission:leave.request')->name('leave.store');
    Route::patch('leave/{leave_request}/{target}', [LeaveController::class, 'transition'])->whereIn('target', ['submitted', 'approved', 'rejected', 'cancelled', 'withdrawn'])->name('leave.transition');
    Route::get('leave-management', [LeaveManagementController::class, 'index'])->middleware('permission:leave.manage-policy')->name('leave-management.index');
    Route::post('leave-management/{action}', [LeaveManagementController::class, 'execute'])->whereIn('action', ['policy.create', 'assignment.create', 'balance.adjust', 'balance.opening', 'accrual.annual', 'accrual.monthly', 'carry_forward', 'expiry'])->name('leave-management.execute');
    Route::put('leave-management/{action}/{subject}', [LeaveManagementController::class, 'execute'])->whereIn('action', ['policy.update', 'policy.deactivate'])->whereNumber('subject')->name('leave-management.update');
    Route::post('leave/{leaveRequest}/attachments/{action}', [LeaveAttachmentController::class, 'upload'])->where('action', 'upload')->whereNumber('leaveRequest')->name('leave-attachments.upload');
    Route::put('leave/{leaveRequest}/attachments/{attachment}/{action}', [LeaveAttachmentController::class, 'mutate'])->whereIn('action', ['replace', 'remove'])->whereNumber(['leaveRequest', 'attachment'])->name('leave-attachments.update');
    Route::get('leave/{leaveRequest}/attachments/{attachment}/download', [LeaveAttachmentController::class, 'download'])->middleware('permission:leave.view')->whereNumber(['leaveRequest', 'attachment'])->name('leave-attachments.download');
});
