<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->char('functional_currency_code', 3)->nullable()->after('currency_code');
            $table->index('functional_currency_code', 'companies_functional_currency_idx');
        });

        Schema::create('finance_fx_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 32);
            $table->string('source_reference', 180);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->uuid('idempotency_key');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'base_currency', 'quote_currency', 'rate_type', 'version'], 'fx_rate_pair_version_unique');
            $table->unique(['company_id', 'idempotency_key'], 'fx_rate_idempotency_unique');
            $table->index(['company_id', 'base_currency', 'quote_currency', 'rate_type', 'status', 'effective_from'], 'fx_rate_resolution_idx');
        });

        Schema::create('finance_fx_rate_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fx_rate_id')->constrained('finance_fx_rates')->restrictOnDelete();
            $table->unsignedInteger('rate_version');
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 32);
            $table->string('source_reference', 180);
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->char('checksum', 64);
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['company_id', 'source_type', 'source_id', 'rate_type'], 'fx_snapshot_source_unique');
            $table->index(['company_id', 'fx_rate_id', 'rate_version'], 'fx_snapshot_rate_idx');
        });

        Schema::create('finance_fx_revaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->date('as_of_date');
            $table->char('functional_currency', 3);
            $table->string('status', 20)->default('draft');
            $table->uuid('idempotency_key');
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('reversal_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'fx_revaluation_idempotency_unique');
            $table->index(['company_id', 'accounting_period_id', 'as_of_date', 'status'], 'fx_revaluation_scope_idx');
        });

        Schema::create('finance_fx_revaluation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fx_revaluation_run_id')->constrained('finance_fx_revaluation_runs')->restrictOnDelete();
            $table->foreignId('fx_rate_snapshot_id')->constrained('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->char('transaction_currency', 3);
            $table->bigInteger('foreign_outstanding_minor');
            $table->bigInteger('carrying_functional_minor');
            $table->bigInteger('revalued_functional_minor');
            $table->bigInteger('fx_difference_minor');
            $table->foreignId('accounting_policy_id')->nullable()->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('policy_version')->nullable();
            $table->json('mapping_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['fx_revaluation_run_id', 'source_type', 'source_id'], 'fx_revaluation_source_unique');
            $table->index(['company_id', 'transaction_currency', 'source_type'], 'fx_revaluation_line_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fx_revaluation_lines');
        Schema::dropIfExists('finance_fx_revaluation_runs');
        Schema::dropIfExists('finance_fx_rate_snapshots');
        Schema::dropIfExists('finance_fx_rates');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex('companies_functional_currency_idx');
            $table->dropColumn('functional_currency_code');
        });
    }
};
