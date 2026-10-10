<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained('treasury_financial_accounts')->restrictOnDelete();
            $table->string('reference', 120);
            $table->date('refund_date');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('draft');
            $table->string('idempotency_key', 191);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('accounting_policy_id')->nullable()->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'customer_refund_company_reference_uq');
            $table->unique(['company_id', 'idempotency_key'], 'customer_refund_company_idem_uq');
            $table->index(['company_id', 'customer_id', 'status'], 'customer_refund_customer_status_idx');
            $table->index(['company_id', 'branch_id', 'refund_date'], 'customer_refund_scope_date_idx');
        });

        Schema::create('customer_refund_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_credit_note_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('allocated_minor');
            $table->timestamps();
            $table->unique(['customer_refund_id', 'customer_credit_note_id'], 'customer_refund_allocation_source_uq');
            $table->index(['customer_credit_note_id', 'customer_refund_id'], 'customer_refund_allocation_credit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refund_allocations');
        Schema::dropIfExists('customer_refunds');
    }
};
