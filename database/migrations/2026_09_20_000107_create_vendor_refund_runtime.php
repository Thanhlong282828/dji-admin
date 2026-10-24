<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_credit_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('ap_reduction_minor')->default(0)->after('total_minor');
            $table->unsignedBigInteger('vendor_receivable_minor')->default(0)->after('ap_reduction_minor');
        });

        Schema::create('vendor_refund_receivables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('supplier_credit_note_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_return_id')->constrained('procurement_goods_returns')->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('original_minor');
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->string('status', 30)->default('open');
            $table->date('effective_date');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
            $table->index(['company_id', 'vendor_id', 'status'], 'vendor_refund_receivable_scope_idx');
            $table->index(['vendor_bill_id', 'effective_date'], 'vendor_refund_receivable_bill_idx');
        });

        Schema::create('supplier_refund_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('vendor_refund_receivable_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained('treasury_financial_accounts')->restrictOnDelete();
            $table->string('reference', 120);
            $table->date('refund_date');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 30)->default('draft');
            $table->string('idempotency_key', 191);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'supplier_refund_company_reference_uq');
            $table->unique(['company_id', 'idempotency_key'], 'supplier_refund_company_idem_uq');
            $table->index(['vendor_refund_receivable_id', 'status'], 'supplier_refund_receivable_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refund_receipts');
        Schema::dropIfExists('vendor_refund_receivables');
        Schema::table('supplier_credit_notes', function (Blueprint $table): void {
            $table->dropColumn(['ap_reduction_minor', 'vendor_receivable_minor']);
        });
    }
};
