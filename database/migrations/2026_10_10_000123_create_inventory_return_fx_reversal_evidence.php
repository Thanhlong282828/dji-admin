<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_return_fx_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_return_id')->constrained('procurement_goods_returns')->restrictOnDelete();
            $table->foreignId('goods_return_line_id')->unique()->constrained('procurement_goods_return_lines')->restrictOnDelete();
            $table->foreignId('inventory_receipt_fx_valuation_id');
            $table->foreign('inventory_receipt_fx_valuation_id', 'inventory_return_fx_valuation_fk')->references('id')->on('inventory_receipt_fx_valuations')->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('return_quantity');
            $table->unsignedInteger('cumulative_return_quantity');
            $table->char('transaction_currency', 3);
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('released_functional_basis_minor');
            $table->unsignedBigInteger('cumulative_released_basis_minor');
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'branch_id'], 'inventory_return_fx_scope_idx');
            $table->index(['inventory_receipt_fx_valuation_id', 'cumulative_return_quantity'], 'inventory_return_fx_basis_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_return_fx_reversals');
    }
};
