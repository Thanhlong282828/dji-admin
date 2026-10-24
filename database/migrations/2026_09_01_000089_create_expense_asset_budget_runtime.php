<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('finance_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained('finance_expense_categories')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('procurement_vendors')->nullOnDelete();
            $table->foreignId('attachment_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('reference', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('VND');
            $table->date('expense_date');
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('unpaid');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->nullOnDelete();
            $table->foreignId('payment_journal_id')->nullable()->constrained('payroll_accounting_journals')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'expense_date', 'status']);
        });
        Schema::create('finance_asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('finance_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_category_id')->constrained('finance_asset_categories')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('procurement_vendors')->nullOnDelete();
            $table->string('asset_code', 80);
            $table->string('name');
            $table->unsignedBigInteger('acquisition_cost_minor');
            $table->unsignedBigInteger('residual_value_minor')->default(0);
            $table->char('currency', 3)->default('VND');
            $table->date('acquisition_date');
            $table->date('in_service_date')->nullable();
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('acquisition_journal_id')->nullable()->constrained('payroll_accounting_journals')->nullOnDelete();
            $table->foreignId('disposal_journal_id')->nullable()->constrained('payroll_accounting_journals')->nullOnDelete();
            $table->date('disposed_on')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'asset_code']);
        });
        Schema::create('finance_asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['asset_id', 'effective_from', 'effective_to'], 'finance_asset_assignment_effective');
        });
        Schema::create('finance_depreciation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->date('period_month');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('accumulated_minor');
            $table->unsignedBigInteger('book_value_minor');
            $table->string('status', 20)->default('posted');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['asset_id', 'period_month']);
        });
        Schema::create('finance_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'year', 'name']);
        });
        Schema::create('finance_budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained('finance_budgets')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['budget_id', 'branch_id', 'cost_center_id', 'account_id', 'month'], 'finance_budget_line_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_budget_lines');
        Schema::dropIfExists('finance_budgets');
        Schema::dropIfExists('finance_depreciation_entries');
        Schema::dropIfExists('finance_asset_assignments');
        Schema::dropIfExists('finance_assets');
        Schema::dropIfExists('finance_asset_categories');
        Schema::dropIfExists('finance_expenses');
        Schema::dropIfExists('finance_expense_categories');
    }
};
