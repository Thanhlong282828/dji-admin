<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_fx_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->unique()->constrained('vendor_bills')->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->unsignedBigInteger('transaction_amount_minor');
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('functional_amount_minor');
            $table->foreignId('fx_rate_snapshot_id')->nullable()->constrained('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 32);
            $table->string('source_reference', 180);
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id')->unique()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'transaction_currency'], 'ap_fx_recognition_transaction_idx');
            $table->index(['company_id', 'functional_currency'], 'ap_fx_recognition_functional_idx');
            $table->index(['company_id', 'branch_id'], 'ap_fx_recognition_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_fx_recognitions');
    }
};
