<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->string('type', 30);
            $table->char('currency', 3)->default('VND');
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('treasury_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->constrained('treasury_financial_accounts')->restrictOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->string('direction', 10);
            $table->string('reference', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('VND');
            $table->dateTime('occurred_at');
            $table->string('status', 20)->default('recorded');
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'financial_account_id', 'occurred_at'], 'treasury_tx_scope_date_idx');
            $table->unique(['company_id', 'source_type', 'source_id'], 'treasury_tx_source_unique');
        });

        Schema::create('treasury_reconciliation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained('treasury_financial_accounts')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['financial_account_id', 'period_from', 'period_to'], 'treasury_recon_period_unique');
        });

        Schema::create('treasury_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('reconciliation_session_id')->constrained('treasury_reconciliation_sessions')->cascadeOnDelete();
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->restrictOnDelete();
            $table->string('external_reference', 120);
            $table->dateTime('occurred_at');
            $table->bigInteger('amount_minor');
            $table->string('status', 20)->default('unmatched');
            $table->text('ignore_reason')->nullable();
            $table->timestamps();
            $table->unique(['reconciliation_session_id', 'external_reference'], 'treasury_statement_reference_unique');
            $table->index(['company_id', 'status']);
        });

        Schema::create('accounting_period_close_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'created_at'], 'period_close_request_inbox_idx');
        });

        Schema::create('accounting_period_close_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('close_request_id')->constrained('accounting_period_close_requests')->cascadeOnDelete();
            $table->string('check_key', 80);
            $table->string('status', 20);
            $table->unsignedInteger('finding_count')->default(0);
            $table->string('summary', 255);
            $table->timestamps();
            $table->unique(['close_request_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_close_checks');
        Schema::dropIfExists('accounting_period_close_requests');
        Schema::dropIfExists('treasury_statement_lines');
        Schema::dropIfExists('treasury_reconciliation_sessions');
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('treasury_financial_accounts');
    }
};
