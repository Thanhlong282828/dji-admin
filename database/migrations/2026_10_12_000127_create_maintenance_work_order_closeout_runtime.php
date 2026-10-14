<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_order_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('maintenance_work_orders')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->text('acceptance_note');
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'accepted_at'], 'maintenance_acceptance_tenant_idx');
        });

        Schema::create('maintenance_work_order_closeouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('maintenance_work_orders')->restrictOnDelete();
            $table->foreignId('acceptance_id')->unique()->constrained('maintenance_work_order_acceptances')->restrictOnDelete();
            $table->string('idempotency_key', 191)->unique();
            $table->char('currency', 3);
            $table->unsignedBigInteger('gross_spare_cost_minor');
            $table->unsignedBigInteger('returned_spare_cost_minor');
            $table->unsignedBigInteger('net_spare_cost_minor');
            $table->unsignedBigInteger('other_verified_cost_minor');
            $table->unsignedBigInteger('total_actual_cost_minor');
            $table->json('lineage_snapshot');
            $table->char('snapshot_checksum', 64);
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'closed_at'], 'maintenance_closeout_tenant_idx');
            $table->unique(['company_id', 'snapshot_checksum'], 'maintenance_closeout_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_order_closeouts');
        Schema::dropIfExists('maintenance_work_order_acceptances');
    }
};
