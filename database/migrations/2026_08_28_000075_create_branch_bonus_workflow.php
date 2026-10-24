<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_bonus_proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('bonus_reference', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->string('reason_category', 50);
            $table->text('reason');
            $table->date('effective_period');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->foreignId('salary_component_assignment_id')->nullable()->constrained('employee_salary_component_assignments')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bonus_reference']);
            $table->index(['company_id', 'branch_id', 'effective_period', 'status'], 'branch_bonus_scope_period_status');
        });

        Schema::create('branch_bonus_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_bonus_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedInteger('version');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('safe_comment', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['company_id', 'branch_bonus_proposal_id', 'id'], 'branch_bonus_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_bonus_histories');
        Schema::dropIfExists('branch_bonus_proposals');
    }
};
