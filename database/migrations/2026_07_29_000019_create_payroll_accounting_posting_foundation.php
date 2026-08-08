<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('company_id')->constrained(indexName: 'acct_company_fk')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('accounting_accounts', indexName: 'acct_parent_fk')->nullOnDelete();
            $t->string('code', 50);
            $t->string('name', 160);
            $t->string('account_type', 20);
            $t->string('normal_balance', 10);
            $t->char('currency', 3)->nullable();
            $t->boolean('allow_posting')->default(true);
            $t->string('status', 20)->default('active');
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->foreignId('created_by')->constrained('users', indexName: 'acct_creator_fk')->restrictOnDelete();
            $t->timestamps();
            $t->unique('uuid', 'acct_uuid_uq');
            $t->unique(['company_id', 'code'], 'acct_company_code_uq');
            $t->index(['company_id', 'status', 'effective_from', 'effective_to'], 'acct_company_status_idx');
        });
        Schema::create('payroll_accounting_policies', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('company_id')->constrained(indexName: 'pap_company_fk')->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name', 160);
            $t->unsignedInteger('version');
            $t->char('currency', 3);
            $t->string('aggregation_strategy', 50)->default('per_account_and_cost_center');
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('rounding_rule', 20)->default('half_up');
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->constrained('users', indexName: 'pap_creator_fk')->restrictOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique('uuid', 'pap_uuid_uq');
            $t->unique(['company_id', 'code', 'version'], 'pap_company_code_ver_uq');
            $t->index(['company_id', 'status', 'effective_from', 'effective_to'], 'pap_effective_idx');
        });
        Schema::create('payroll_accounting_mappings', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('company_id')->constrained(indexName: 'pam_company_fk')->restrictOnDelete();
            $t->foreignId('accounting_policy_id')->constrained('payroll_accounting_policies', indexName: 'pam_policy_fk')->restrictOnDelete();
            $t->string('source_type', 60);
            $t->string('source_code', 80);
            $t->foreignId('debit_account_id')->nullable()->constrained('accounting_accounts', indexName: 'pam_debit_fk')->restrictOnDelete();
            $t->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts', indexName: 'pam_credit_fk')->restrictOnDelete();
            $t->foreignId('cost_center_id')->nullable()->constrained(indexName: 'pam_cost_fk')->nullOnDelete();
            $t->foreignId('organization_unit_id')->nullable()->constrained(indexName: 'pam_org_fk')->nullOnDelete();
            $t->unsignedInteger('priority')->default(100);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('status', 20)->default('active');
            $t->foreignId('created_by')->constrained('users', indexName: 'pam_creator_fk')->restrictOnDelete();
            $t->timestamps();
            $t->unique('uuid', 'pam_uuid_uq');
            $t->index(['company_id', 'accounting_policy_id', 'source_type', 'source_code'], 'pam_policy_source_idx');
            $t->index(['company_id', 'status', 'effective_from', 'effective_to'], 'pam_effective_idx');
        });
        Schema::create('payroll_accounting_journals', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('company_id')->constrained(indexName: 'paj_company_fk')->restrictOnDelete();
            $t->foreignId('payroll_run_id')->constrained(indexName: 'paj_run_fk')->restrictOnDelete();
            $t->foreignId('payroll_period_id')->constrained(indexName: 'paj_period_fk')->restrictOnDelete();
            $t->foreignId('accounting_policy_id')->constrained('payroll_accounting_policies', indexName: 'paj_policy_fk')->restrictOnDelete();
            $t->unsignedInteger('policy_version');
            $t->string('journal_number', 80);
            $t->date('journal_date');
            $t->date('posting_date');
            $t->date('resolution_date');
            $t->char('currency', 3);
            $t->unsignedInteger('calculation_version');
            $t->string('status', 30)->default('draft');
            $t->unsignedBigInteger('total_debit_minor')->default(0);
            $t->unsignedBigInteger('total_credit_minor')->default(0);
            $t->unsignedInteger('line_count')->default(0);
            $t->string('active_intent_key', 100)->nullable();
            $t->uuid('idempotency_key');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users', indexName: 'paj_creator_fk')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'paj_reviewer_fk')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users', indexName: 'paj_approver_fk')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('posted_by')->nullable()->constrained('users', indexName: 'paj_poster_fk')->nullOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->foreignId('exported_by')->nullable()->constrained('users', indexName: 'paj_exporter_fk')->nullOnDelete();
            $t->timestamp('exported_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users', indexName: 'paj_rejecter_fk')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->string('rejection_reason', 1000)->nullable();
            $t->foreignId('reversal_of_journal_id')->nullable()->constrained('payroll_accounting_journals', indexName: 'paj_reversal_of_fk')->restrictOnDelete();
            $t->foreignId('reversed_by_journal_id')->nullable()->constrained('payroll_accounting_journals', indexName: 'paj_reversed_by_fk')->restrictOnDelete();
            $t->string('reversal_reason', 1000)->nullable();
            $t->string('export_format', 50)->nullable();
            $t->string('storage_disk', 30)->nullable();
            $t->string('storage_path')->nullable();
            $t->string('file_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->char('checksum', 64)->nullable();
            $t->timestamps();
            $t->unique('uuid', 'paj_uuid_uq');
            $t->unique(['company_id', 'journal_number'], 'paj_company_number_uq');
            $t->unique(['company_id', 'idempotency_key'], 'paj_company_idem_uq');
            $t->unique('active_intent_key', 'paj_run_calc_active_uq');
            $t->index(['company_id', 'status', 'posting_date'], 'paj_company_status_idx');
        });
        Schema::create('payroll_accounting_journal_lines', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('company_id')->constrained(indexName: 'pajl_company_fk')->restrictOnDelete();
            $t->foreignId('journal_id')->constrained('payroll_accounting_journals', indexName: 'pajl_journal_fk')->cascadeOnDelete();
            $t->unsignedInteger('line_number');
            $t->string('source_type', 60);
            $t->string('source_code', 80);
            $t->string('description', 255);
            $t->foreignId('account_id')->constrained('accounting_accounts', indexName: 'pajl_account_fk')->restrictOnDelete();
            $t->string('account_code_snapshot', 50);
            $t->string('account_name_snapshot', 160);
            $t->unsignedBigInteger('debit_minor')->default(0);
            $t->unsignedBigInteger('credit_minor')->default(0);
            $t->char('currency', 3);
            $t->foreignId('cost_center_id')->nullable()->constrained(indexName: 'pajl_cost_fk')->nullOnDelete();
            $t->string('cost_center_code_snapshot', 50)->nullable();
            $t->string('cost_center_name_snapshot', 160)->nullable();
            $t->foreignId('organization_unit_id')->nullable()->constrained(indexName: 'pajl_org_fk')->nullOnDelete();
            $t->string('organization_unit_code_snapshot', 50)->nullable();
            $t->string('organization_unit_name_snapshot', 160)->nullable();
            $t->foreignId('mapping_id')->constrained('payroll_accounting_mappings', indexName: 'pajl_mapping_fk')->restrictOnDelete();
            $t->json('mapping_snapshot')->nullable();
            $t->unsignedInteger('source_count')->default(0);
            $t->timestamps();
            $t->unique('uuid', 'pajl_uuid_uq');
            $t->unique(['journal_id', 'line_number'], 'pajl_journal_line_uq');
            $t->index(['company_id', 'account_id'], 'pajl_account_idx');
        });
        Schema::create('payroll_accounting_journal_sources', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained(indexName: 'pajs_company_fk')->restrictOnDelete();
            $t->foreignId('journal_id')->constrained('payroll_accounting_journals', indexName: 'pajs_journal_fk')->cascadeOnDelete();
            $t->foreignId('journal_line_id')->constrained('payroll_accounting_journal_lines', indexName: 'pajs_line_fk')->cascadeOnDelete();
            $t->foreignId('payroll_item_id')->constrained(indexName: 'pajs_item_fk')->restrictOnDelete();
            $t->foreignId('payroll_item_component_id')->nullable()->constrained(indexName: 'pajs_component_fk')->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained(indexName: 'pajs_employee_fk')->nullOnDelete();
            $t->unsignedInteger('calculation_version');
            $t->string('source_type', 60);
            $t->string('source_code', 80);
            $t->unsignedBigInteger('source_amount_minor');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['company_id', 'journal_line_id', 'payroll_item_id'], 'pajs_line_item_idx');
        });
        Schema::create('payroll_accounting_validation_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained(indexName: 'pavi_company_fk')->restrictOnDelete();
            $t->foreignId('journal_id')->constrained('payroll_accounting_journals', indexName: 'pavi_journal_fk')->cascadeOnDelete();
            $t->string('code', 100);
            $t->string('severity', 20);
            $t->string('message', 500);
            $t->boolean('is_blocking')->default(true);
            $t->json('safe_context')->nullable();
            $t->timestamps();
            $t->index(['journal_id', 'code', 'is_blocking'], 'pavi_journal_code_idx');
        });
        Schema::create('payroll_accounting_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained(indexName: 'paal_company_fk')->restrictOnDelete();
            $t->foreignId('journal_id')->constrained('payroll_accounting_journals', indexName: 'paal_journal_fk')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users', indexName: 'paal_actor_fk')->nullOnDelete();
            $t->string('action', 50);
            $t->timestamp('accessed_at');
            $t->index(['journal_id', 'action', 'accessed_at'], 'paal_journal_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_accounting_access_logs');
        Schema::dropIfExists('payroll_accounting_validation_issues');
        Schema::dropIfExists('payroll_accounting_journal_sources');
        Schema::dropIfExists('payroll_accounting_journal_lines');
        Schema::dropIfExists('payroll_accounting_journals');
        Schema::dropIfExists('payroll_accounting_mappings');
        Schema::dropIfExists('payroll_accounting_policies');
        Schema::dropIfExists('accounting_accounts');
    }
};
