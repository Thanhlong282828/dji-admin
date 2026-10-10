<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('commerce_customers')->restrictOnDelete();
            $table->foreignId('customer_invoice_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 120);
            $table->date('effective_date');
            $table->char('currency', 3);
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('net_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('ar_reduction_minor')->default(0);
            $table->unsignedBigInteger('customer_credit_liability_minor')->default(0);
            $table->foreignId('accounting_policy_id')->nullable()->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'credit_note_number'], 'customer_credit_note_number_uq');
            $table->unique(['company_id', 'idempotency_key'], 'customer_credit_note_idempotency_uq');
            $table->index(['company_id', 'customer_invoice_id', 'status'], 'customer_credit_note_invoice_status_idx');
            $table->index(['company_id', 'customer_id', 'effective_date'], 'customer_credit_note_customer_date_idx');
        });

        Schema::create('customer_credit_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_invoice_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('description');
            $table->unsignedBigInteger('net_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
            $table->unique(['customer_credit_note_id', 'customer_invoice_line_id'], 'customer_credit_note_line_source_uq');
            $table->index(['customer_invoice_line_id', 'customer_credit_note_id'], 'customer_credit_note_line_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_note_lines');
        Schema::dropIfExists('customer_credit_notes');
    }
};
