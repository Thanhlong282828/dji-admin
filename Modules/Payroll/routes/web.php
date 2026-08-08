<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\AccountingAccountController;
use Modules\Payroll\Http\Controllers\AccountingJournalController;
use Modules\Payroll\Http\Controllers\AccountingMappingController;
use Modules\Payroll\Http\Controllers\AccountingPolicyController;
use Modules\Payroll\Http\Controllers\ErpExportController;
use Modules\Payroll\Http\Controllers\GovernmentFilingController;
use Modules\Payroll\Http\Controllers\InsuranceAdministrationController;
use Modules\Payroll\Http\Controllers\PaymentAccountController;
use Modules\Payroll\Http\Controllers\PaymentBatchController;
use Modules\Payroll\Http\Controllers\PayrollAdjustmentController;
use Modules\Payroll\Http\Controllers\PayrollAnnualTaxReconciliationController;
use Modules\Payroll\Http\Controllers\PayrollController;
use Modules\Payroll\Http\Controllers\PayrollDeliveryController;
use Modules\Payroll\Http\Controllers\PayrollExportController;
use Modules\Payroll\Http\Controllers\PayrollFragmentController;
use Modules\Payroll\Http\Controllers\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\PayrollRunController;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Http\Controllers\SalaryComponentAssignmentController;
use Modules\Payroll\Http\Controllers\SalaryComponentController;
use Modules\Payroll\Http\Controllers\TaxAdministrationController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('payroll/government-filings', [GovernmentFilingController::class, 'index'])->middleware('permission:government.filing.view')->name('payroll.government-filings.index');
    Route::get('payroll/government-filings/create', [GovernmentFilingController::class, 'create'])->middleware('permission:government.filing.create')->name('payroll.government-filings.create');
    Route::post('payroll/government-filings', [GovernmentFilingController::class, 'store'])->middleware('permission:government.filing.create')->name('payroll.government-filings.store');
    Route::get('payroll/government-filings/{filing}', [GovernmentFilingController::class, 'show'])->whereNumber('filing')->middleware('permission:government.filing.view')->name('payroll.government-filings.show');
    Route::post('payroll/government-filings/{filing}/{action}', [GovernmentFilingController::class, 'mutate'])->whereNumber('filing')->whereIn('action', ['generate', 'validate', 'approve', 'submit', 'retry', 'reject'])->middleware('permission:government.filing.view')->name('payroll.government-filings.mutate');
    Route::get('payroll/government-filings/{filing}/documents/{document}', [GovernmentFilingController::class, 'download'])->whereNumber('filing')->whereNumber('document')->middleware('permission:government.filing.download')->name('payroll.government-filings.download');
    Route::get('payroll/erp/exports', [ErpExportController::class, 'index'])->middleware('permission:erp.integration.view')->name('payroll.erp.exports.index');
    Route::get('payroll/erp/connections', [ErpExportController::class, 'connections'])->middleware('permission:erp.integration.connection.view')->name('payroll.erp.connections.index');
    Route::get('payroll/erp/connections/create', [ErpExportController::class, 'connectionCreate'])->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.connections.create');
    Route::post('payroll/erp/connections', [ErpExportController::class, 'connectionStore'])->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.connections.store');
    Route::get('payroll/erp/connections/{connection}', [ErpExportController::class, 'connectionShow'])->whereNumber('connection')->middleware('permission:erp.integration.connection.view')->name('payroll.erp.connections.show');
    Route::get('payroll/erp/connections/{connection}/edit', [ErpExportController::class, 'connectionEdit'])->whereNumber('connection')->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.connections.edit');
    Route::put('payroll/erp/connections/{connection}', [ErpExportController::class, 'connectionUpdate'])->whereNumber('connection')->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.connections.update');
    Route::get('payroll/erp/mappings', [ErpExportController::class, 'mappings'])->middleware('permission:erp.integration.connection.view')->name('payroll.erp.mappings.index');
    Route::get('payroll/erp/mappings/create', [ErpExportController::class, 'mappingCreate'])->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.mappings.create');
    Route::post('payroll/erp/mappings', [ErpExportController::class, 'mappingStore'])->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.mappings.store');
    Route::get('payroll/erp/mappings/{mapping}', [ErpExportController::class, 'mappingShow'])->whereNumber('mapping')->middleware('permission:erp.integration.connection.view')->name('payroll.erp.mappings.show');
    Route::get('payroll/erp/mappings/{mapping}/edit', [ErpExportController::class, 'mappingEdit'])->whereNumber('mapping')->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.mappings.edit');
    Route::put('payroll/erp/mappings/{mapping}', [ErpExportController::class, 'mappingUpdate'])->whereNumber('mapping')->middleware('permission:erp.integration.connection.manage')->name('payroll.erp.mappings.update');
    Route::get('payroll/erp/exports/create', [ErpExportController::class, 'create'])->middleware('permission:erp.integration.export.create')->name('payroll.erp.exports.create');
    Route::post('payroll/erp/exports', [ErpExportController::class, 'store'])->middleware('permission:erp.integration.export.create')->name('payroll.erp.exports.store');
    Route::get('payroll/erp/exports/{export}', [ErpExportController::class, 'show'])->whereNumber('export')->middleware('permission:erp.integration.view')->name('payroll.erp.exports.show');
    Route::get('payroll/erp/exports/{export}/download', [ErpExportController::class, 'download'])->whereNumber('export')->middleware('permission:erp.integration.view')->name('payroll.erp.exports.download');
    Route::post('payroll/erp/exports/{export}/{action}', [ErpExportController::class, 'mutate'])->whereNumber('export')->whereIn('action', ['validate', 'execute', 'retry', 'reverse'])->middleware('permission:erp.integration.view')->name('payroll.erp.exports.mutate');
    Route::get('payroll/annual-tax', [PayrollAnnualTaxReconciliationController::class, 'index'])->middleware('permission:payroll.annual_tax.view')->name('payroll.annual-tax.index');
    Route::get('payroll/annual-tax/create', [PayrollAnnualTaxReconciliationController::class, 'create'])->middleware('permission:payroll.annual_tax.create')->name('payroll.annual-tax.create');
    Route::post('payroll/annual-tax', [PayrollAnnualTaxReconciliationController::class, 'store'])->middleware('permission:payroll.annual_tax.create')->name('payroll.annual-tax.store');
    Route::get('payroll/annual-tax/{reconciliation}', [PayrollAnnualTaxReconciliationController::class, 'show'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.view')->name('payroll.annual-tax.show');
    Route::post('payroll/annual-tax/{reconciliation}/collect', [PayrollAnnualTaxReconciliationController::class, 'collect'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.calculate')->name('payroll.annual-tax.collect');
    Route::post('payroll/annual-tax/{reconciliation}/calculate', [PayrollAnnualTaxReconciliationController::class, 'calculate'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.calculate')->name('payroll.annual-tax.calculate');
    Route::post('payroll/annual-tax/{reconciliation}/validate', [PayrollAnnualTaxReconciliationController::class, 'validateReconciliation'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.validate')->name('payroll.annual-tax.validate');
    Route::post('payroll/annual-tax/{reconciliation}/submit', [PayrollAnnualTaxReconciliationController::class, 'submit'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.submit')->name('payroll.annual-tax.submit');
    Route::post('payroll/annual-tax/{reconciliation}/review', [PayrollAnnualTaxReconciliationController::class, 'review'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.review')->name('payroll.annual-tax.review');
    Route::post('payroll/annual-tax/{reconciliation}/approve', [PayrollAnnualTaxReconciliationController::class, 'approve'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.approve')->name('payroll.annual-tax.approve');
    Route::post('payroll/annual-tax/{reconciliation}/finalize', [PayrollAnnualTaxReconciliationController::class, 'finalize'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.finalize')->name('payroll.annual-tax.finalize');
    Route::post('payroll/annual-tax/{reconciliation}/reject', [PayrollAnnualTaxReconciliationController::class, 'reject'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.reject')->name('payroll.annual-tax.reject');
    Route::post('payroll/annual-tax/{reconciliation}/supersede', [PayrollAnnualTaxReconciliationController::class, 'supersede'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.supersede')->name('payroll.annual-tax.supersede');
    Route::post('payroll/annual-tax/{reconciliation}/statement', [PayrollAnnualTaxReconciliationController::class, 'generateStatement'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.statement.generate')->name('payroll.annual-tax.statement.generate');
    Route::get('payroll/annual-tax/{reconciliation}/statement/{document}', [PayrollAnnualTaxReconciliationController::class, 'downloadStatement'])->whereNumber('reconciliation')->whereNumber('document')->middleware('permission:payroll.annual_tax.statement.download')->name('payroll.annual-tax.statement.download');
    Route::post('payroll/annual-tax/{reconciliation}/payment-intent', [PayrollAnnualTaxReconciliationController::class, 'createPaymentIntent'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.payment.create')->name('payroll.annual-tax.payment-intent');
    Route::post('payroll/annual-tax/{reconciliation}/refund-intent', [PayrollAnnualTaxReconciliationController::class, 'createRefundIntent'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.refund.create')->name('payroll.annual-tax.refund-intent');
    Route::post('payroll/annual-tax/{reconciliation}/settlement-journal', [PayrollAnnualTaxReconciliationController::class, 'createSettlementJournal'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.accounting.create')->name('payroll.annual-tax.settlement-journal');
    Route::post('payroll/annual-tax/{reconciliation}/downstream/retry', [PayrollAnnualTaxReconciliationController::class, 'retryDownstream'])->whereNumber('reconciliation')->middleware('permission:payroll.annual_tax.downstream.retry')->name('payroll.annual-tax.downstream.retry');
    Route::get('payroll/adjustments', [PayrollAdjustmentController::class, 'index'])->middleware('permission:payroll.adjustment.view')->name('payroll.adjustments.index');
    Route::get('payroll/adjustments/create', [PayrollAdjustmentController::class, 'create'])->middleware('permission:payroll.adjustment.create')->name('payroll.adjustments.create');
    Route::post('payroll/adjustments', [PayrollAdjustmentController::class, 'store'])->middleware('permission:payroll.adjustment.create')->name('payroll.adjustments.store');
    Route::get('payroll/adjustments/{adjustment}', [PayrollAdjustmentController::class, 'show'])->whereNumber('adjustment')->middleware('permission:payroll.adjustment.view')->name('payroll.adjustments.show');
    Route::post('payroll/adjustments/{adjustment}/{action}', [PayrollAdjustmentController::class, 'mutate'])->whereNumber('adjustment')->whereIn('action', ['calculate', 'validate', 'submit', 'review', 'approve', 'reject', 'apply'])->middleware('permission:payroll.adjustment.view')->name('payroll.adjustments.mutate');
    Route::post('payroll/adjustments/{adjustment}/downstream/{type}', [PayrollAdjustmentController::class, 'downstream'])->whereNumber('adjustment')->whereIn('type', ['payment', 'accounting', 'statement'])->middleware('permission:payroll.adjustment.view')->name('payroll.adjustments.downstream');
    Route::post('payroll/adjustments/{adjustment}/downstream/{type}/retry', [PayrollAdjustmentController::class, 'retry'])->whereNumber('adjustment')->whereIn('type', ['payment', 'accounting', 'statement'])->middleware('permission:payroll.adjustment.downstream.retry')->name('payroll.adjustments.downstream.retry');
    Route::get('payroll/adjustments/{adjustment}/statement/download', [PayrollAdjustmentController::class, 'downloadStatement'])->whereNumber('adjustment')->middleware('permission:payroll.adjustment.statement.download')->name('payroll.adjustments.statement.download');
    Route::get('payroll/accounting/accounts', [AccountingAccountController::class, 'index'])->middleware('permission:payroll.accounting.view')->name('payroll.accounting.accounts.index');
    Route::get('payroll/accounting/accounts/create', [AccountingAccountController::class, 'create'])->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.accounts.create');
    Route::post('payroll/accounting/accounts', [AccountingAccountController::class, 'store'])->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.accounts.store');
    Route::get('payroll/accounting/accounts/{account}/edit', [AccountingAccountController::class, 'edit'])->whereNumber('account')->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.accounts.edit');
    Route::put('payroll/accounting/accounts/{account}', [AccountingAccountController::class, 'update'])->whereNumber('account')->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.accounts.update');
    Route::get('payroll/accounting/policies', [AccountingPolicyController::class, 'index'])->middleware('permission:payroll.accounting.view')->name('payroll.accounting.policies.index');
    Route::get('payroll/accounting/policies/create', [AccountingPolicyController::class, 'create'])->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.policies.create');
    Route::post('payroll/accounting/policies', [AccountingPolicyController::class, 'store'])->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.policies.store');
    Route::get('payroll/accounting/policies/{policy}', [AccountingPolicyController::class, 'show'])->whereNumber('policy')->middleware('permission:payroll.accounting.view')->name('payroll.accounting.policies.show');
    Route::get('payroll/accounting/policies/{policy}/edit', [AccountingPolicyController::class, 'edit'])->whereNumber('policy')->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.policies.edit');
    Route::put('payroll/accounting/policies/{policy}', [AccountingPolicyController::class, 'update'])->whereNumber('policy')->middleware('permission:payroll.accounting.policy.manage')->name('payroll.accounting.policies.update');
    Route::get('payroll/accounting/mappings', [AccountingMappingController::class, 'index'])->middleware('permission:payroll.accounting.view')->name('payroll.accounting.mappings.index');
    Route::get('payroll/accounting/mappings/create', [AccountingMappingController::class, 'create'])->middleware('permission:payroll.accounting.mapping.manage')->name('payroll.accounting.mappings.create');
    Route::post('payroll/accounting/mappings', [AccountingMappingController::class, 'store'])->middleware('permission:payroll.accounting.mapping.manage')->name('payroll.accounting.mappings.store');
    Route::get('payroll/accounting/mappings/{mapping}/edit', [AccountingMappingController::class, 'edit'])->whereNumber('mapping')->middleware('permission:payroll.accounting.mapping.manage')->name('payroll.accounting.mappings.edit');
    Route::put('payroll/accounting/mappings/{mapping}', [AccountingMappingController::class, 'update'])->whereNumber('mapping')->middleware('permission:payroll.accounting.mapping.manage')->name('payroll.accounting.mappings.update');
    Route::get('payroll/accounting/journals', [AccountingJournalController::class, 'index'])->middleware('permission:payroll.accounting.view')->name('payroll.accounting.journals.index');
    Route::get('payroll/accounting/journals/create', [AccountingJournalController::class, 'create'])->middleware('permission:payroll.accounting.journal.create')->name('payroll.accounting.journals.create');
    Route::post('payroll/accounting/journals', [AccountingJournalController::class, 'store'])->middleware('permission:payroll.accounting.journal.create')->name('payroll.accounting.journals.store');
    Route::get('payroll/accounting/journals/{journal}', [AccountingJournalController::class, 'show'])->whereNumber('journal')->middleware('permission:payroll.accounting.view')->name('payroll.accounting.journals.show');
    Route::post('payroll/accounting/journals/{journal}/{action}', [AccountingJournalController::class, 'mutate'])->whereNumber('journal')->whereIn('action', ['validate', 'review', 'approve', 'reject', 'post', 'export'])->middleware('permission:payroll.accounting.view')->name('payroll.accounting.journals.mutate');
    Route::post('payroll/accounting/journals/{journal}/reverse', [AccountingJournalController::class, 'reverse'])->whereNumber('journal')->middleware('permission:payroll.accounting.journal.reverse')->name('payroll.accounting.journals.reverse');
    Route::get('payroll/accounting/journals/{journal}/download', [AccountingJournalController::class, 'download'])->whereNumber('journal')->middleware('permission:payroll.accounting.journal.download')->name('payroll.accounting.journals.download');
    Route::get('payroll/payment-accounts', [PaymentAccountController::class, 'index'])->middleware('permission:payroll.payment.view')->name('payroll.payment-accounts.index');
    Route::get('payroll/payment-accounts/{employee}', [PaymentAccountController::class, 'show'])->whereNumber('employee')->middleware('permission:payroll.payment.view')->name('payroll.payment-accounts.show');
    Route::post('payroll/payment-accounts/{employee}', [PaymentAccountController::class, 'store'])->whereNumber('employee')->middleware('permission:payroll.payment.account.manage')->name('payroll.payment-accounts.store');
    Route::put('payroll/payment-accounts/{account}', [PaymentAccountController::class, 'update'])->whereNumber('account')->middleware('permission:payroll.payment.account.manage')->name('payroll.payment-accounts.update');
    Route::post('payroll/payment-accounts/{account}/verify', [PaymentAccountController::class, 'verify'])->whereNumber('account')->middleware('permission:payroll.payment.account.verify')->name('payroll.payment-accounts.verify');
    Route::get('payroll/payment-batches', [PaymentBatchController::class, 'index'])->middleware('permission:payroll.payment.view')->name('payroll.payment-batches.index');
    Route::get('payroll/payment-batches/create', [PaymentBatchController::class, 'create'])->middleware('permission:payroll.payment.batch.create')->name('payroll.payment-batches.create');
    Route::post('payroll/payment-batches', [PaymentBatchController::class, 'store'])->middleware('permission:payroll.payment.batch.create')->name('payroll.payment-batches.store');
    Route::get('payroll/payment-batches/{batch}', [PaymentBatchController::class, 'show'])->whereNumber('batch')->middleware('permission:payroll.payment.view')->name('payroll.payment-batches.show');
    Route::post('payroll/payment-batches/{batch}/validate', [PaymentBatchController::class, 'validateBatch'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.review')->name('payroll.payment-batches.validate');
    Route::post('payroll/payment-batches/{batch}/review', [PaymentBatchController::class, 'review'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.review')->name('payroll.payment-batches.review');
    Route::post('payroll/payment-batches/{batch}/approve', [PaymentBatchController::class, 'approve'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.approve')->name('payroll.payment-batches.approve');
    Route::post('payroll/payment-batches/{batch}/reject', [PaymentBatchController::class, 'reject'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.approve')->name('payroll.payment-batches.reject');
    Route::post('payroll/payment-batches/{batch}/release', [PaymentBatchController::class, 'release'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.release')->name('payroll.payment-batches.release');
    Route::get('payroll/payment-batches/{batch}/download', [PaymentBatchController::class, 'download'])->whereNumber('batch')->middleware('permission:payroll.payment.batch.download')->name('payroll.payment-batches.download');
    Route::get('payroll/payslips', [PayslipController::class, 'index'])->middleware('permission:payroll.payslip.view')->name('payroll.payslips.index');
    Route::get('payroll/payslips/{payslip}', [PayslipController::class, 'show'])->whereNumber('payslip')->middleware('permission:payroll.payslip.view')->name('payroll.payslips.show');
    Route::get('payroll/payslips/{payslip}/download', [PayslipController::class, 'download'])->whereNumber('payslip')->middleware('permission:payroll.payslip.download')->name('payroll.payslips.download');
    Route::post('payroll/runs/{run}/payslips/generate', [PayslipController::class, 'generateRun'])->whereNumber('run')->middleware('permission:payroll.payslip.generate')->name('payroll.payslips.generate-run');
    Route::post('payroll/runs/{run}/items/{item}/payslips/generate', [PayslipController::class, 'generateItem'])->whereNumber('run')->whereNumber('item')->middleware('permission:payroll.payslip.generate')->name('payroll.payslips.generate-item');
    Route::post('payroll/payslips/{payslip}/publish', [PayslipController::class, 'publish'])->whereNumber('payslip')->middleware('permission:payroll.payslip.publish')->name('payroll.payslips.publish');
    Route::post('payroll/payslips/{payslip}/regenerate', [PayslipController::class, 'regenerate'])->whereNumber('payslip')->middleware('permission:payroll.payslip.regenerate')->name('payroll.payslips.regenerate');
    Route::post('payroll/payslips/{payslip}/revoke', [PayslipController::class, 'revoke'])->whereNumber('payslip')->middleware('permission:payroll.payslip.revoke')->name('payroll.payslips.revoke');
    Route::get('payroll/tax/policies', [TaxAdministrationController::class, 'policies'])->middleware('permission:payroll.tax.view')->name('payroll.tax.policies.index');
    Route::get('payroll/tax/policies/create', [TaxAdministrationController::class, 'policyForm'])->middleware('permission:payroll.tax.policy.create')->name('payroll.tax.policies.create');
    Route::post('payroll/tax/policies', [TaxAdministrationController::class, 'savePolicy'])->middleware('permission:payroll.tax.policy.create')->name('payroll.tax.policies.store');
    Route::get('payroll/tax/policies/{policy}', [TaxAdministrationController::class, 'policy'])->whereNumber('policy')->middleware('permission:payroll.tax.view')->name('payroll.tax.policies.show');
    Route::get('payroll/tax/policies/{policy}/edit', [TaxAdministrationController::class, 'policyForm'])->whereNumber('policy')->middleware('permission:payroll.tax.policy.update')->name('payroll.tax.policies.edit');
    Route::put('payroll/tax/policies/{policy}', [TaxAdministrationController::class, 'savePolicy'])->whereNumber('policy')->middleware('permission:payroll.tax.policy.update')->name('payroll.tax.policies.update');
    Route::get('payroll/tax/employees', [TaxAdministrationController::class, 'employees'])->middleware('permission:payroll.tax.view')->name('payroll.tax.employees.index');
    Route::get('payroll/tax/employees/{employee}', [TaxAdministrationController::class, 'employee'])->whereNumber('employee')->middleware('permission:payroll.tax.view')->name('payroll.tax.employees.show');
    Route::post('payroll/tax/employees/{employee}/profiles', [TaxAdministrationController::class, 'saveProfile'])->whereNumber('employee')->middleware('permission:payroll.tax.profile.manage')->name('payroll.tax.profiles.store');
    Route::put('payroll/tax/employees/{employee}/profiles/{profile}', [TaxAdministrationController::class, 'saveProfile'])->whereNumber('employee')->whereNumber('profile')->middleware('permission:payroll.tax.profile.manage')->name('payroll.tax.profiles.update');
    Route::get('payroll/insurance/policies', [InsuranceAdministrationController::class, 'policies'])->middleware('permission:payroll.insurance.view')->name('payroll.insurance.policies.index');
    Route::get('payroll/insurance/policies/create', [InsuranceAdministrationController::class, 'policyForm'])->middleware('permission:payroll.insurance.policy.create')->name('payroll.insurance.policies.create');
    Route::post('payroll/insurance/policies', [InsuranceAdministrationController::class, 'savePolicy'])->middleware('permission:payroll.insurance.policy.create')->name('payroll.insurance.policies.store');
    Route::get('payroll/insurance/policies/{policy}', [InsuranceAdministrationController::class, 'policy'])->whereNumber('policy')->middleware('permission:payroll.insurance.view')->name('payroll.insurance.policies.show');
    Route::get('payroll/insurance/policies/{policy}/edit', [InsuranceAdministrationController::class, 'policyForm'])->whereNumber('policy')->middleware('permission:payroll.insurance.policy.update')->name('payroll.insurance.policies.edit');
    Route::put('payroll/insurance/policies/{policy}', [InsuranceAdministrationController::class, 'savePolicy'])->whereNumber('policy')->middleware('permission:payroll.insurance.policy.update')->name('payroll.insurance.policies.update');
    Route::get('payroll/insurance/employees', [InsuranceAdministrationController::class, 'employees'])->middleware('permission:payroll.insurance.view')->name('payroll.insurance.employees.index');
    Route::get('payroll/insurance/employees/{employee}', [InsuranceAdministrationController::class, 'employee'])->whereNumber('employee')->middleware('permission:payroll.insurance.view')->name('payroll.insurance.employees.show');
    Route::post('payroll/insurance/employees/{employee}/profiles', [InsuranceAdministrationController::class, 'saveProfile'])->whereNumber('employee')->middleware('permission:payroll.insurance.profile.manage')->name('payroll.insurance.profiles.store');
    Route::put('payroll/insurance/employees/{employee}/profiles/{profile}', [InsuranceAdministrationController::class, 'saveProfile'])->whereNumber('employee')->whereNumber('profile')->middleware('permission:payroll.insurance.profile.manage')->name('payroll.insurance.profiles.update');
    Route::redirect('payroll-input', '/admin/payroll/inputs')->name('payroll-input.index');
    Route::get('payroll/periods', [PayrollPeriodController::class, 'index'])->middleware('permission:payroll-period.view')->name('payroll.periods.index');
    Route::get('payroll/periods/create', [PayrollPeriodController::class, 'create'])->middleware('permission:payroll-period.create')->name('payroll.periods.create');
    Route::post('payroll/periods', [PayrollPeriodController::class, 'store'])->middleware('permission:payroll-period.create')->name('payroll.periods.store');
    Route::get('payroll/periods/{period}', [PayrollPeriodController::class, 'show'])->whereNumber('period')->middleware('permission:payroll-period.view')->name('payroll.periods.show');
    Route::get('payroll/periods/{period}/edit', [PayrollPeriodController::class, 'edit'])->whereNumber('period')->middleware('permission:payroll-period.update')->name('payroll.periods.edit');
    Route::put('payroll/periods/{period}', [PayrollPeriodController::class, 'update'])->whereNumber('period')->middleware('permission:payroll-period.update')->name('payroll.periods.update');
    Route::post('payroll/periods/{period}/open', [PayrollPeriodController::class, 'open'])->whereNumber('period')->middleware('permission:payroll-period.open')->name('payroll.periods.open');
    Route::post('payroll/periods/{period}/lock', [PayrollPeriodController::class, 'lock'])->whereNumber('period')->middleware('permission:payroll-period.lock')->name('payroll.periods.lock');
    Route::post('payroll/periods/{period}/close', [PayrollPeriodController::class, 'close'])->whereNumber('period')->middleware('permission:payroll-period.close')->name('payroll.periods.close');
    Route::delete('payroll/periods/{period}', [PayrollPeriodController::class, 'destroy'])->whereNumber('period')->middleware('permission:payroll-period.delete')->name('payroll.periods.destroy');
    Route::get('payroll/inputs', [PayrollController::class, 'index'])->middleware('permission:payroll-period.view')->name('payroll.inputs.index');
    Route::get('payroll/inputs/create', [PayrollController::class, 'create'])->middleware('permission:payroll-input.generate')->name('payroll.inputs.create');
    Route::post('payroll/inputs', [PayrollController::class, 'store'])->middleware('permission:payroll-input.generate')->name('payroll.inputs.store');
    Route::get('payroll/inputs/{snapshot}', [PayrollController::class, 'show'])->whereNumber('snapshot')->middleware('permission:payroll-period.view')->name('payroll.inputs.show');
    Route::get('payroll/inputs/{snapshot}/employees', [PayrollController::class, 'employees'])->whereNumber('snapshot')->middleware('permission:payroll-period.view')->name('payroll.inputs.employees.index');
    Route::get('payroll/inputs/{snapshot}/employees/{employeeInput}', [PayrollController::class, 'employee'])->whereNumber('snapshot')->whereNumber('employeeInput')->middleware('permission:payroll-period.view')->name('payroll.inputs.employees.show');
    Route::post('payroll/inputs/{snapshot}/validate', [PayrollController::class, 'validateSnapshot'])->whereNumber('snapshot')->middleware('permission:payroll-input.validate')->name('payroll.inputs.validate');
    Route::post('payroll/inputs/{snapshot}/versions', [PayrollController::class, 'createVersion'])->whereNumber('snapshot')->middleware('permission:payroll-input.create-version')->name('payroll.inputs.versions.store');
    Route::post('payroll/inputs/{snapshot}/create-run', [PayrollController::class, 'createRun'])->whereNumber('snapshot')->middleware('permission:payroll.run.create')->name('payroll.inputs.create-run');
    Route::post('payroll-periods/{period}/generate-inputs', [PayrollController::class, 'generate'])->middleware('permission:payroll-input.generate')->name('payroll-input.generate');
    Route::post('payroll-periods/{period}/lock', [PayrollController::class, 'lock'])->middleware('permission:payroll-period.lock')->name('payroll-period.lock');
    Route::get('payroll/delivery', [PayrollDeliveryController::class, 'index'])
        ->middleware('permission:payroll.component.view')->name('payroll.delivery.index');
    Route::get('payroll/runs', [PayrollDeliveryController::class, 'runs'])
        ->middleware('permission:payroll.run.view')->name('payroll.runs.index');
    Route::get('payroll/runs/{run}', [PayrollDeliveryController::class, 'show'])
        ->whereNumber('run')->middleware('permission:payroll.run.view')->name('payroll.runs.show');
    Route::get('payroll/runs/{run}/items/{item}', [PayrollDeliveryController::class, 'item'])
        ->whereNumber('run')->whereNumber('item')->middleware('permission:payroll.item.view')->name('payroll.runs.items.show');
    Route::get('payroll/runs/{run}/export.csv', [PayrollExportController::class, 'items'])
        ->whereNumber('run')->middleware('permission:payroll.export')->name('payroll.runs.export');
    Route::get('payroll/runs/{run}/components-export.csv', [PayrollExportController::class, 'components'])
        ->whereNumber('run')->middleware('permission:payroll.export')->name('payroll.runs.components-export');
    Route::get('payroll/runs/{run}/fragments/{fragment}', [PayrollFragmentController::class, 'show'])
        ->whereNumber('run')->whereIn('fragment', ['summary', 'readiness', 'items', 'validation', 'history', 'actions'])
        ->middleware('permission:payroll.run.view')->name('payroll.runs.fragments.show');
    Route::post('payroll/components/{action}', [SalaryComponentController::class, 'execute'])
        ->where('action', 'store')->middleware('permission:payroll.component.manage')->name('payroll.components.store');
    Route::put('payroll/components/{component}/{action}', [SalaryComponentController::class, 'execute'])
        ->whereNumber('component')->where('action', 'update')->middleware('permission:payroll.component.manage')->name('payroll.components.update');
    Route::post('payroll/components/{component}/{action}', [SalaryComponentController::class, 'execute'])
        ->whereNumber('component')->where('action', 'status')->middleware('permission:payroll.component.manage')->name('payroll.components.status');
    Route::post('payroll/assignments/{action}', [SalaryComponentAssignmentController::class, 'execute'])
        ->where('action', 'assignment-store')->middleware('permission:payroll.assignment.manage')->name('payroll.assignments.store');
    Route::post('payroll/assignments/{assignment}/{action}', [SalaryComponentAssignmentController::class, 'execute'])
        ->whereNumber('assignment')->where('action', 'assignment-end')->middleware('permission:payroll.assignment.manage')->name('payroll.assignments.end');
    Route::post('payroll/runs/{action}', [PayrollRunController::class, 'store'])
        ->where('action', 'store')->middleware('permission:payroll.run.create')->name('payroll.runs.store');
    Route::post('payroll/runs/{run}/{action}', [PayrollRunController::class, 'mutate'])
        ->whereNumber('run')->whereIn('action', ['calculate', 'recalculate', 'submit', 'approve', 'reject', 'finalize'])
        ->middleware('permission:payroll.run.view')->name('payroll.runs.mutate');
});
