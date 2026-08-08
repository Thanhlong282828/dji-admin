<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Presentation\Http\Controllers\NotificationCenterController;

if (app()->environment(['local', 'testing'])) {
    Route::get('admin/notifications/_fixtures/login', function () {
        abort_unless(str_ends_with(DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'notification.browser@schema.test')->firstOrFail();
        Auth::login($user);
        $companyId = DB::table('companies')->where('code', 'NOTIFY-FIXTURE')->value('id');
        abort_unless(is_numeric($companyId), 404);

        return redirect()->route('admin.notifications.index', ['company_id' => (int) $companyId]);
    })->name('notification.fixture.login');
}

Route::prefix('admin/notifications')->name('admin.notifications.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [NotificationCenterController::class, 'index'])->middleware('permission:notification.view')->name('index');
    Route::get('/unread-count', [NotificationCenterController::class, 'unread'])->middleware('permission:notification.view')->name('unread');
    Route::get('/templates', [NotificationCenterController::class, 'templates'])->middleware('permission:notification.template.manage')->name('templates.index');
    Route::post('/templates', [NotificationCenterController::class, 'storeTemplate'])->middleware('permission:notification.template.manage')->name('templates.store');
    Route::post('/templates/{template}/publish', [NotificationCenterController::class, 'publishTemplate'])->middleware('permission:notification.template.manage')->name('templates.publish');
    Route::get('/deliveries', [NotificationCenterController::class, 'deliveries'])->middleware('permission:notification.delivery.view')->name('deliveries.index');
    Route::post('/deliveries/{delivery}/retry', [NotificationCenterController::class, 'retry'])->middleware('permission:notification.retry')->name('deliveries.retry');
    Route::post('/read-all', [NotificationCenterController::class, 'markAll'])->middleware('permission:notification.view')->name('read-all');
    Route::get('/{message}', [NotificationCenterController::class, 'show'])->middleware('permission:notification.view')->name('show');
    Route::post('/{message}/read', [NotificationCenterController::class, 'markRead'])->middleware('permission:notification.view')->name('read');
    Route::post('/preferences', [NotificationCenterController::class, 'preference'])->middleware('permission:notification.preference.manage')->name('preferences.update');
});
