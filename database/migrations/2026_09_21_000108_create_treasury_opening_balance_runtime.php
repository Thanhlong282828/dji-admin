<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('treasury_financial_account_id')->constrained('treasury_financial_accounts', 'id', 'treasury_opening_account_fk')->restrictOnDelete();
            $table->foreignId('supersedes_opening_balance_id')->nullable()->constrained('treasury_opening_balances', 'id', 'treasury_opening_supersedes_fk')->restrictOnDelete();
            $table->char('currency', 3);
            $table->date('effective_date');
            $table->unsignedBigInteger('opening_amount_minor');
            $table->bigInteger('posting_adjustment_minor')->nullable();
            $table->string('reference', 120);
            $table->text('source_note')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('accounting_posting_policy_id')->nullable()->constrained('accounting_posting_policies', 'id', 'treasury_opening_policy_fk')->restrictOnDelete();
            $table->unsignedInteger('accounting_posting_policy_version')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals', 'id', 'treasury_opening_journal_fk')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users', 'id', 'treasury_opening_creator_fk')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users', 'id', 'treasury_opening_submitter_fk')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id', 'treasury_opening_approver_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users', 'id', 'treasury_opening_poster_fk')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'treasury_financial_account_id', 'currency', 'effective_date'], 'treasury_opening_basis_unique');
            $table->unique('supersedes_opening_balance_id', 'treasury_opening_supersedes_unique');
            $table->index(['company_id', 'currency', 'effective_date', 'status'], 'treasury_opening_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_opening_balances');
    }
};
