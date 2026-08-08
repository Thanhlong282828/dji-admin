<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_tax_filings', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('annual_tax_reconciliation_id')->constrained('payroll_annual_tax_reconciliations', indexName: 'gtf_recon_fk')->restrictOnDelete();
            $t->unsignedSmallInteger('tax_year');
            $t->string('jurisdiction', 10);
            $t->string('filing_type', 40);
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 24)->default('draft');
            $t->string('active_key', 191)->nullable();
            $t->uuid('idempotency_key');
            $t->char('source_checksum', 64);
            $t->char('payload_checksum', 64)->nullable();
            $t->json('header_snapshot');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('generated_at')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key'], 'gtf_idem_uq');
            $t->unique(['company_id', 'annual_tax_reconciliation_id', 'filing_type', 'version'], 'gtf_version_uq');
            $t->unique('active_key', 'gtf_active_uq');
            $t->index(['company_id', 'tax_year', 'status'], 'gtf_scope_idx');
        });

        Schema::create('government_tax_filing_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtfi_filing_fk')->cascadeOnDelete();
            $t->foreignId('annual_tax_reconciliation_id')->constrained('payroll_annual_tax_reconciliations', indexName: 'gtfi_recon_fk')->restrictOnDelete();
            $t->unsignedInteger('line_number');
            $t->json('employee_identity_snapshot');
            $t->json('income_snapshot');
            $t->json('deduction_snapshot');
            $t->json('tax_withheld_snapshot');
            $t->json('annual_result_snapshot');
            $t->char('item_checksum', 64);
            $t->timestamps();
            $t->unique(['government_tax_filing_id', 'line_number'], 'gtfi_line_uq');
            $t->index(['company_id', 'annual_tax_reconciliation_id'], 'gtfi_scope_idx');
        });

        Schema::create('government_tax_filing_documents', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtfd_filing_fk')->restrictOnDelete();
            $t->unsignedInteger('document_version');
            $t->string('storage_disk', 30);
            $t->string('storage_path');
            $t->string('file_name');
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('file_size');
            $t->char('checksum', 64);
            $t->char('source_checksum', 64);
            $t->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('generated_at');
            $t->timestamps();
            $t->unique(['government_tax_filing_id', 'document_version'], 'gtfd_version_uq');
            $t->index(['company_id', 'government_tax_filing_id'], 'gtfd_scope_idx');
        });

        Schema::create('government_tax_submission_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtsa_filing_fk')->cascadeOnDelete();
            $t->unsignedInteger('attempt_number');
            $t->uuid('idempotency_key');
            $t->char('payload_hash', 64);
            $t->char('request_hash', 64);
            $t->char('response_hash', 64)->nullable();
            $t->string('status', 30);
            $t->string('error_code', 100)->nullable();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('submitted_at');
            $t->timestamps();
            $t->unique(['government_tax_filing_id', 'attempt_number'], 'gtsa_attempt_uq');
            $t->unique(['company_id', 'idempotency_key'], 'gtsa_idem_uq');
        });

        Schema::create('government_tax_validation_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtvi_filing_fk')->cascadeOnDelete();
            $t->string('issue_code', 100);
            $t->string('severity', 20);
            $t->string('field_name', 100)->nullable();
            $t->string('safe_message', 500);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['government_tax_filing_id', 'severity', 'resolved_at'], 'gtvi_scope_idx');
        });

        Schema::create('government_tax_filing_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtfl_filing_fk')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24)->nullable();
            $t->string('safe_message', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['government_tax_filing_id', 'created_at'], 'gtfl_history_idx');
        });

        Schema::create('government_tax_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('government_tax_filing_id')->constrained('government_tax_filings', indexName: 'gtal_filing_fk')->restrictOnDelete();
            $t->foreignId('government_tax_filing_document_id')->nullable()->constrained('government_tax_filing_documents', indexName: 'gtal_document_fk')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('result', 30);
            $t->timestamp('accessed_at')->useCurrent();
            $t->index(['government_tax_filing_id', 'action', 'accessed_at'], 'gtal_scope_idx');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Government Filing foundation is intentionally forward-only.');
    }
};
