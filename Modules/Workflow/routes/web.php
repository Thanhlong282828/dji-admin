<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Workflow\Presentation\Http\Controllers\WorkflowController;

if (app()->environment(['local', 'testing'])) {
    Route::get('_fixtures/workflow/login', function () {
        abort_unless(str_ends_with((string) DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'workflow.approver1@schema.test')->firstOrFail();
        $companyId = DB::table('companies')->where('code', 'WF-BROWSER-FIXTURE')->value('id');
        abort_unless(is_numeric($companyId), 404);
        Auth::login($user);

        return redirect()->route('admin.workflows.dashboard', ['company_id' => (int) $companyId]);
    })->name('fixtures.workflow.login');
}

Route::middleware(['auth', 'verified'])->prefix('admin/workflows')->name('admin.workflows.')->group(function (): void {
    Route::get('/', [WorkflowController::class, 'dashboard'])->middleware('permission:workflow.view')->name('dashboard');
    Route::get('/definitions', [WorkflowController::class, 'definitions'])->middleware('permission:workflow.view')->name('definitions');
    Route::get('/definitions/create', [WorkflowController::class, 'create'])->middleware('permission:workflow.definition.manage')->name('definitions.create');
    Route::post('/definitions', [WorkflowController::class, 'store'])->middleware('permission:workflow.definition.manage')->name('definitions.store');
    Route::post('/definitions/{definition}/publish', [WorkflowController::class, 'publish'])->middleware('permission:workflow.definition.manage')->name('definitions.publish');
    Route::post('/instances', [WorkflowController::class, 'start'])->middleware('permission:workflow.start')->name('instances.store');
    Route::get('/instances/{instance}', [WorkflowController::class, 'show'])->middleware('permission:workflow.view')->name('instances.show');
    Route::get('/instances/{instance}/audit', [WorkflowController::class, 'audit'])->middleware('permission:workflow.audit.view')->name('instances.audit');
    Route::post('/instances/{instance}/cancel', [WorkflowController::class, 'cancel'])->middleware('permission:workflow.cancel')->name('instances.cancel');
    Route::get('/tasks', [WorkflowController::class, 'tasks'])->middleware('permission:workflow.view')->name('tasks');
    Route::post('/tasks/{task}/claim', [WorkflowController::class, 'claim'])->middleware('permission:workflow.task.claim')->name('tasks.claim');
    Route::post('/tasks/{task}/decision', [WorkflowController::class, 'decide'])->middleware('permission:workflow.task.decide')->name('tasks.decide');
});
