<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;
use Modules\Attendance\Http\Controllers\AttendanceWorkflowController;
use Modules\Attendance\Http\Controllers\OvertimeController;
use Modules\Attendance\Http\Controllers\ScheduleRuleController;
use Modules\Attendance\Http\Controllers\WorkforceExportController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance.view')->name('attendance.index');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:attendance.check-in')->name('attendance.check-in');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:attendance.check-out')->name('attendance.check-out');
    Route::get('workforce/{section?}', [AttendanceController::class, 'management'])->whereIn('section', ['calendars', 'holidays', 'shifts', 'assignments'])->name('workforce.management');
    Route::post('workforce/{resource}', [AttendanceController::class, 'save'])->whereIn('resource', ['calendar', 'holiday', 'shift', 'assignment'])->name('workforce.save');
    Route::put('workforce/{resource}/{id}', [AttendanceController::class, 'save'])->whereIn('resource', ['calendar', 'holiday', 'shift', 'assignment'])->whereNumber('id')->name('workforce.update');
    Route::delete('workforce/{resource}/{id}', [AttendanceController::class, 'remove'])->whereIn('resource', ['calendar', 'holiday', 'shift', 'assignment'])->whereNumber('id')->name('workforce.destroy');
    Route::get('attendance-periods/{period}/readiness', [AttendanceWorkflowController::class, 'readiness'])->middleware('permission:attendance.lock')->name('attendance-periods.readiness');
    Route::post('attendance-periods/{period}/lock', [AttendanceWorkflowController::class, 'lock'])->middleware('permission:attendance.lock')->defaults('action', 'lock')->name('attendance-periods.lock');
    Route::get('attendance/{record}', [AttendanceWorkflowController::class, 'show'])->middleware('permission:attendance.view')->whereNumber('record')->name('attendance.show');
    Route::post('attendance/{record}/adjustments', [AttendanceWorkflowController::class, 'adjustment'])->middleware('permission:attendance.adjust')->defaults('action', 'create')->name('attendance-adjustments.store');
    Route::post('attendance-adjustments/{record}/{action}', [AttendanceWorkflowController::class, 'adjustment'])->whereIn('action', ['submit', 'approve', 'reject', 'cancel'])->name('attendance-adjustments.transition');
    Route::post('attendance/{record}/{action}', [AttendanceWorkflowController::class, 'attendance'])->whereIn('action', ['submit', 'approve', 'reject'])->name('attendance.transition');
    Route::get('overtime', [OvertimeController::class, 'index'])->middleware('permission:overtime.view')->name('overtime.index');
    Route::post('overtime/{action}', [OvertimeController::class, 'execute'])->whereIn('action', ['policy.create', 'assignment.create', 'request.create'])->name('overtime.execute');
    Route::put('overtime/{action}/{subject}', [OvertimeController::class, 'execute'])->whereIn('action', ['policy.update', 'policy.deactivate', 'request.submit', 'request.approve', 'request.reject', 'request.cancel', 'request.reconcile'])->whereNumber('subject')->name('overtime.update');
    Route::get('workforce/calendars/{calendar}/days', [ScheduleRuleController::class, 'calendar'])->middleware('permission:work-calendar.view')->whereNumber('calendar')->name('workforce.calendar-days');
    Route::put('workforce/calendar-rules/{action}/{parent}', [ScheduleRuleController::class, 'execute'])->where('action', 'calendar.days.update')->whereNumber('parent')->name('workforce.calendar-days.update');
    Route::get('workforce/shifts/{shift}/break-rules', [ScheduleRuleController::class, 'shift'])->middleware('permission:shift.view')->whereNumber('shift')->name('workforce.shift-breaks');
    Route::post('workforce/shift-rules/{action}/{parent}', [ScheduleRuleController::class, 'execute'])->whereIn('action', ['break.create'])->whereNumber('parent')->name('workforce.shift-breaks.create');
    Route::put('workforce/shift-rules/{action}/{parent}/{rule}', [ScheduleRuleController::class, 'execute'])->whereIn('action', ['break.update', 'break.archive'])->whereNumber(['parent', 'rule'])->name('workforce.shift-breaks.update');
    Route::get('workforce-export/{type}.csv', WorkforceExportController::class)->whereIn('type', ['workforce', 'attendance', 'leave', 'leave-balances', 'overtime', 'payroll-input'])->name('workforce.export');
    Route::get('attendance-export.csv', WorkforceExportController::class)->defaults('type', 'attendance')->name('attendance.export');
    Route::get('leave-export.csv', WorkforceExportController::class)->defaults('type', 'leave')->name('leave.export');
    Route::get('leave-balances-export.csv', WorkforceExportController::class)->defaults('type', 'leave-balances')->name('leave-balances.export');
    Route::get('overtime-export.csv', WorkforceExportController::class)->defaults('type', 'overtime')->name('overtime.export');
    Route::get('payroll-input-export.csv', WorkforceExportController::class)->defaults('type', 'payroll-input')->name('payroll-input.export');
});
