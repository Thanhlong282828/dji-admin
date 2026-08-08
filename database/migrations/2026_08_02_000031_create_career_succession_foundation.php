<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('career_paths')) {
            Schema::create('career_paths', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
                $table->unique(['company_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('career_path_steps')) {
            Schema::create('career_path_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('career_path_id')->constrained()->cascadeOnDelete();
                $table->foreignId('position_id')->constrained('job_positions')->restrictOnDelete();
                $table->unsignedInteger('sequence');
                $table->unsignedTinyInteger('required_level')->default(1);
                $table->unsignedInteger('expected_duration_months')->nullable();
                $table->char('checksum', 64);
                $table->timestamps();
                $table->unique(['career_path_id', 'sequence']);
                $table->unique(['career_path_id', 'position_id']);
            });
        }
        if (! Schema::hasTable('employee_career_plans')) {
            Schema::create('employee_career_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('career_path_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('current_step')->default(1);
                $table->unsignedInteger('target_step');
                $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['company_id', 'idempotency_key']);
                $table->index(['company_id', 'employee_id', 'status']);
            });
        }
        if (! Schema::hasTable('succession_plans')) {
            Schema::create('succession_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('position_id')->constrained('job_positions')->restrictOnDelete();
                $table->foreignId('current_holder_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->enum('status', ['draft', 'nominated', 'assessed', 'approved', 'active', 'archived'])->default('draft');
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamps();
                $table->unique(['company_id', 'position_id', 'status']);
                $table->unique(['company_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('succession_candidates')) {
            Schema::create('succession_candidates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('succession_plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->enum('readiness_level', ['not_ready', 'developing', 'ready_1_2_years', 'ready_now'])->default('not_ready');
                $table->decimal('score', 5, 2)->unsigned()->default(0);
                $table->text('reason')->nullable();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamps();
                $table->unique(['succession_plan_id', 'employee_id']);
                $table->unique(['succession_plan_id', 'idempotency_key']);
            });
        }
        if (! Schema::hasTable('readiness_assessments')) {
            Schema::create('readiness_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('succession_candidate_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('assessment_type');
                $table->decimal('score', 5, 2)->unsigned();
                $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['company_id', 'idempotency_key']);
                $table->index(['company_id', 'employee_id', 'created_at']);
            });
        }
        if (! Schema::hasTable('career_history')) {
            Schema::create('career_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
                $table->string('aggregate_type');
                $table->unsignedBigInteger('aggregate_id');
                $table->string('event_type');
                $table->foreignId('from_position')->nullable()->constrained('job_positions')->nullOnDelete();
                $table->foreignId('to_position')->nullable()->constrained('job_positions')->nullOnDelete();
                $table->json('payload');
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->char('checksum', 64);
                $table->string('idempotency_key', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['company_id', 'idempotency_key']);
                $table->index(['company_id', 'aggregate_type', 'aggregate_id', 'created_at'], 'career_history_aggregate_timeline_index');
            });
        }
        if (! Schema::hasTable('career_outbox')) {
            Schema::create('career_outbox', function (Blueprint $table): void {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('event_type');
                $table->string('aggregate_type');
                $table->unsignedBigInteger('aggregate_id');
                $table->json('payload');
                $table->char('payload_checksum', 64);
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamp('published_at')->nullable();
                $table->index(['company_id', 'published_at', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('career_outbox');
        Schema::dropIfExists('career_history');
        Schema::dropIfExists('readiness_assessments');
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('succession_plans');
        Schema::dropIfExists('employee_career_plans');
        Schema::dropIfExists('career_path_steps');
        Schema::dropIfExists('career_paths');
    }
};
