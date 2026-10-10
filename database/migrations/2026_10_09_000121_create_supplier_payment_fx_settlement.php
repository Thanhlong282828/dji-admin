<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_fx_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('ap_payment_id')->constrained('ap_payments')->restrictOnDelete();
            $table->foreignId('ap_payment_allocation_id')->unique();
            $table->foreign('ap_payment_allocation_id', 'spfx_allocation_fk')->references('id')->on('ap_payment_allocations')->restrictOnDelete();
            $table->foreignId('supplier_invoice_fx_recognition_id');
            $table->foreign('supplier_invoice_fx_recognition_id', 'spfx_recognition_fk')->references('id')->on('supplier_invoice_fx_recognitions')->restrictOnDelete();
            $table->foreignId('settlement_fx_snapshot_id')->nullable();
            $table->foreign('settlement_fx_snapshot_id', 'spfx_snapshot_fk')->references('id')->on('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->foreignId('treasury_financial_account_id');
            $table->foreign('treasury_financial_account_id', 'spfx_treasury_account_fk')->references('id')->on('treasury_financial_accounts')->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('foreign_settled_minor');
            $table->unsignedBigInteger('settlement_functional_minor');
            $table->unsignedBigInteger('released_carrying_minor');
            $table->bigInteger('realized_fx_minor');
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 20);
            $table->string('source_reference', 160);
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id');
            $table->foreign('accounting_journal_id', 'spfx_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id');
            $table->foreign('treasury_transaction_id', 'spfx_treasury_tx_fk')->references('id')->on('treasury_transactions')->restrictOnDelete();
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'supplier_invoice_fx_recognition_id'], 'supplier_payment_fx_company_recognition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_fx_settlements');
    }
};
