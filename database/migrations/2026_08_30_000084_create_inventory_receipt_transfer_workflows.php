<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->string('supplier_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'status'], 'stock_receipt_scope_status');
        });

        Schema::create('inventory_stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('destination_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('source_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'source_branch_id', 'status'], 'stock_transfer_source_status');
            $table->index(['company_id', 'destination_branch_id', 'status'], 'stock_transfer_destination_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_transfers');
        Schema::dropIfExists('inventory_stock_receipts');
    }
};
