<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Presentation\Http\Controllers\IntegrationController;

if (app()->environment(['local', 'testing'])) {
    Route::get('admin/integrations/_fixtures/login', function () {
        abort_unless(str_ends_with(DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'integration.browser@schema.test')->firstOrFail();
        Auth::login($user);
        $company = DB::table('companies')->where('code', 'INTEGRATION-FIXTURE')->value('id');
        abort_unless(is_numeric($company), 404);

        return redirect()->route('admin.integrations.dashboard', ['company_id' => (int) $company]);
    })->name('integration.fixture.login');
}

Route::prefix('admin/integrations')->name('admin.integrations.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [IntegrationController::class, 'dashboard'])->middleware('permission:integration.view')->name('dashboard');
    Route::get('/connections', [IntegrationController::class, 'connections'])->middleware('permission:integration.view')->name('connections.index');
    Route::post('/connections', [IntegrationController::class, 'storeConnection'])->middleware('permission:integration.connection.manage')->name('connections.store');
    Route::get('/connections/{connection}', [IntegrationController::class, 'connection'])->middleware('permission:integration.view')->name('connections.show');
    Route::post('/connections/{connection}/activate', [IntegrationController::class, 'activate'])->middleware('permission:integration.connection.manage')->name('connections.activate');
    Route::get('/outbound', [IntegrationController::class, 'outbound'])->middleware('permission:integration.message.view')->name('outbound.index');
    Route::post('/outbound', [IntegrationController::class, 'queue'])->middleware('permission:integration.connection.manage')->name('outbound.store');
    Route::get('/outbound/{message}', [IntegrationController::class, 'outboundDetail'])->middleware('permission:integration.message.view')->name('outbound.show');
    Route::post('/outbound/{message}/retry', [IntegrationController::class, 'retry'])->middleware('permission:integration.retry')->name('outbound.retry');
    Route::get('/inbound', [IntegrationController::class, 'inbound'])->middleware('permission:integration.message.view')->name('inbound.index');
    Route::get('/dead-letters', [IntegrationController::class, 'deadLetters'])->middleware('permission:integration.message.view')->name('deadletters.index');
    Route::post('/dead-letters/{deadLetter}/replay', [IntegrationController::class, 'replay'])->middleware('permission:integration.deadletter.manage')->name('deadletters.replay');
    Route::get('/audit', [IntegrationController::class, 'audit'])->middleware('permission:integration.audit.view')->name('audit.index');
});
