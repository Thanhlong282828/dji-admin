<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_adjustment_reasons', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 60);
            $t->string('name');
            $t->string('category', 60);
            $t->boolean('requires_evidence')->default(false);
            $t->boolean('allows_positive')->default(true);
            $t->boolean('allows_negative')->default(true);
            $t->string('status', 20)->default('active');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'code'], 'pay_adj_reason_company_code_uq');
            $t->index(['company_id', 'status'], 'pay_adj_reason_scope_idx');
        });
        Schema::create('payroll_adjustment_requests', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('adjustment_number', 80);
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('source_payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $t->foreignId('source_payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $t->foreignId('source_payroll_item_id')->constrained('payroll_items')->restrictOnDelete();
            $t->unsignedInteger('source_calculation_version');
            $t->foreignId('adjustment_reason_id')->constrained('payroll_adjustment_reasons')->restrictOnDelete();
            $t->string('adjustment_type', 50);
            $t->date('requested_effective_date');
            $t->foreignId('target_payment_period_id')->nullable()->constrained('payroll_periods')->restrictOnDelete();
            $t->string('status', 24)->default('draft');
            $t->text('description');
            $t->unsignedBigInteger('evidence_document_id')->nullable();
            $t->uuid('idempotency_key');
            $t->string('active_intent_key', 191)->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('applied_at')->nullable();
            $t->foreignId('supersedes_adjustment_request_id')->nullable();
            $t->foreignId('superseded_by_adjustment_request_id')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'adjustment_number'], 'pay_adj_number_uq');
            $t->unique(['company_id', 'idempotency_key'], 'pay_adj_idempotency_uq');
            $t->unique(['company_id', 'active_intent_key'], 'pay_adj_active_intent_uq');
            $t->index(['company_id', 'employee_id', 'status'], 'pay_adj_employee_status_idx');
            $t->index(['source_payroll_item_id', 'status'], 'pay_adj_source_status_idx');
            $t->foreign('supersedes_adjustment_request_id', 'pay_adj_supersedes_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
            $t->foreign('superseded_by_adjustment_request_id', 'pay_adj_superseded_by_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
        });
        Schema::create('payroll_adjustment_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->string('snapshot_type', 30);
            $t->unsignedSmallInteger('schema_version')->default(1);
            $t->json('snapshot_payload');
            $t->char('payload_checksum', 64);
            $t->timestamp('captured_at');
            $t->timestamps();
            $t->unique(['adjustment_request_id', 'snapshot_type'], 'pay_adj_snapshot_type_uq');
            $t->foreign('adjustment_request_id', 'pay_adj_snapshot_request_fk')->references('id')->on('payroll_adjustment_requests')->cascadeOnDelete();
        });
        Schema::create('payroll_adjustment_components', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->unsignedBigInteger('source_component_id')->nullable();
            $t->string('component_code', 80);
            $t->string('component_type', 40);
            $t->bigInteger('historical_amount_minor');
            $t->bigInteger('corrected_amount_minor');
            $t->bigInteger('delta_amount_minor');
            $t->char('currency', 3);
            $t->boolean('taxable')->default(false);
            $t->boolean('insurable')->default(false);
            $t->boolean('employer_cost')->default(false);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['adjustment_request_id', 'component_code'], 'pay_adj_component_code_uq');
            $t->index(['company_id', 'adjustment_request_id'], 'pay_adj_component_scope_idx');
            $t->foreign('adjustment_request_id', 'pay_adj_component_request_fk')->references('id')->on('payroll_adjustment_requests')->cascadeOnDelete();
        });
        Schema::create('payroll_adjustment_validation_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->string('severity', 20);
            $t->string('code', 100);
            $t->string('safe_message', 1000);
            $t->boolean('is_blocking')->default(true);
            $t->json('metadata')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'adjustment_request_id', 'is_blocking'], 'pay_adj_issue_scope_idx');
            $t->foreign('adjustment_request_id', 'pay_adj_issue_request_fk')->references('id')->on('payroll_adjustment_requests')->cascadeOnDelete();
        });
        Schema::create('payroll_adjustment_recoveries', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->bigInteger('outstanding_amount_minor');
            $t->bigInteger('recovered_amount_minor')->default(0);
            $t->bigInteger('remaining_amount_minor');
            $t->char('currency', 3);
            $t->string('recovery_method', 40)->default('future_payroll_deduction');
            $t->string('status', 20)->default('outstanding');
            $t->string('active_intent_key', 191)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'active_intent_key'], 'pay_adj_recovery_intent_uq');
            $t->foreign('adjustment_request_id', 'pay_adj_recovery_request_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
        });
        Schema::create('payroll_adjustment_runs', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->foreignId('source_payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $t->foreignId('source_payroll_item_id')->constrained('payroll_items')->restrictOnDelete();
            $t->unsignedInteger('source_calculation_version');
            $t->unsignedInteger('delta_calculation_version')->default(1);
            $t->bigInteger('delta_gross_minor');
            $t->bigInteger('delta_employee_insurance_minor');
            $t->bigInteger('delta_employer_insurance_minor');
            $t->bigInteger('delta_personal_income_tax_minor');
            $t->bigInteger('delta_net_minor');
            $t->char('currency', 3);
            $t->string('status', 20)->default('applied');
            $t->timestamps();
            $t->unique('adjustment_request_id', 'pay_adj_run_request_uq');
            $t->foreign('adjustment_request_id', 'pay_adj_run_request_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
        });
        Schema::create('payroll_adjustment_payslips', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->foreignId('source_payroll_item_id')->constrained('payroll_items')->restrictOnDelete();
            $t->foreignId('source_payslip_id')->nullable()->constrained('payroll_payslips')->restrictOnDelete();
            $t->foreignId('source_payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $t->foreignId('payment_period_id')->nullable()->constrained('payroll_periods')->restrictOnDelete();
            $t->string('document_type', 40)->default('adjustment_statement');
            $t->json('snapshot_payload');
            $t->char('payload_checksum', 64);
            $t->string('status', 20)->default('generated');
            $t->timestamps();
            $t->unique('adjustment_request_id', 'pay_adj_payslip_request_uq');
            $t->foreign('adjustment_request_id', 'pay_adj_payslip_request_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
        });
        Schema::create('payroll_adjustment_downstream_intents', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->string('intent_type', 30);
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->string('status', 30);
            $t->unsignedBigInteger('downstream_record_id')->nullable();
            $t->json('source_trace');
            $t->timestamps();
            $t->unique(['adjustment_request_id', 'intent_type'], 'pay_adj_downstream_intent_uq');
            $t->foreign('adjustment_request_id', 'pay_adj_downstream_request_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
        });
        Schema::create('payroll_adjustment_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->string('action', 40);
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('safe_reason')->nullable();
            $t->unsignedInteger('version_before');
            $t->unsignedInteger('version_after');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['adjustment_request_id', 'created_at'], 'pay_adj_history_request_idx');
            $t->foreign('adjustment_request_id', 'pay_adj_history_request_fk')->references('id')->on('payroll_adjustment_requests')->cascadeOnDelete();
        });
        Schema::create('payroll_adjustment_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('adjustment_request_id');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->timestamp('accessed_at')->useCurrent();
            $t->foreign('adjustment_request_id', 'pay_adj_access_request_fk')->references('id')->on('payroll_adjustment_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Payroll adjustment foundation is intentionally forward-only.');
    }
};
