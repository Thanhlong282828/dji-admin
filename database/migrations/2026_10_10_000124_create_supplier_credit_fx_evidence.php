<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credit_fx_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_credit_note_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_invoice_fx_recognition_id');
            $table->foreign('supplier_invoice_fx_recognition_id', 'scfx_recognition_fk')->references('id')->on('supplier_invoice_fx_recognitions')->restrictOnDelete();
            $table->foreignId('goods_return_id')->constrained('procurement_goods_returns')->restrictOnDelete();
            $table->foreignId('inventory_return_fx_reversal_id');
            $table->foreign('inventory_return_fx_reversal_id', 'scfx_return_reversal_fk')->references('id')->on('inventory_return_fx_reversals')->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->unsignedBigInteger('foreign_credit_minor');
            $table->unsignedBigInteger('cumulative_foreign_credit_minor');
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('ap_carrying_release_minor');
            $table->unsignedBigInteger('cumulative_ap_carrying_release_minor');
            $table->unsignedBigInteger('supplier_receivable_functional_minor')->default(0);
            $table->bigInteger('purchase_price_variance_minor')->default(0);
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id')->unique()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'branch_id'], 'supplier_credit_fx_scope_idx');
            $table->index(['supplier_invoice_fx_recognition_id', 'cumulative_foreign_credit_minor'], 'supplier_credit_fx_cumulative_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_fx_evidence');
    }
};
