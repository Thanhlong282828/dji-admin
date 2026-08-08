<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_annual_tax_refund_obligations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained(indexName: 'patro_company_fk')->restrictOnDelete();
            $table->foreignId('annual_tax_reconciliation_id')->constrained('payroll_annual_tax_reconciliations', indexName: 'patro_recon_fk')->restrictOnDelete();
            $table->unsignedInteger('calculation_version');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 30)->default('eligible');
            $table->foreignId('created_by')->constrained('users', indexName: 'patro_creator_fk')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['annual_tax_reconciliation_id', 'calculation_version'], 'patro_recon_calc_uq');
        });

        Schema::create('payroll_annual_tax_settlement_journals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained(indexName: 'patsj_company_fk')->restrictOnDelete();
            $table->foreignId('annual_tax_reconciliation_id')->constrained('payroll_annual_tax_reconciliations', indexName: 'patsj_recon_fk')->restrictOnDelete();
            $table->unsignedInteger('calculation_version');
            $table->string('source_type', 50)->default('annual_tax_reconciliation');
            $table->json('component_snapshot');
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_debit_minor');
            $table->unsignedBigInteger('total_credit_minor');
            $table->string('status', 30)->default('created');
            $table->foreignId('created_by')->constrained('users', indexName: 'patsj_creator_fk')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['annual_tax_reconciliation_id', 'calculation_version'], 'patsj_recon_calc_uq');
            $table->index(['company_id', 'status'], 'patsj_company_status_idx');
        });

        Schema::create('payroll_annual_tax_settlement_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained(indexName: 'patsjl_company_fk')->restrictOnDelete();
            $table->foreignId('settlement_journal_id')->constrained('payroll_annual_tax_settlement_journals', indexName: 'patsjl_journal_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('line_number');
            $table->string('account_role', 50);
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->char('currency', 3);
            $table->json('source_trace');
            $table->timestamps();
            $table->unique(['settlement_journal_id', 'line_number'], 'patsjl_line_uq');
        });

        Schema::table('payroll_annual_tax_downstream_intents', function (Blueprint $table): void {
            $table->foreignId('refund_obligation_id')->nullable()->after('recovery_id');
            $table->foreignId('settlement_journal_id')->nullable()->after('accounting_journal_id');
            $table->foreign('refund_obligation_id', 'patdi_refund_fk')->references('id')->on('payroll_annual_tax_refund_obligations')->restrictOnDelete();
            $table->foreign('settlement_journal_id', 'patdi_settlement_fk')->references('id')->on('payroll_annual_tax_settlement_journals')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Annual Tax delivery foundation is intentionally forward-only.');
    }
};
