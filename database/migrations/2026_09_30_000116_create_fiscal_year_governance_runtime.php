<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'fiscal_year_company_name_unique');
            $table->index(['company_id', 'start_date', 'end_date'], 'fiscal_year_company_dates_idx');
            $table->index(['company_id', 'status'], 'fiscal_year_company_status_idx');
        });

        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->foreignId('fiscal_year_id')->nullable()->after('company_id');
            $table->foreign('fiscal_year_id', 'accounting_period_fiscal_year_fk')->references('id')->on('accounting_fiscal_years')->restrictOnDelete();
            $table->index(['company_id', 'fiscal_year_id', 'start_date', 'end_date'], 'accounting_period_fiscal_scope_idx');
        });

        Schema::create('accounting_fiscal_year_close_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreign('company_id', 'fiscal_year_close_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreignId('fiscal_year_id');
            $table->foreign('fiscal_year_id', 'fiscal_year_close_year_fk')->references('id')->on('accounting_fiscal_years')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3);
            $table->bigInteger('revenue_minor')->default(0);
            $table->bigInteger('expense_minor')->default(0);
            $table->bigInteger('net_income_minor')->default(0);
            $table->foreignId('retained_earnings_account_id');
            $table->foreign('retained_earnings_account_id', 'fiscal_year_close_retained_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreignId('closing_journal_id')->nullable();
            $table->foreign('closing_journal_id', 'fiscal_year_close_closing_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('opening_journal_id')->nullable();
            $table->foreign('opening_journal_id', 'fiscal_year_close_opening_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'fiscal_year_id'], 'fiscal_year_single_close_unique');
            $table->unique(['company_id', 'idempotency_key'], 'fiscal_year_close_idempotency_unique');
            $table->index(['company_id', 'status'], 'fiscal_year_close_status_idx');
        });

        Schema::create('accounting_opening_balance_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreign('company_id', 'opening_balance_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreignId('fiscal_year_close_request_id');
            $table->foreign('fiscal_year_close_request_id', 'opening_balance_close_request_fk')->references('id')->on('accounting_fiscal_year_close_requests')->restrictOnDelete();
            $table->foreignId('source_fiscal_year_id');
            $table->foreign('source_fiscal_year_id', 'opening_balance_source_year_fk')->references('id')->on('accounting_fiscal_years')->restrictOnDelete();
            $table->foreignId('target_fiscal_year_id');
            $table->foreign('target_fiscal_year_id', 'opening_balance_target_year_fk')->references('id')->on('accounting_fiscal_years')->restrictOnDelete();
            $table->foreignId('account_id');
            $table->foreign('account_id', 'opening_balance_account_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->foreignId('source_journal_id')->nullable();
            $table->foreign('source_journal_id', 'opening_balance_source_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('opening_journal_id')->nullable();
            $table->foreign('opening_journal_id', 'opening_balance_opening_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['target_fiscal_year_id', 'account_id', 'currency'], 'opening_balance_target_account_currency_unique');
            $table->index(['company_id', 'target_fiscal_year_id'], 'opening_balance_tenant_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_opening_balance_lines');
        Schema::dropIfExists('accounting_fiscal_year_close_requests');
        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->dropForeign('accounting_period_fiscal_year_fk');
            $table->dropIndex('accounting_period_fiscal_scope_idx');
            $table->dropColumn('fiscal_year_id');
        });
        Schema::dropIfExists('accounting_fiscal_years');
    }
};
