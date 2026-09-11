<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('title');
            $table->text('description');
            $table->string('priority', 20)->default('NORMAL');
            $table->string('failure_category', 80)->nullable();
            $table->date('requested_date')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reported_at');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'priority']);
        });
        Schema::create('maintenance_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->unique()->constrained('maintenance_requests')->restrictOnDelete();
            $table->foreignId('preventive_plan_id')->nullable();
            $table->date('preventive_due_date')->nullable();
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('maintenance_type', 20);
            $table->string('priority', 20)->default('NORMAL');
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->text('completion_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['preventive_plan_id', 'preventive_due_date'], 'maintenance_plan_occurrence_unique');
            $table->index(['company_id', 'status', 'scheduled_end']);
        });
        Schema::create('preventive_maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('finance_assets')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('frequency_type', 20);
            $table->unsignedSmallInteger('frequency_value')->default(1);
            $table->date('next_due_date');
            $table->timestamp('last_completed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'next_due_date'], 'maintenance_plan_due_index');
        });
        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->foreign('preventive_plan_id')->references('id')->on('preventive_maintenance_plans')->nullOnDelete();
        });
        Schema::create('maintenance_part_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('maintenance_work_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('REQUIRED');
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->unsignedBigInteger('actual_cost_minor')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_part_requirements');
        Schema::table('maintenance_work_orders', fn (Blueprint $table) => $table->dropForeign(['preventive_plan_id']));
        Schema::dropIfExists('preventive_maintenance_plans');
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('maintenance_requests');
    }
};
