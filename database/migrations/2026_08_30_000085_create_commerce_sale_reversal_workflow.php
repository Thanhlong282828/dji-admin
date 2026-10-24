<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_sale_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('original_revenue_id')->constrained('branch_management_revenues')->restrictOnDelete();
            $table->string('reference', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3)->default('VND');
            $table->string('status', 20)->default('completed');
            $table->boolean('inventory_restocked')->default(false);
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['company_id', 'source_type', 'source_id'], 'sale_reversal_source_unique');
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'branch_id', 'completed_at'], 'sale_reversal_scope_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_sale_reversals');
    }
};
