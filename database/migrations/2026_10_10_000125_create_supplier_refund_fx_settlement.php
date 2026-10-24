<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_refund_fx_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_refund_receipt_id')->unique();
            $table->foreign('supplier_refund_receipt_id', 'srfx_receipt_fk')->references('id')->on('supplier_refund_receipts')->restrictOnDelete();
            $table->foreignId('vendor_refund_receivable_id');
            $table->foreign('vendor_refund_receivable_id', 'srfx_receivable_fk')->references('id')->on('vendor_refund_receivables')->restrictOnDelete();
            $table->foreignId('supplier_credit_fx_evidence_id');
            $table->foreign('supplier_credit_fx_evidence_id', 'srfx_credit_evidence_fk')->references('id')->on('supplier_credit_fx_evidence')->restrictOnDelete();
            $table->foreignId('settlement_fx_snapshot_id');
            $table->foreign('settlement_fx_snapshot_id', 'srfx_snapshot_fk')->references('id')->on('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('foreign_refunded_minor');
            $table->unsignedBigInteger('functional_cash_minor');
            $table->unsignedBigInteger('released_carrying_minor');
            $table->bigInteger('realized_fx_minor');
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 20);
            $table->string('source_reference', 160);
            $table->foreignId('accounting_policy_id');
            $table->foreign('accounting_policy_id', 'srfx_policy_fk')->references('id')->on('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id')->unique();
            $table->foreign('accounting_journal_id', 'srfx_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->unique();
            $table->foreign('treasury_transaction_id', 'srfx_treasury_fk')->references('id')->on('treasury_transactions')->restrictOnDelete();
            $table->char('checksum', 64);
            $table->foreignId('created_by');
            $table->foreign('created_by', 'srfx_creator_fk')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'vendor_refund_receivable_id'], 'supplier_refund_fx_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refund_fx_settlements');
    }
};
