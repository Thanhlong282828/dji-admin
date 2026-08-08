<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('bank_code', 30)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('branch_code', 30)->nullable();
            $table->text('account_number_encrypted')->nullable();
            $table->string('account_number_last4', 4)->nullable();
            $table->string('account_holder_name', 150);
            $table->char('currency', 3);
            $table->boolean('is_primary')->default(true);
            $table->string('verification_status', 30)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'payment_method', 'currency'], 'payment_account_scope_idx');
            $table->index(['company_id', 'status', 'verification_status', 'effective_from', 'effective_to'], 'payment_account_effective_idx');
        });
        Schema::create('payroll_payment_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 80);
            $table->char('currency', 3);
            $table->date('payment_date');
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('calculation_version');
            $table->unsignedInteger('employee_count')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->string('export_format', 30)->nullable();
            $table->string('storage_disk', 30)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->uuid('idempotency_key');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique(['company_id', 'batch_number']);
            $table->index(['company_id', 'status', 'payment_date']);
            $table->index(['company_id', 'payroll_run_id']);
        });
        Schema::create('payroll_payment_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_batch_id')->constrained('payroll_payment_batches')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('calculation_version');
            $table->string('active_intent_key', 100)->nullable()->unique();
            $table->string('payment_method', 30);
            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->string('beneficiary_name_snapshot', 150);
            $table->string('bank_code_snapshot', 30)->nullable();
            $table->string('bank_name_snapshot', 120)->nullable();
            $table->string('branch_code_snapshot', 30)->nullable();
            $table->text('account_number_ciphertext')->nullable();
            $table->string('account_number_last4', 4)->nullable();
            $table->string('payment_reference', 80);
            $table->string('status', 30)->default('pending');
            $table->string('validation_status', 30)->default('valid');
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('reconciliation_status', 30)->default('pending');
            $table->string('bank_transaction_reference', 120)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_batch_id', 'payment_reference'], 'payment_batch_reference_unique');
            $table->index(['company_id', 'payroll_item_id', 'calculation_version'], 'payment_item_source_idx');
        });
        Schema::create('payroll_payment_validation_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_batch_id')->constrained('payroll_payment_batches')->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 100);
            $table->string('severity', 20);
            $table->string('message', 500);
            $table->boolean('is_blocking')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'payment_batch_id', 'is_blocking'], 'payment_issue_scope_idx');
        });
        Schema::create('payroll_payment_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_batch_id')->constrained('payroll_payment_batches')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->char('ip_hash', 64)->nullable();
            $table->string('safe_metadata', 500)->nullable();
            $table->timestamp('accessed_at');
            $table->index(['company_id', 'payment_batch_id', 'accessed_at'], 'payment_access_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payment_access_logs');
        Schema::dropIfExists('payroll_payment_validation_issues');
        Schema::dropIfExists('payroll_payment_batch_items');
        Schema::dropIfExists('payroll_payment_batches');
        Schema::dropIfExists('employee_payment_accounts');
    }
};
