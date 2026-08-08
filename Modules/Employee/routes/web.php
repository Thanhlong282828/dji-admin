<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Employee\Presentation\Http\Controllers\DataConsentController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeAssignmentController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeAvatarController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeCredentialController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeDocumentController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeExportController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeLifecycleController;
use Modules\Employee\Presentation\Http\Controllers\EmployeeUserLinkController;
use Modules\Employee\Presentation\Http\Controllers\EmploymentContractController;
use Modules\Employee\Presentation\Http\Controllers\HRGovernanceController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('hr/governance', [HRGovernanceController::class, 'dashboard'])->middleware('permission:hr.governance.view')->name('hr.governance.dashboard');
    Route::get('hr/governance/issues', [HRGovernanceController::class, 'issues'])->middleware('permission:hr.governance.view')->name('hr.governance.issues');
    Route::get('hr/governance/audit', [HRGovernanceController::class, 'audit'])->middleware('permission:hr.audit.view')->name('hr.governance.audit');
    Route::get('hr/governance/consents', [HRGovernanceController::class, 'consents'])->middleware('permission:hr.governance.view')->name('hr.governance.consents');
    Route::get('hr/governance/documents', [HRGovernanceController::class, 'documents'])->middleware('permission:hr.governance.view')->name('hr.governance.documents');
    Route::post('hr/governance/runs', [HRGovernanceController::class, 'run'])->middleware('permission:hr.quality.run')->name('hr.governance.runs.store');
    Route::post('hr/governance/issues/{issue}/transition', [HRGovernanceController::class, 'transition'])->middleware('permission:hr.governance.manage')->name('hr.governance.issues.transition');
    Route::post('hr/governance/issues/{issue}/approve', [HRGovernanceController::class, 'approve'])->middleware('permission:hr.governance.manage')->name('hr.governance.issues.approve');
    Route::post('hr/governance/documents/{document}/archive', [HRGovernanceController::class, 'archiveDocument'])->middleware('permission:hr.document.manage')->name('hr.governance.documents.archive');
    Route::get('employee-lifecycle', [EmployeeLifecycleController::class, 'index'])->middleware('permission:hr.lifecycle.view')->name('employee-lifecycle.index');
    Route::get('employee-lifecycle/{employee}', [EmployeeLifecycleController::class, 'show'])->middleware('permission:hr.lifecycle.view')->name('employee-lifecycle.show');
    Route::post('employee-lifecycle/{employee}', [EmployeeLifecycleController::class, 'createLifecycle'])->middleware('permission:hr.lifecycle.create')->name('employee-lifecycle.create');
    Route::post('employee-lifecycle/{employee}/start-onboarding', [EmployeeLifecycleController::class, 'startOnboarding'])->middleware('permission:hr.lifecycle.onboarding')->name('employee-lifecycle.start-onboarding');
    Route::post('employee-lifecycle/{employee}/complete-onboarding', [EmployeeLifecycleController::class, 'completeOnboarding'])->middleware('permission:hr.lifecycle.onboarding')->name('employee-lifecycle.complete-onboarding');
    Route::post('employee-lifecycle/{employee}/transfer', [EmployeeLifecycleController::class, 'transfer'])->middleware('permission:hr.lifecycle.transfer')->name('employee-lifecycle.transfer');
    Route::post('employee-lifecycle/{employee}/promote', [EmployeeLifecycleController::class, 'promote'])->middleware('permission:hr.lifecycle.promote')->name('employee-lifecycle.promote');
    Route::post('employee-lifecycle/{employee}/terminate', [EmployeeLifecycleController::class, 'terminate'])->middleware('permission:hr.lifecycle.terminate')->name('employee-lifecycle.terminate');
    Route::get('employees-export', EmployeeExportController::class)->middleware('permission:employee.export')->name('employees.export');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->middleware('permission:employee-document.upload')->name('employees.documents.store');
    Route::get('employee-documents/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('permission:employee-document.download')->name('employee-documents.download');
    Route::delete('employee-documents/{document}', [EmployeeDocumentController::class, 'archive'])->middleware('permission:employee-document.archive')->name('employee-documents.archive');
    Route::post('employees/{employee}/credentials', [EmployeeCredentialController::class, 'store'])->middleware('permission:employee-credential.create')->name('employees.credentials.store');
    Route::put('employee-credentials/{credential}', [EmployeeCredentialController::class, 'update'])->middleware('permission:employee-credential.update')->name('employee-credentials.update');
    Route::delete('employee-credentials/{credential}', [EmployeeCredentialController::class, 'archive'])->middleware('permission:employee-credential.archive')->name('employee-credentials.archive');
    Route::post('employee-credentials/{credential}/restore', [EmployeeCredentialController::class, 'restore'])->middleware('permission:employee-credential.restore')->name('employee-credentials.restore');
    Route::post('employees/{employee}/contracts', [EmploymentContractController::class, 'store'])->middleware('permission:employment-contract.create')->name('employees.contracts.store');
    Route::post('employment-contracts/{contract}/submit', [EmploymentContractController::class, 'submit'])->middleware('permission:employment-contract.submit')->name('employment-contracts.submit');
    Route::post('employment-contracts/{contract}/approve', [EmploymentContractController::class, 'approve'])->middleware('permission:employment-contract.approve')->name('employment-contracts.approve');
    Route::post('employment-contracts/{contract}/return', [EmploymentContractController::class, 'return'])->middleware('permission:employment-contract.approve')->name('employment-contracts.return');
    Route::post('employment-contracts/{contract}/activate', [EmploymentContractController::class, 'activate'])->middleware('permission:employment-contract.activate')->name('employment-contracts.activate');
    Route::post('employment-contracts/{contract}/terminate', [EmploymentContractController::class, 'terminate'])->middleware('permission:employment-contract.terminate')->name('employment-contracts.terminate');
    Route::post('employment-contracts/{contract}/cancel', [EmploymentContractController::class, 'cancel'])->middleware('permission:employment-contract.cancel')->name('employment-contracts.cancel');
    Route::post('employees/{employee}/consents', [DataConsentController::class, 'grant'])->middleware('permission:data-consent.grant')->name('employees.consents.grant');
    Route::post('data-consents/{consent}/withdraw', [DataConsentController::class, 'withdraw'])->middleware('permission:data-consent.withdraw')->name('data-consents.withdraw');
    Route::post('employees/{employee}/avatar', [EmployeeAvatarController::class, 'store'])->middleware('permission:employee.manage-avatar')->name('employees.avatar.store');
    Route::delete('employees/{employee}/avatar', [EmployeeAvatarController::class, 'destroy'])->middleware('permission:employee.manage-avatar')->name('employees.avatar.destroy');
    Route::post('employees/{employee}/user-link', [EmployeeUserLinkController::class, 'store'])->middleware('permission:employee-user.link')->name('employees.user-link.store');
    Route::delete('employees/{employee}/user-link', [EmployeeUserLinkController::class, 'destroy'])->middleware('permission:employee-user.unlink')->name('employees.user-link.destroy');
    Route::post('employees/{employee}/user-invite', [EmployeeUserLinkController::class, 'invite'])->middleware('permission:employee-user.invite')->name('employees.user.invite');
    Route::get('employees/{employee}/assignments', [EmployeeAssignmentController::class, 'index'])->middleware('permission:employee-assignment.view')->name('employees.assignments.index');
    Route::post('employees/{employee}/assignments', [EmployeeAssignmentController::class, 'store'])->middleware('permission:employee-assignment.create')->name('employees.assignments.store');
    Route::put('employee-assignments/{assignment}', [EmployeeAssignmentController::class, 'update'])->middleware('permission:employee-assignment.update')->name('employee-assignments.update');
    Route::post('employee-assignments/{assignment}/end', [EmployeeAssignmentController::class, 'end'])->middleware('permission:employee-assignment.end')->name('employee-assignments.end');
    Route::post('employees/{employee}/transfer', [EmployeeAssignmentController::class, 'transfer'])->middleware('permission:employee-assignment.transfer')->name('employees.transfer');
    Route::post('employees/{employee}/promote', [EmployeeAssignmentController::class, 'promote'])->middleware('permission:employee-assignment.promote')->name('employees.promote');
    Route::post('employees/{employee}/restore', [EmployeeController::class, 'restore'])->middleware('permission:employee.restore')->name('employees.restore');
    Route::resource('employees', EmployeeController::class)->middleware('permission:employee.view');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('_fixtures/hr-governance/login', function () {
        abort_unless(str_ends_with((string) DB::connection()->getDatabaseName(), '_testing'), 404);
        $user = User::query()->where('email', 'governance.fixture@schema.test')->firstOrFail();
        Auth::login($user);

        return redirect()->route('admin.hr.governance.dashboard', ['company_id' => request()->integer('company_id')]);
    })->name('fixtures.hr-governance.login');
}
