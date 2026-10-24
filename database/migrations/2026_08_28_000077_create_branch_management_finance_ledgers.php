<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_management_revenues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->string('source_type', 30)->default('management_ledger');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->date('recognized_on');
            $table->string('status', 20)->default('recognized');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'recognized_on', 'status'], 'branch_revenue_period');
        });
        Schema::create('branch_operating_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->string('category', 40);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->date('incurred_on');
            $table->string('status', 20)->default('approved');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'incurred_on', 'status'], 'branch_expense_period');
        });
        Schema::create('branch_rent_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('contract_reference', 80);
            $table->unsignedBigInteger('monthly_amount_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'contract_reference']);
            $table->index(['company_id', 'branch_id', 'effective_from', 'effective_to'], 'branch_rent_effective');
        });
        Schema::create('branch_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('asset_code', 80);
            $table->string('name');
            $table->string('category', 40);
            $table->unsignedBigInteger('acquisition_cost_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->date('acquired_on');
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'asset_code']);
            $table->index(['company_id', 'branch_id', 'acquired_on', 'status'], 'branch_asset_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_assets');
        Schema::dropIfExists('branch_rent_contracts');
        Schema::dropIfExists('branch_operating_expenses');
        Schema::dropIfExists('branch_management_revenues');
    }
};
