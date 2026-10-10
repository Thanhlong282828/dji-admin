<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_receipt_fx_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('procurement_goods_receipts')->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->unique()->constrained('procurement_goods_receipt_lines')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->unsignedBigInteger('foreign_amount_minor');
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('functional_amount_minor');
            $table->unsignedBigInteger('functional_unit_cost_minor');
            $table->foreignId('fx_rate_snapshot_id')->nullable()->constrained('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->decimal('rate_decimal', 24, 12);
            $table->date('recognition_date');
            $table->string('rate_type', 20);
            $table->string('source_reference', 180);
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'branch_id'], 'inventory_receipt_fx_scope_idx');
            $table->index(['company_id', 'transaction_currency'], 'inventory_receipt_fx_currency_idx');
        });

        Schema::create('vendor_bill_receipt_fx_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_receipt_allocation_id');
            $table->unique('vendor_bill_receipt_allocation_id', 'vbrfx_allocation_uq');
            $table->foreign('vendor_bill_receipt_allocation_id', 'vbrfx_allocation_fk')->references('id')->on('vendor_bill_receipt_allocations')->restrictOnDelete();
            $table->foreignId('inventory_receipt_fx_valuation_id');
            $table->foreign('inventory_receipt_fx_valuation_id', 'vbrfx_valuation_fk')->references('id')->on('inventory_receipt_fx_valuations')->restrictOnDelete();
            $table->foreignId('supplier_invoice_fx_recognition_id');
            $table->foreign('supplier_invoice_fx_recognition_id', 'vbrfx_recognition_fk')->references('id')->on('supplier_invoice_fx_recognitions')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('foreign_amount_minor');
            $table->unsignedBigInteger('released_grni_functional_minor');
            $table->unsignedBigInteger('ap_functional_minor');
            $table->bigInteger('purchase_price_variance_minor');
            $table->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'vendor_bill_id'], 'vendor_bill_receipt_fx_bill_idx');
            $table->index(['company_id', 'inventory_receipt_fx_valuation_id'], 'vendor_bill_receipt_fx_valuation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_receipt_fx_matches');
        Schema::dropIfExists('inventory_receipt_fx_valuations');
    }
};
