<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('status', 24)->default('draft')->index();
            $table->date('count_date');
            $table->string('idempotency_key', 191);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'branch_id', 'count_date'], 'inventory_stock_counts_scope_date_idx');
        });

        Schema::create('inventory_stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('inventory_stock_counts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('system_qty');
            $table->unsignedInteger('counted_qty');
            $table->integer('variance_qty');
            $table->timestamps();
            $table->unique(['stock_count_id', 'product_variant_id'], 'inventory_stock_count_variant_unique');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('stock_count_id')->nullable()->constrained('inventory_stock_counts')->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('reason', 32);
            $table->string('status', 24)->default('draft')->index();
            $table->date('posting_date');
            $table->char('currency', 3);
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
            $table->index(['company_id', 'branch_id', 'posting_date'], 'inventory_adjustments_scope_date_idx');
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->unsignedBigInteger('unit_cost_minor');
            $table->bigInteger('value_delta_minor');
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['adjustment_id', 'product_variant_id'], 'inventory_adjustment_variant_unique');
            $table->unique('inventory_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_stock_count_lines');
        Schema::dropIfExists('inventory_stock_counts');
    }
};
