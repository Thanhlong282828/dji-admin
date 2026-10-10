<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Audit\Presentation\Http\Controllers\AuditController;

if (app()->environment(['local', 'testing'])) {
    Route::get('admin/audit/_fixtures/login', function () {
        abort_unless(str_ends_with(DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'audit.browser@schema.test')->firstOrFail();
        Auth::login($user);
        $company = DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id');
        abort_unless(is_numeric($company), 404);
        session(['company_id' => (int) $company]);

        return redirect()->route('admin.audit.dashboard', ['company_id' => (int) $company]);
    })->name('audit.fixture.login');
}

Route::prefix('admin/audit')->name('admin.audit.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [AuditController::class, 'dashboard'])->middleware('permission:audit.view')->name('dashboard');
    Route::get('/timeline', [AuditController::class, 'timeline'])->middleware('permission:audit.view')->name('timeline');
    Route::get('/events/{event}', [AuditController::class, 'event'])->middleware('permission:audit.view')->name('events.show');
    Route::get('/actors', [AuditController::class, 'actors'])->middleware('permission:audit.search')->name('actors');
    Route::get('/resources', [AuditController::class, 'resources'])->middleware('permission:audit.search')->name('resources');
    Route::get('/exports', [AuditController::class, 'exports'])->middleware('permission:audit.export')->name('exports');
    Route::post('/exports', [AuditController::class, 'createExport'])->middleware('permission:audit.export')->name('exports.store');
    Route::get('/exports/{export}/download', [AuditController::class, 'download'])->middleware('permission:audit.export')->name('exports.download');
    Route::get('/retention', [AuditController::class, 'retention'])->middleware('permission:audit.retention.manage')->name('retention');
    Route::post('/retention', [AuditController::class, 'applyRetention'])->middleware('permission:audit.retention.manage')->name('retention.store');
    Route::post('/retention/archive', [AuditController::class, 'archive'])->middleware('permission:audit.retention.manage')->name('retention.archive');
});
