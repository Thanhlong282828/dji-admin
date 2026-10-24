<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contract_renewals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_contract_id')->constrained('employment_contracts')->restrictOnDelete();
            $table->foreignId('new_contract_id')->nullable()->constrained('employment_contracts')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->date('effective_date');
            $table->date('new_ends_at')->nullable();
            $table->string('new_contract_type', 30);
            $table->decimal('new_base_salary', 18, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('status', 30)->default('draft')->index();
            $table->string('reason', 1000);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'previous_contract_id'], 'contract_renewal_previous_unique');
        });

        Schema::create('employee_resignations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->date('requested_last_working_date');
            $table->date('approved_last_working_date')->nullable();
            $table->string('category', 60)->default('voluntary');
            $table->string('reason', 1000);
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'status'], 'resignation_employee_status_index');
        });

        Schema::create('employee_offboarding_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('resignation_id')->nullable()->constrained('employee_resignations')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->date('effective_date');
            $table->string('status', 30)->default('in_progress')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'employee_id', 'resignation_id'], 'offboarding_resignation_unique');
        });

        Schema::create('employee_offboarding_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offboarding_case_id')->constrained('employee_offboarding_cases')->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('item_key', 80);
            $table->string('label');
            $table->boolean('mandatory')->default(true);
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('evidence_reference', 500)->nullable();
            $table->timestamps();
            $table->unique(['offboarding_case_id', 'item_key'], 'offboarding_case_item_unique');
        });

        Schema::create('accounting_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('target_accounting_period_id');
            $table->foreignId('resulting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('adjustment_type', 30)->default('reversal');
            $table->string('reason', 1000);
            $table->string('status', 30)->default('draft')->index();
            $table->string('original_checksum', 64);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'original_journal_id', 'adjustment_type'], 'accounting_adjustment_source_unique');
            $table->foreign('target_accounting_period_id', 'acct_adj_target_period_fk')->references('id')->on('accounting_periods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_adjustment_requests');
        Schema::dropIfExists('employee_offboarding_items');
        Schema::dropIfExists('employee_offboarding_cases');
        Schema::dropIfExists('employee_resignations');
        Schema::dropIfExists('employment_contract_renewals');
    }
};
