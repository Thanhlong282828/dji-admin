<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_asset_impairments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('reason', 32);
            $table->date('review_date');
            $table->unsignedBigInteger('previous_book_value_minor');
            $table->unsignedBigInteger('impairment_amount_minor');
            $table->string('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->string('idempotency_key', 191);
            $table->json('accounting_policy_snapshot')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'asset_id', 'status'], 'asset_impairment_scope_idx');
        });

        Schema::create('finance_asset_disposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->string('reference', 120);
            $table->date('disposal_date');
            $table->string('reason', 500);
            $table->unsignedBigInteger('proceeds_minor')->default(0);
            $table->unsignedBigInteger('book_value_minor');
            $table->bigInteger('gain_loss_minor');
            $table->string('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->string('idempotency_key', 191);
            $table->json('accounting_policy_snapshot')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique('asset_id', 'asset_disposal_once_unique');
            $table->index(['company_id', 'status', 'disposal_date'], 'asset_disposal_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_asset_disposals');
        Schema::dropIfExists('finance_asset_impairments');
    }
};
