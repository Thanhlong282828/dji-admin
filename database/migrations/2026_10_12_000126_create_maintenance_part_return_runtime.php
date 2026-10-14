<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_part_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained('maintenance_work_orders')->restrictOnDelete();
            $table->foreignId('maintenance_part_requirement_id')->constrained('maintenance_part_requirements')->restrictOnDelete();
            $table->foreignId('original_issue_movement_id')->constrained('inventory_movements')->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('returned_value_minor');
            $table->char('currency', 3);
            $table->string('return_reference', 100);
            $table->string('idempotency_key', 191);
            $table->string('return_type', 30)->default('UNUSED_PHYSICAL_RETURN');
            $table->text('reason');
            $table->string('status', 20)->default('POSTED');
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->foreignId('accounting_journal_id')->nullable()->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->foreignId('accounting_policy_id')->constrained('accounting_posting_policies')->restrictOnDelete();
            $table->unsignedInteger('accounting_policy_version');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['company_id', 'return_reference'], 'maintenance_part_return_reference_uq');
            $table->unique(['company_id', 'idempotency_key'], 'maintenance_part_return_idempotency_uq');
            $table->unique('inventory_movement_id', 'maintenance_part_return_movement_uq');
            $table->unique('accounting_journal_id', 'maintenance_part_return_journal_uq');
            $table->index(['maintenance_part_requirement_id', 'status'], 'maintenance_part_return_requirement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_part_returns');
    }
};
