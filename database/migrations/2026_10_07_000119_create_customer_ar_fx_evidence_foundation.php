<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice_fx_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_invoice_id')->unique()->constrained('customer_invoices')->restrictOnDelete();
            $table->char('transaction_currency', 3);
            $table->unsignedBigInteger('transaction_amount_minor');
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('functional_amount_minor');
            $table->foreignId('fx_rate_snapshot_id')->nullable()->constrained('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 32);
            $table->string('source_reference', 180);
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'transaction_currency'], 'ar_fx_recognition_transaction_idx');
            $table->index(['company_id', 'functional_currency'], 'ar_fx_recognition_functional_idx');
        });

        Schema::create('customer_collection_allocation_fx_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_collection_allocation_id')->unique('ar_fx_settlement_allocation_unique');
            $table->foreignId('customer_invoice_fx_recognition_id');
            $table->foreignId('settlement_fx_snapshot_id')->nullable();
            $table->char('transaction_currency', 3);
            $table->char('functional_currency', 3);
            $table->unsignedBigInteger('foreign_settled_minor');
            $table->unsignedBigInteger('settlement_functional_minor');
            $table->unsignedBigInteger('released_carrying_minor');
            $table->bigInteger('realized_fx_minor');
            $table->decimal('rate_decimal', 24, 12);
            $table->date('rate_date');
            $table->string('rate_type', 32);
            $table->string('source_reference', 180);
            $table->char('checksum', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->foreign('customer_collection_allocation_id', 'ar_fx_settlement_allocation_fk')->references('id')->on('customer_collection_allocations')->restrictOnDelete();
            $table->foreign('customer_invoice_fx_recognition_id', 'ar_fx_settlement_recognition_fk')->references('id')->on('customer_invoice_fx_recognitions')->restrictOnDelete();
            $table->foreign('settlement_fx_snapshot_id', 'ar_fx_settlement_snapshot_fk')->references('id')->on('finance_fx_rate_snapshots')->restrictOnDelete();
            $table->index(['company_id', 'customer_invoice_fx_recognition_id'], 'ar_fx_settlement_recognition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_collection_allocation_fx_settlements');
        Schema::dropIfExists('customer_invoice_fx_recognitions');
    }
};
