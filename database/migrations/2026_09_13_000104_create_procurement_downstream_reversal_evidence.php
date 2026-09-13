<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_reversal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('ap_payment_id')->constrained('ap_payments')->restrictOnDelete();
            $table->foreignId('target_accounting_period_id');
            $table->foreign('target_accounting_period_id', 'ap_pay_rev_period_fk')->references('id')->on('accounting_periods')->restrictOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->text('reason');
            $table->string('idempotency_key', 120);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->foreignId('accounting_adjustment_id')->nullable();
            $table->foreign('accounting_adjustment_id', 'ap_pay_rev_adjustment_fk')->references('id')->on('accounting_adjustment_requests')->restrictOnDelete();
            $table->foreignId('reversal_journal_id')->nullable();
            $table->foreign('reversal_journal_id', 'ap_pay_rev_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('treasury_reversal_transaction_id')->nullable();
            $table->foreign('treasury_reversal_transaction_id', 'ap_pay_rev_treasury_fk')->references('id')->on('treasury_transactions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'ap_payment_reversal_key_unique');
            $table->unique('ap_payment_id', 'ap_payment_single_reversal_unique');
            $table->index(['company_id', 'status', 'effective_at'], 'ap_payment_reversal_scope_idx');
            $table->index(['ap_payment_id', 'status'], 'ap_payment_reversal_payment_idx');
        });

        Schema::create('ap_payment_allocation_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ap_payment_allocation_id')->constrained('ap_payment_allocations')->restrictOnDelete();
            $table->foreignId('payment_reversal_request_id');
            $table->foreign('payment_reversal_request_id', 'ap_alloc_rev_request_fk')->references('id')->on('ap_payment_reversal_requests')->restrictOnDelete();
            $table->unsignedBigInteger('reversed_minor');
            $table->timestamp('effective_at');
            $table->timestamps();
            $table->unique('ap_payment_allocation_id', 'ap_allocation_single_reversal_unique');
            $table->unique(['payment_reversal_request_id', 'ap_payment_allocation_id'], 'ap_allocation_reversal_source_unique');
            $table->index('effective_at', 'ap_allocation_reversal_effective_idx');
        });

        Schema::create('vendor_bill_reversal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_accounting_period_id');
            $table->foreign('target_accounting_period_id', 'vendor_bill_rev_period_fk')->references('id')->on('accounting_periods')->restrictOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->text('reason');
            $table->string('idempotency_key', 120);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->foreignId('accounting_adjustment_id')->nullable();
            $table->foreign('accounting_adjustment_id', 'vendor_bill_rev_adjustment_fk')->references('id')->on('accounting_adjustment_requests')->restrictOnDelete();
            $table->foreignId('reversal_journal_id')->nullable();
            $table->foreign('reversal_journal_id', 'vendor_bill_rev_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'vendor_bill_reversal_key_unique');
            $table->unique('vendor_bill_id', 'vendor_bill_single_reversal_unique');
            $table->index(['company_id', 'status', 'effective_at'], 'vendor_bill_reversal_scope_idx');
            $table->index(['vendor_bill_id', 'status'], 'vendor_bill_reversal_bill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_allocation_reversals');
        Schema::dropIfExists('vendor_bill_reversal_requests');
        Schema::dropIfExists('ap_payment_reversal_requests');
    }
};
