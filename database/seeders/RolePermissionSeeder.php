<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $modules = ['company', 'organization', 'employee', 'position', 'recruitment', 'onboarding', 'contract', 'attendance', 'shift', 'leave', 'payroll', 'insurance', 'tax', 'performance', 'training', 'benefit', 'succession', 'labor-relation', 'offboarding', 'workflow', 'report', 'document', 'project', 'maintenance', 'integration', 'security', 'notification', 'audit', 'setting', 'article', 'article-category', 'product', 'product-category', 'media', 'page', 'menu', 'dashboard', 'seo', 'system'];
        $actions = ['view', 'create', 'update', 'delete', 'export', 'import', 'approve', 'reject', 'publish', 'restore', 'audit'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::query()->firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        foreach (['payroll.calculate', 'company.change-status', 'organization.tenant-all', 'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.export', 'cost-center.audit', 'organization-unit.view', 'organization-unit.create', 'organization-unit.update', 'organization-unit.move', 'organization-unit.delete', 'organization-unit.export', 'organization-unit.audit', 'job-position.view', 'job-position.create', 'job-position.update', 'job-position.delete', 'job-position.change-headcount', 'job-position.change-critical', 'job-position.export', 'job-position.audit',
            'employee.portal.view', 'employee.view-sensitive', 'employee.restore', 'employee.change-status', 'employee.assign-manager', 'employee.link-user', 'employee.unlink-user', 'employee.upload-avatar',
            'employee-assignment.view', 'employee-assignment.create', 'employee-assignment.update', 'employee-assignment.end', 'employee-assignment.transfer', 'employee-assignment.promote',
            'employee-history.view', 'employee-history.create-manual', 'employee-history.export',
            'employee-credential.view', 'employee-credential.create', 'employee-credential.update', 'employee-credential.delete', 'employee-credential.download', 'employee-credential.export',
            'employment-contract.view', 'employment-contract.view-salary', 'employment-contract.create', 'employment-contract.update', 'employment-contract.submit', 'employment-contract.approve', 'employment-contract.activate', 'employment-contract.terminate', 'employment-contract.download', 'employment-contract.audit',
            'data-consent.view', 'data-consent.grant', 'data-consent.withdraw', 'data-consent.renew', 'data-consent.download-evidence',
            'employee-document.view', 'employee-document.upload', 'employee-document.download', 'employee-document.version', 'employee-document.sign', 'employee-document.delete', 'employee-document.audit',
            'employee.archive', 'employee.manage-avatar',
            'employee-assignment.change-manager', 'employee-assignment.audit',
            'employee-credential.archive', 'employee-credential.restore', 'employee-credential.view-sensitive', 'employee-credential.audit',
            'employment-contract.cancel', 'employment-contract.renew', 'employment-contract.export',
            'data-consent.export', 'data-consent.audit',
            'employee-document.archive', 'employee-document.restore', 'document.upload', 'document.download', 'document.archive',
            'employee-user.view', 'employee-user.link', 'employee-user.unlink', 'employee-user.create', 'employee-user.invite', 'employee-user.disable', 'employee-user.audit',
            'recruitment-requisition.view', 'recruitment-requisition.create', 'recruitment-requisition.update', 'recruitment-requisition.submit', 'recruitment-requisition.approve', 'recruitment-requisition.reject', 'recruitment-requisition.open', 'recruitment-requisition.pause', 'recruitment-requisition.fill', 'recruitment-requisition.cancel', 'recruitment-requisition.duplicate', 'recruitment-requisition.export', 'recruitment-requisition.audit', 'recruitment-requisition.override-headcount',
            'candidate.view', 'candidate.create', 'candidate.update', 'candidate.archive', 'candidate.restore', 'candidate.merge', 'candidate.view-sensitive', 'candidate.upload-resume', 'candidate.download-resume', 'candidate.manage-consent', 'candidate.export', 'candidate.audit',
            'application.view', 'application.create', 'application.update', 'application.change-stage', 'application.bulk-change-stage', 'application.score', 'application.evaluate', 'application.reject', 'application.withdraw', 'application.reopen', 'application.offer', 'application.hire', 'application.export', 'application.audit',
            'interview.view', 'interview.view-panel-summary', 'interview.view-private-notes', 'interview.schedule', 'interview.reschedule', 'interview.cancel', 'interview.complete', 'interview.assign-interviewer', 'interview.evaluate', 'interview.reopen-evaluation', 'interview.audit',
            'candidate-offer.view', 'candidate-offer.create', 'candidate-offer.update', 'candidate-offer.submit', 'candidate-offer.approve', 'candidate-offer.send', 'candidate-offer.accept', 'candidate-offer.decline', 'candidate-offer.withdraw', 'candidate-offer.view-salary', 'candidate-offer.download', 'candidate-offer.audit', 'candidate-offer.hire', 'onboarding.create-from-hire',
            'onboarding.view', 'onboarding.create', 'onboarding.update', 'onboarding.assign-buddy', 'onboarding.start', 'onboarding.complete-item', 'onboarding.manage-provisioning', 'onboarding.start-probation-review', 'onboarding.complete', 'onboarding.cancel', 'onboarding.audit', 'onboarding.export',
            'work-calendar.view', 'work-calendar.manage',
            'shift.view', 'shift.manage', 'shift.assign',
            'attendance.view', 'attendance.view-own', 'attendance.check-in', 'attendance.check-out', 'attendance.adjust', 'attendance.approve', 'attendance.lock', 'attendance.export',
            'leave.view', 'leave.view-own', 'leave.request', 'leave.approve', 'leave.adjust-balance', 'leave.manage-policy', 'leave.export',
            'overtime.view', 'overtime.view-own', 'overtime.request', 'overtime.approve', 'overtime.manage-policy', 'overtime.export',
            'payroll-period.view', 'payroll-period.manage', 'payroll-period.create', 'payroll-period.update', 'payroll-period.open', 'payroll-period.lock', 'payroll-period.close', 'payroll-period.delete', 'payroll-input.generate', 'payroll-input.validate', 'payroll-input.create-version', 'payroll-period.unlock', 'payroll-input.export',
            'payroll.component.view', 'payroll.component.manage', 'payroll.assignment.view', 'payroll.assignment.manage', 'payroll.run.view', 'payroll.run.create', 'payroll.run.calculate', 'payroll.run.submit', 'payroll.run.approve', 'payroll.run.reject', 'payroll.run.finalize', 'payroll.item.view', 'payroll.export',
            'payroll.insurance.view', 'payroll.insurance.policy.create', 'payroll.insurance.policy.update', 'payroll.insurance.profile.manage', 'payroll.insurance.calculate',
            'payroll.tax.view', 'payroll.tax.policy.create', 'payroll.tax.policy.update', 'payroll.tax.profile.manage', 'payroll.tax.calculate', 'payroll.tax.dependent.manage',
            'tax-policy.view', 'tax-policy.create', 'tax-policy.update', 'tax-policy.approve', 'tax-policy.activate',
            'payroll.payslip.view', 'payroll.payslip.generate', 'payroll.payslip.publish', 'payroll.payslip.download', 'payroll.payslip.revoke', 'payroll.payslip.regenerate',
            'payroll.payment.view', 'payroll.payment.account.manage', 'payroll.payment.account.verify', 'payroll.payment.batch.create', 'payroll.payment.batch.review', 'payroll.payment.batch.approve', 'payroll.payment.batch.release', 'payroll.payment.batch.download', 'payroll.payment.reconcile',
            'payroll.accounting.view', 'payroll.accounting.policy.manage', 'payroll.accounting.mapping.manage', 'payroll.accounting.journal.create', 'payroll.accounting.journal.review', 'payroll.accounting.journal.approve', 'payroll.accounting.journal.post', 'payroll.accounting.journal.export', 'payroll.accounting.journal.download', 'payroll.accounting.journal.reverse',
            'payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply', 'payroll.adjustment.supersede', 'payroll.adjustment.recovery.manage', 'payroll.adjustment.payment.create', 'payroll.adjustment.accounting.create', 'payroll.adjustment.statement.generate', 'payroll.adjustment.statement.download', 'payroll.adjustment.downstream.retry',
            'payroll.annual_tax.view', 'payroll.annual_tax.create', 'payroll.annual_tax.collect', 'payroll.annual_tax.calculate', 'payroll.annual_tax.validate', 'payroll.annual_tax.submit', 'payroll.annual_tax.review', 'payroll.annual_tax.approve', 'payroll.annual_tax.finalize', 'payroll.annual_tax.reject', 'payroll.annual_tax.supersede', 'payroll.annual_tax.statement.generate', 'payroll.annual_tax.statement.download', 'payroll.annual_tax.payment.create', 'payroll.annual_tax.refund.create', 'payroll.annual_tax.accounting.create', 'payroll.annual_tax.downstream.retry',
            'erp.integration.view', 'erp.integration.connection.view', 'erp.integration.connection.manage', 'erp.integration.export.create', 'erp.integration.export.validate', 'erp.integration.export.execute', 'erp.integration.export.retry', 'erp.integration.export.reverse',
            'government.filing.view', 'government.filing.create', 'government.filing.generate', 'government.filing.validate', 'government.filing.approve', 'government.filing.submit', 'government.filing.download',
            'hr.lifecycle.view', 'hr.lifecycle.create', 'hr.lifecycle.onboarding', 'hr.lifecycle.transfer', 'hr.lifecycle.promote', 'hr.lifecycle.terminate',
            'talent.performance.view', 'talent.performance.manage', 'talent.goal.create', 'talent.goal.update', 'talent.review.submit', 'talent.review.approve', 'talent.review.close',
            'career.view', 'career.manage', 'career.plan.create', 'succession.view', 'succession.manage', 'succession.approve',
            'analytics.view', 'analytics.manage', 'analytics.export',
            'hr.governance.view', 'hr.governance.manage', 'hr.quality.run', 'hr.audit.view', 'hr.document.manage',
            'workflow.view', 'workflow.definition.manage', 'workflow.start', 'workflow.task.claim', 'workflow.task.decide', 'workflow.cancel', 'workflow.audit.view',
            'notification.view', 'notification.template.manage', 'notification.delivery.view', 'notification.retry', 'notification.preference.manage',
            'iam.view', 'iam.request.create', 'iam.request.review', 'iam.request.approve', 'iam.assignment.manage', 'iam.privileged.manage', 'iam.sod.manage', 'iam.review.manage', 'iam.audit.view',
            'integration.view', 'integration.connection.manage', 'integration.message.view', 'integration.retry', 'integration.deadletter.manage', 'integration.audit.view',
            'audit.view', 'audit.search', 'audit.export', 'audit.retention.manage',
            'operations.view', 'operations.manage', 'api.documentation.view',
            'branch.view', 'branch.create', 'branch.update', 'branch.archive', 'branch.bonus.view', 'branch.bonus.create', 'branch.bonus.approve',
            'commerce.store.view', 'commerce.store.create', 'commerce.store.update',
            'commerce.payment.view', 'commerce.payment.record', 'commerce.fulfillment.view', 'commerce.fulfillment.update',
            'commerce.seller.governance.view', 'commerce.seller.governance.manage',
            'inventory.view', 'inventory.update', 'stock-count.view', 'stock-count.create', 'stock-count.submit', 'stock-count.approve', 'inventory-adjustment.view', 'inventory-adjustment.create', 'inventory-adjustment.approve', 'inventory-adjustment.post',
            'procurement.view', 'procurement.manage',
            'vendor.view', 'vendor.manage', 'purchase-request.view', 'purchase-request.create', 'purchase-request.submit', 'purchase-request.approve', 'purchase-request.cancel',
            'purchase-order.view', 'purchase-order.create', 'purchase-order.approve', 'purchase-order.send', 'purchase-order.cancel',
            'goods-receipt.view', 'goods-receipt.create', 'goods-receipt.receive', 'procurement-goods-return.view', 'procurement-goods-return.create', 'procurement-goods-return.submit', 'procurement-goods-return.approve', 'procurement-goods-return.post', 'supplier-credit.view', 'supplier-credit.create', 'supplier-credit.submit', 'supplier-credit.approve', 'supplier-credit.post', 'supplier-refund.create', 'supplier-refund.submit', 'supplier-refund.approve', 'supplier-refund.post', 'vendor-bill.view', 'vendor-bill.create', 'vendor-bill.approve',
            'service-acceptance.view', 'service-acceptance.create', 'service-acceptance.submit', 'service-acceptance.approve', 'service-acceptance.reverse',
            'ap-payment.view', 'ap-payment.create', 'treasury.payment.reversal.request', 'treasury.payment.reversal.approve', 'vendor-bill.reversal.request', 'vendor-bill.reversal.approve', 'accounting-period.view', 'accounting.period.manage', 'financial-statement.view',
            'finance.operations.view', 'finance.operations.manage', 'finance.operations.approve',
            'finance.fx-rate.view', 'finance.fx-rate.manage', 'finance.fx-rate.approve', 'finance.fx-exposure.view', 'finance.fx-revaluation.view', 'finance.fx-revaluation.manage', 'finance.fx-revaluation.approve', 'finance.fx-revaluation.post',
            'asset-impairment.create', 'asset-impairment.approve', 'asset-impairment.post',
            'asset-disposal.create', 'asset-disposal.approve', 'asset-disposal.post',
            'treasury.view', 'treasury.manage', 'treasury.reconcile', 'treasury-opening-balance.view', 'treasury-opening-balance.create', 'treasury-opening-balance.submit', 'treasury-opening-balance.approve', 'treasury-opening-balance.post', 'treasury-internal-transfer.view', 'treasury-internal-transfer.create', 'treasury-internal-transfer.submit', 'treasury-internal-transfer.approve', 'treasury-internal-transfer.post', 'treasury-internal-transfer.reverse', 'accounting.period.view', 'accounting.period.close.request', 'accounting.period.close.approve', 'accounting.period.reopen', 'approval.inbox.view',
            'accounting.adjustment.view', 'accounting.adjustment.create', 'accounting.adjustment.submit', 'accounting.adjustment.approve', 'accounting.adjustment.reject', 'accounting.adjustment.post',
            'crm.view', 'crm.manage', 'sales.view', 'sales.manage', 'customer-invoice.view', 'customer-invoice.manage', 'customer-credit-note.view', 'customer-credit-note.create', 'customer-credit-note.submit', 'customer-credit-note.approve', 'customer-credit-note.post', 'customer-refund.view', 'customer-refund.create', 'customer-refund.submit', 'customer-refund.approve', 'customer-refund.post', 'ar.view', 'collection.view', 'collection.create', 'cash-flow.view',
            'branch-commerce.view', 'branch-commerce.manage', 'branch-commerce.checkout',
            'project.manage-members', 'project.manage-task', 'project.timesheet.create', 'project.timesheet.approve', 'project.complete',
            'maintenance.request.create', 'maintenance.request.submit', 'maintenance.request.approve', 'maintenance.work-order.create', 'maintenance.work-order.assign', 'maintenance.work-order.execute', 'maintenance.part.issue', 'maintenance.plan.manage',
        ] as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['Super Admin', 'System Admin', 'HR Admin', 'HR Manager', 'Recruiter', 'C&B', 'L&D', 'HRBP', 'Finance', 'Manager', 'Employee', 'Content Admin', 'Editor', 'Seller', 'Buyer', 'Viewer'];
        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('Super Admin')->syncPermissions(Permission::all());
        Role::findByName('System Admin')->syncPermissions(Permission::whereNotIn('name', ['payroll.update', 'payroll.delete'])->get());
        Role::findByName('HR Admin')->syncPermissions(Permission::whereIn('name', ['company.view', 'company.create', 'company.update', 'company.change-status', 'cost-center.view', 'cost-center.create', 'cost-center.update', 'organization-unit.view', 'organization-unit.create', 'organization-unit.update', 'organization-unit.move', 'job-position.view', 'job-position.create', 'job-position.update', 'job-position.change-headcount', 'job-position.change-critical', 'employee.view', 'employee.create', 'employee.update', 'employee.restore', 'employee.change-status', 'employee.assign-manager', 'employee.link-user', 'employee.unlink-user', 'employee.upload-avatar', 'employee-assignment.view', 'employee-assignment.create', 'employee-assignment.update', 'employee-assignment.end', 'employee-assignment.transfer', 'employee-assignment.promote', 'employee-history.view', 'employee-credential.view', 'employee-credential.create', 'employee-credential.update', 'employment-contract.view', 'employment-contract.create', 'employment-contract.update', 'data-consent.view', 'data-consent.grant', 'data-consent.withdraw', 'employee-document.view', 'employee-document.upload', 'employee-document.download'])->get());
        Role::findByName('HR Manager')->syncPermissions(Permission::whereIn('name', ['company.view', 'cost-center.view', 'organization-unit.view', 'job-position.view', 'employee.view', 'employee-assignment.view', 'employee-history.view', 'employee-credential.view', 'employment-contract.view', 'data-consent.view', 'employee-document.view'])->get());
        Role::findByName('HR Admin')->givePermissionTo(Permission::where('name', 'like', 'hr.lifecycle.%')->get());
        Role::findByName('HR Admin')->givePermissionTo(['employment-contract.submit', 'employment-contract.approve', 'employment-contract.activate', 'employment-contract.terminate', 'employment-contract.renew']);
        Role::findByName('HR Manager')->givePermissionTo('hr.lifecycle.view');
        Role::findByName('HR Admin')->givePermissionTo(Permission::where('name', 'like', 'talent.%')->get());
        Role::findByName('HR Manager')->givePermissionTo(['talent.performance.view', 'talent.goal.create', 'talent.goal.update', 'talent.review.submit']);
        Role::findByName('HR Admin')->givePermissionTo(Permission::whereIn('name', ['career.view', 'career.manage', 'career.plan.create', 'succession.view', 'succession.manage', 'succession.approve'])->get());
        Role::findByName('HR Manager')->givePermissionTo(['career.view', 'career.plan.create', 'succession.view']);
        Role::findByName('HR Admin')->givePermissionTo(['analytics.view', 'analytics.manage', 'analytics.export']);
        Role::findByName('HR Manager')->givePermissionTo('analytics.view');
        Role::findByName('HR Admin')->givePermissionTo(Permission::whereIn('name', ['hr.governance.view', 'hr.governance.manage', 'hr.quality.run', 'hr.audit.view', 'hr.document.manage'])->get());
        Role::findByName('HR Manager')->givePermissionTo(Permission::whereIn('name', ['hr.governance.view', 'hr.audit.view'])->get());
        Role::findByName('HR Admin')->givePermissionTo(Permission::where('name', 'like', 'workflow.%')->get());
        Role::findByName('HR Manager')->givePermissionTo(Permission::whereIn('name', ['workflow.view', 'workflow.start', 'workflow.task.claim', 'workflow.task.decide'])->get());
        Role::findByName('HR Admin')->givePermissionTo(Permission::where('name', 'like', 'notification.%')->get());
        Role::findByName('HR Manager')->givePermissionTo(['notification.view', 'notification.preference.manage']);
        Role::findByName('Super Admin')->givePermissionTo(Permission::where('name', 'like', 'iam.%')->get());
        Role::findByName('System Admin')->givePermissionTo(Permission::where('name', 'like', 'iam.%')->get());
        Role::findByName('Super Admin')->givePermissionTo(['operations.view', 'operations.manage']);
        Role::findByName('System Admin')->givePermissionTo(['operations.view', 'operations.manage']);
        Role::findByName('HR Admin')->givePermissionTo(Permission::whereIn('name', ['iam.view', 'iam.request.create', 'iam.request.review', 'iam.assignment.manage', 'iam.review.manage'])->get());
        Role::findByName('HR Manager')->givePermissionTo(Permission::whereIn('name', ['iam.view', 'iam.request.create'])->get());
        Role::findByName('Recruiter')->syncPermissions(Permission::where(fn ($query) => $query->where('name', 'like', 'recruitment-requisition.%')->orWhere('name', 'like', 'candidate.%')->orWhere('name', 'like', 'application.%')->orWhere('name', 'like', 'interview.%')->orWhere('name', 'like', 'candidate-offer.%'))->whereNotIn('name', ['recruitment-requisition.override-headcount', 'candidate-offer.approve', 'candidate-offer.view-salary'])->get());
        Role::findByName('C&B')->syncPermissions(Permission::where(fn ($query) => $query->where('name', 'like', 'payroll-%')->orWhere('name', 'like', 'payroll.%')->orWhere('name', 'like', 'attendance.%')->orWhere('name', 'like', 'leave.%')->orWhere('name', 'like', 'overtime.%'))->get());
        Role::findByName('Content Admin')->syncPermissions(Permission::whereIn('name', collect(['article', 'article-category', 'product', 'product-category', 'media', 'page', 'menu', 'seo'])->crossJoin($actions)->map(fn ($pair) => implode('.', $pair)))->get());
        Role::findByName('Seller')->syncPermissions(['commerce.store.view', 'commerce.store.create', 'commerce.store.update', 'commerce.fulfillment.view', 'commerce.fulfillment.update', 'product.view', 'product.create', 'product.update', 'product.delete', 'product-category.view', 'inventory.view', 'inventory.update', 'stock-count.view', 'stock-count.create', 'stock-count.submit', 'inventory-adjustment.view', 'inventory-adjustment.create', 'branch-commerce.view', 'branch-commerce.manage', 'branch-commerce.checkout']);
        Role::findByName('Finance')->syncPermissions(Permission::whereIn('name', ['commerce.payment.view', 'commerce.payment.record', 'payroll.assignment.view', 'payroll.accounting.view', 'payroll.tax.view', 'procurement.view', 'procurement.manage', 'vendor.view', 'vendor.manage', 'purchase-request.view', 'purchase-request.create', 'purchase-request.submit', 'purchase-request.approve', 'purchase-request.cancel', 'purchase-order.view', 'purchase-order.create', 'purchase-order.approve', 'purchase-order.send', 'purchase-order.cancel', 'goods-receipt.view', 'goods-receipt.create', 'goods-receipt.receive', 'service-acceptance.view', 'service-acceptance.create', 'service-acceptance.submit', 'service-acceptance.approve', 'service-acceptance.reverse', 'vendor-bill.view', 'vendor-bill.create', 'vendor-bill.approve', 'ap-payment.view', 'ap-payment.create', 'accounting-period.view', 'accounting.period.manage', 'financial-statement.view', 'finance.operations.view', 'finance.operations.manage', 'finance.operations.approve', 'treasury.view', 'treasury.manage', 'treasury.reconcile', 'treasury-internal-transfer.view', 'treasury-internal-transfer.create', 'treasury-internal-transfer.submit', 'treasury-internal-transfer.approve', 'treasury-internal-transfer.post', 'treasury-internal-transfer.reverse', 'accounting.period.view', 'accounting.period.close.request', 'accounting.period.close.approve', 'accounting.period.reopen', 'approval.inbox.view', 'accounting.adjustment.view', 'accounting.adjustment.create', 'accounting.adjustment.submit', 'accounting.adjustment.approve', 'accounting.adjustment.reject', 'accounting.adjustment.post'])->get());
        Role::findByName('Finance')->givePermissionTo(['crm.view', 'crm.manage', 'sales.view', 'sales.manage', 'customer-invoice.view', 'customer-invoice.manage', 'customer-credit-note.view', 'customer-credit-note.create', 'customer-credit-note.submit', 'customer-credit-note.approve', 'customer-credit-note.post', 'customer-refund.view', 'customer-refund.create', 'customer-refund.submit', 'customer-refund.approve', 'customer-refund.post', 'ar.view', 'collection.view', 'collection.create', 'cash-flow.view']);
        Role::findByName('Finance')->givePermissionTo(['stock-count.view', 'stock-count.approve', 'inventory-adjustment.view', 'inventory-adjustment.approve', 'inventory-adjustment.post']);
        Role::findByName('Finance')->givePermissionTo(['asset-impairment.create', 'asset-impairment.approve', 'asset-impairment.post', 'asset-disposal.create', 'asset-disposal.approve', 'asset-disposal.post']);
        Role::findByName('Finance')->givePermissionTo(['tax-policy.view', 'tax-policy.create', 'tax-policy.update', 'tax-policy.approve', 'tax-policy.activate']);
        Role::findByName('Finance')->givePermissionTo(['procurement-goods-return.view', 'procurement-goods-return.create', 'procurement-goods-return.submit', 'procurement-goods-return.approve', 'procurement-goods-return.post', 'supplier-credit.view', 'supplier-credit.create', 'supplier-credit.submit', 'supplier-credit.approve', 'supplier-credit.post']);
        Role::findByName('Employee')->syncPermissions(['employee.portal.view', 'attendance.view-own', 'attendance.check-in', 'attendance.check-out', 'leave.view-own', 'leave.request', 'overtime.view-own', 'overtime.request', 'notification.view', 'project.view', 'project.timesheet.create']);
        Role::findByName('Manager')->syncPermissions(['employee.portal.view', 'attendance.view-own', 'attendance.check-in', 'attendance.check-out', 'leave.view-own', 'leave.request', 'overtime.view-own', 'overtime.request', 'notification.view', 'project.view', 'project.create', 'project.update', 'project.manage-members', 'project.manage-task', 'project.timesheet.create', 'project.timesheet.approve', 'project.complete']);
        Role::findByName('Manager')->givePermissionTo(['branch.bonus.view', 'branch.bonus.create']);
        Role::findByName('Manager')->givePermissionTo(['maintenance.view', 'maintenance.request.create', 'maintenance.request.submit']);
        Role::findByName('HR Admin')->givePermissionTo(['maintenance.view', 'maintenance.work-order.assign']);
        Role::findByName('Finance')->givePermissionTo('maintenance.view');
        Role::findByName('C&B')->givePermissionTo(['branch.bonus.view', 'branch.bonus.approve']);
        Role::findByName('HR Admin')->givePermissionTo(['branch.view', 'branch.create', 'branch.update', 'branch.archive']);
        Role::findByName('HR Manager')->givePermissionTo('branch.view');
        Role::findByName('Viewer')->syncPermissions(Permission::where('name', 'like', '%.view')->get());
    }
}
