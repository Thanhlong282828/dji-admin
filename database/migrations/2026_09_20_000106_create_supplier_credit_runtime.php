<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bill_receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_bill_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('procurement_goods_receipt_lines')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('allocated_minor');
            $table->timestamps();
            $table->unique(['vendor_bill_line_id', 'goods_receipt_line_id'], 'bill_receipt_allocation_source_uq');
            $table->index(['goods_receipt_line_id', 'vendor_bill_line_id'], 'bill_receipt_allocation_receipt_idx');
        });

        Schema::create('supplier_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_return_id')->constrained('procurement_goods_returns')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('credit_number', 100);
            $table->date('credit_date');
            $table->char('currency', 3)->default('VND');
            $table->text('reason');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('total_minor');
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
            $table->unique(['company_id', 'credit_number'], 'supplier_credit_company_number_uq');
            $table->unique(['company_id', 'idempotency_key'], 'supplier_credit_company_idem_uq');
            $table->index(['company_id', 'status', 'credit_date'], 'supplier_credit_scope_idx');
            $table->index(['vendor_bill_id', 'status'], 'supplier_credit_bill_status_idx');
            $table->index(['goods_return_id', 'status'], 'supplier_credit_return_status_idx');
        });

        Schema::create('supplier_credit_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_return_line_id')->constrained('procurement_goods_return_lines')->restrictOnDelete();
            $table->foreignId('vendor_bill_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_receipt_allocation_id');
            $table->foreign('vendor_bill_receipt_allocation_id', 'supplier_credit_line_allocation_fk')->references('id')->on('vendor_bill_receipt_allocations')->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('procurement_goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('credit_minor');
            $table->timestamps();
            $table->unique(['supplier_credit_note_id', 'goods_return_line_id', 'vendor_bill_receipt_allocation_id'], 'supplier_credit_line_source_uq');
            $table->index(['goods_return_line_id', 'supplier_credit_note_id'], 'supplier_credit_line_return_idx');
            $table->index(['vendor_bill_line_id', 'supplier_credit_note_id'], 'supplier_credit_line_bill_idx');
        });

        Schema::create('procurement_exception_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('exception_type', 100);
            $table->string('fingerprint', 64);
            $table->string('severity', 20)->default('WARNING');
            $table->string('status', 20)->default('open');
            $table->foreignId('vendor_id')->nullable()->constrained('procurement_vendors')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('procurement_goods_receipts')->restrictOnDelete();
            $table->foreignId('goods_return_id')->nullable()->constrained('procurement_goods_returns')->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason', 500);
            $table->json('evidence')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_reference', 191)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fingerprint'], 'procurement_exception_fingerprint_uq');
            $table->index(['company_id', 'status', 'exception_type'], 'procurement_exception_workbench_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_exception_cases');
        Schema::dropIfExists('supplier_credit_note_lines');
        Schema::dropIfExists('supplier_credit_notes');
        Schema::dropIfExists('vendor_bill_receipt_allocations');
    }
};
