<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_internal_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_treasury_account_id')->constrained('treasury_financial_accounts', 'id', 'treasury_transfer_source_account_fk')->restrictOnDelete();
            $table->foreignId('destination_treasury_account_id')->constrained('treasury_financial_accounts', 'id', 'treasury_transfer_destination_account_fk')->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->dateTime('occurred_at');
            $table->string('reference', 120);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('idempotency_key', 191);
            $table->foreignId('accounting_posting_policy_id')->nullable()->constrained('accounting_posting_policies', 'id', 'treasury_transfer_policy_fk')->restrictOnDelete();
            $table->unsignedInteger('accounting_posting_policy_version')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals', 'id', 'treasury_transfer_journal_fk')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users', 'id', 'treasury_transfer_creator_fk')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users', 'id', 'treasury_transfer_submitter_fk')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id', 'treasury_transfer_approver_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users', 'id', 'treasury_transfer_poster_fk')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'treasury_transfer_idempotency_unique');
            $table->index(['company_id', 'occurred_at', 'status'], 'treasury_transfer_lookup');
        });

        Schema::create('treasury_internal_transfer_legs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('treasury_internal_transfer_id')->constrained('treasury_internal_transfers', 'id', 'treasury_transfer_leg_header_fk')->restrictOnDelete();
            $table->foreignId('treasury_financial_account_id')->constrained('treasury_financial_accounts', 'id', 'treasury_transfer_leg_account_fk')->restrictOnDelete();
            $table->string('leg_role', 24);
            $table->string('direction', 12);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->foreignId('treasury_transaction_id')->nullable()->unique('treasury_transfer_leg_transaction_unique')->constrained('treasury_transactions', 'id', 'treasury_transfer_leg_transaction_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['treasury_internal_transfer_id', 'leg_role'], 'treasury_transfer_leg_role_unique');
            $table->index(['treasury_internal_transfer_id', 'direction'], 'treasury_transfer_leg_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_internal_transfer_legs');
        Schema::dropIfExists('treasury_internal_transfers');
    }
};
