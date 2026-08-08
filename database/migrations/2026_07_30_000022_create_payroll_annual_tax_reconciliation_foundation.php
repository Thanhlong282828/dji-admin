<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_adjustment_requests', function (Blueprint $t): void {
            $t->unsignedSmallInteger('tax_attribution_year')->nullable()->after('requested_effective_date');
            $t->index(['company_id', 'employee_id', 'tax_attribution_year', 'status'], 'pay_adj_tax_attr_idx');
        });

        Schema::create('payroll_annual_tax_reconciliations', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->unsignedSmallInteger('tax_year');
            $t->char('jurisdiction', 2);
            $t->char('currency', 3);
            $t->string('reference', 80);
            $t->unsignedInteger('version')->default(1);
            $t->string('active_key', 191)->nullable();
            $t->uuid('idempotency_key');
            $t->string('status', 24)->default('draft');
            $t->string('result_type', 24)->nullable();
            $t->json('taxpayer_snapshot');
            $t->json('company_tax_snapshot');
            $t->json('tax_policy_snapshot')->nullable();
            $t->unsignedInteger('calculation_version')->nullable();
            $t->string('checksum_version', 40)->default('annual-tax-source-v1');
            $t->char('source_checksum', 64)->nullable();
            foreach (['gross_income_minor', 'taxable_income_minor', 'non_taxable_income_minor', 'employee_insurance_deduction_minor', 'personal_deduction_minor', 'dependent_deduction_minor', 'other_deduction_minor', 'pit_withheld_minor', 'final_tax_liability_minor', 'additional_tax_payable_minor', 'tax_refund_minor'] as $column) {
                $t->unsignedBigInteger($column)->default(0);
            }
            $t->bigInteger('prior_tax_settlement_minor')->default(0);
            $t->foreignId('created_by');
            foreach (['calculated_by', 'submitted_by', 'reviewed_by', 'approved_by', 'finalized_by'] as $column) {
                $t->foreignId($column)->nullable();
            }
            foreach (['calculated_at', 'submitted_at', 'reviewed_at', 'approved_at', 'finalized_at', 'superseded_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
            $t->foreignId('supersedes_reconciliation_id')->nullable();
            $t->foreignId('superseded_by_reconciliation_id')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'reference'], 'patr_reference_uq');
            $t->unique(['company_id', 'idempotency_key'], 'patr_idempotency_uq');
            $t->unique(['company_id', 'active_key'], 'patr_active_uq');
            $t->unique(['company_id', 'employee_id', 'tax_year', 'version'], 'patr_version_uq');
            $t->index(['company_id', 'tax_year', 'status', 'employee_id'], 'patr_company_year_idx');
            $t->foreign('company_id', 'patr_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patr_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            foreach (['created_by', 'calculated_by', 'submitted_by', 'reviewed_by', 'approved_by', 'finalized_by'] as $column) {
                $t->foreign($column, 'patr_'.$column.'_fk')->references('id')->on('users')->{$column === 'created_by' ? 'restrictOnDelete' : 'nullOnDelete'}();
            }
            $t->foreign('supersedes_reconciliation_id', 'patr_supersedes_fk')->references('id')->on('payroll_annual_tax_reconciliations')->restrictOnDelete();
            $t->foreign('superseded_by_reconciliation_id', 'patr_superseded_fk')->references('id')->on('payroll_annual_tax_reconciliations')->restrictOnDelete();
        });

        Schema::create('payroll_annual_tax_sources', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('source_type', 40);
            $t->string('source_reference', 191);
            $t->unsignedSmallInteger('source_period_year');
            $t->unsignedTinyInteger('source_period_month')->nullable();
            $t->unsignedSmallInteger('tax_attribution_year');
            $t->foreignId('payroll_period_id')->nullable();
            $t->foreignId('payroll_run_id')->nullable();
            $t->foreignId('payroll_item_id')->nullable();
            $t->foreignId('payslip_id')->nullable();
            $t->foreignId('adjustment_request_id')->nullable();
            $t->foreignId('adjustment_run_id')->nullable();
            $t->unsignedInteger('source_calculation_version');
            $t->foreignId('source_tax_policy_id')->nullable();
            $t->unsignedInteger('source_tax_policy_version')->nullable();
            foreach (['gross_income_minor', 'taxable_income_minor', 'non_taxable_income_minor', 'employee_insurance_deduction_minor', 'personal_deduction_minor', 'dependent_deduction_minor', 'other_deduction_minor', 'pit_withheld_minor'] as $column) {
                $t->bigInteger($column)->default(0);
            }
            $t->char('currency', 3);
            $t->char('source_checksum', 64);
            $t->string('source_sort_key', 255);
            $t->timestamps();
            $t->unique(['annual_tax_reconciliation_id', 'source_type', 'source_reference'], 'pats_source_uq');
            $t->unique(['annual_tax_reconciliation_id', 'payroll_item_id'], 'pats_item_uq');
            $t->unique(['annual_tax_reconciliation_id', 'adjustment_run_id'], 'pats_adj_run_uq');
            $t->index(['company_id', 'employee_id', 'tax_attribution_year'], 'pats_attr_year_idx');
            $t->foreign('annual_tax_reconciliation_id', 'pats_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->cascadeOnDelete();
            $t->foreign('company_id', 'pats_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'pats_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('payroll_period_id', 'pats_period_fk')->references('id')->on('payroll_periods')->restrictOnDelete();
            $t->foreign('payroll_run_id', 'pats_run_fk')->references('id')->on('payroll_runs')->restrictOnDelete();
            $t->foreign('payroll_item_id', 'pats_item_fk')->references('id')->on('payroll_items')->restrictOnDelete();
            $t->foreign('payslip_id', 'pats_payslip_fk')->references('id')->on('payroll_payslips')->restrictOnDelete();
            $t->foreign('adjustment_request_id', 'pats_adj_req_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
            $t->foreign('adjustment_run_id', 'pats_adj_run_fk')->references('id')->on('payroll_adjustment_runs')->restrictOnDelete();
            $t->foreign('source_tax_policy_id', 'pats_tax_policy_fk')->references('id')->on('tax_policies')->restrictOnDelete();
        });

        Schema::create('payroll_annual_tax_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('annual_tax_source_id')->nullable();
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('category', 50);
            $t->string('component_code', 100);
            $t->string('description')->nullable();
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->unsignedInteger('source_calculation_version');
            $t->foreignId('payroll_item_component_id')->nullable();
            $t->foreignId('adjustment_component_id')->nullable();
            $t->json('metadata')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['annual_tax_reconciliation_id', 'category'], 'patc_recon_idx');
            $t->index(['annual_tax_source_id', 'sort_order'], 'patc_source_idx');
            $t->foreign('annual_tax_reconciliation_id', 'patc_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->cascadeOnDelete();
            $t->foreign('annual_tax_source_id', 'patc_source_fk')->references('id')->on('payroll_annual_tax_sources')->cascadeOnDelete();
            $t->foreign('company_id', 'patc_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patc_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('payroll_item_component_id', 'patc_item_comp_fk')->references('id')->on('payroll_item_components')->restrictOnDelete();
            $t->foreign('adjustment_component_id', 'patc_adj_comp_fk')->references('id')->on('payroll_adjustment_components')->restrictOnDelete();
        });

        Schema::create('payroll_annual_tax_validation_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('annual_tax_source_id')->nullable();
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('issue_code', 100);
            $t->string('severity', 20);
            $t->string('field_name', 100)->nullable();
            $t->string('source_reference', 191)->nullable();
            $t->string('safe_message', 1000);
            $t->json('metadata')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable();
            $t->timestamps();
            $t->index(['annual_tax_reconciliation_id', 'severity'], 'patvi_recon_sev_idx');
            $t->foreign('annual_tax_reconciliation_id', 'patvi_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->cascadeOnDelete();
            $t->foreign('annual_tax_source_id', 'patvi_source_fk')->references('id')->on('payroll_annual_tax_sources')->cascadeOnDelete();
            $t->foreign('company_id', 'patvi_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patvi_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('resolved_by', 'patvi_resolved_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('payroll_annual_tax_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->string('action', 50);
            $t->foreignId('actor_id')->nullable();
            $t->string('safe_reason', 1000)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['annual_tax_reconciliation_id', 'created_at'], 'path_recon_idx');
            $t->foreign('annual_tax_reconciliation_id', 'path_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->cascadeOnDelete();
            $t->foreign('company_id', 'path_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'path_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('actor_id', 'path_actor_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('payroll_annual_tax_documents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('document_type', 50);
            $t->unsignedInteger('document_version')->default(1);
            $t->boolean('is_current')->default(true);
            $t->string('storage_disk', 30)->nullable();
            $t->string('storage_path')->nullable();
            $t->string('file_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->char('checksum', 64)->nullable();
            $t->char('source_checksum', 64)->nullable();
            $t->foreignId('generated_by')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->unsignedInteger('generation_version')->nullable();
            $t->timestamps();
            $t->unique(['annual_tax_reconciliation_id', 'document_type', 'document_version'], 'patd_version_uq');
            $t->index(['annual_tax_reconciliation_id', 'document_type', 'is_current'], 'patd_recon_current_idx');
            $t->foreign('annual_tax_reconciliation_id', 'patd_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->restrictOnDelete();
            $t->foreign('company_id', 'patd_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patd_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('generated_by', 'patd_generated_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('payroll_annual_tax_downstream_intents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->string('intent_type', 50);
            $t->string('status', 30)->default('pending');
            $t->uuid('idempotency_key');
            $t->unsignedInteger('attempt_count')->default(0);
            foreach (['last_attempt_at', 'processing_started_at', 'completed_at', 'failed_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
            $t->string('failure_code', 100)->nullable();
            $t->string('failure_message', 1000)->nullable();
            $t->unsignedBigInteger('recovery_id')->nullable();
            $t->foreignId('payment_batch_item_id')->nullable();
            $t->foreignId('accounting_journal_id')->nullable();
            $t->foreignId('annual_tax_document_id')->nullable();
            $t->timestamps();
            $t->unique(['annual_tax_reconciliation_id', 'intent_type'], 'patdi_recon_type_uq');
            $t->unique(['company_id', 'idempotency_key'], 'patdi_idempotency_uq');
            $t->foreign('annual_tax_reconciliation_id', 'patdi_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->restrictOnDelete();
            $t->foreign('company_id', 'patdi_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patdi_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('payment_batch_item_id', 'patdi_payment_item_fk')->references('id')->on('payroll_payment_batch_items')->restrictOnDelete();
            $t->foreign('accounting_journal_id', 'patdi_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $t->foreign('annual_tax_document_id', 'patdi_document_fk')->references('id')->on('payroll_annual_tax_documents')->restrictOnDelete();
        });

        Schema::create('payroll_annual_tax_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('annual_tax_reconciliation_id');
            $t->foreignId('annual_tax_document_id')->nullable();
            $t->foreignId('company_id');
            $t->foreignId('employee_id');
            $t->foreignId('actor_id')->nullable();
            $t->string('action', 50);
            $t->string('result', 30);
            $t->char('ip_hash', 64)->nullable();
            $t->string('user_agent_summary', 255)->nullable();
            $t->timestamp('accessed_at')->useCurrent();
            $t->index(['annual_tax_reconciliation_id', 'action', 'accessed_at'], 'patal_recon_action_idx');
            $t->foreign('annual_tax_reconciliation_id', 'patal_recon_fk')->references('id')->on('payroll_annual_tax_reconciliations')->restrictOnDelete();
            $t->foreign('annual_tax_document_id', 'patal_document_fk')->references('id')->on('payroll_annual_tax_documents')->restrictOnDelete();
            $t->foreign('company_id', 'patal_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $t->foreign('employee_id', 'patal_employee_fk')->references('id')->on('employees')->restrictOnDelete();
            $t->foreign('actor_id', 'patal_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Annual PIT foundation is intentionally forward-only.');
    }
};
