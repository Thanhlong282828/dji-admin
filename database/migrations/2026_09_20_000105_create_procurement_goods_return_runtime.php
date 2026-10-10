<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_goods_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('procurement_goods_receipts')->restrictOnDelete();
            $table->string('return_number', 100);
            $table->date('return_date');
            $table->char('currency', 3)->default('VND');
            $table->text('reason');
            $table->string('status', 30)->default('draft');
            $table->boolean('restore_commitment')->default(true);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('idempotency_key', 191);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_number'], 'goods_return_company_number_uq');
            $table->unique(['company_id', 'idempotency_key'], 'goods_return_company_idem_uq');
            $table->index(['company_id', 'status', 'return_date'], 'goods_return_company_status_date_idx');
        });

        Schema::create('procurement_goods_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_return_id')->constrained('procurement_goods_returns')->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('procurement_goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('line_value_minor');
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['goods_return_id', 'goods_receipt_line_id'], 'goods_return_receipt_line_uq');
            $table->index(['goods_receipt_line_id', 'goods_return_id'], 'goods_return_line_receipt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_goods_return_lines');
        Schema::dropIfExists('procurement_goods_returns');
    }
};
