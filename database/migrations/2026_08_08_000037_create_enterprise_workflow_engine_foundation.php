<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('latest_version')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });
        Schema::create('workflow_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->enum('routing_type', ['sequential', 'parallel', 'any_one', 'all_must_approve', 'conditional']);
            $table->json('configuration');
            $table->char('checksum', 64);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'version'], 'workflow_def_version_unique');
        });
        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_version_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->string('name');
            $table->unsignedInteger('sequence');
            $table->enum('approval_mode', ['sequential', 'parallel', 'any_one', 'all_must_approve', 'conditional']);
            $table->enum('resolver_type', ['specific_user', 'manager', 'permission', 'organization_role']);
            $table->string('resolver_value');
            $table->json('condition')->nullable();
            $table->unsignedInteger('due_after_minutes')->nullable();
            $table->unsignedInteger('escalation_after_minutes')->nullable();
            $table->string('fallback_resolver_type')->nullable();
            $table->string('fallback_resolver_value')->nullable();
            $table->timestamps();
            $table->unique(['workflow_definition_version_id', 'step_key'], 'workflow_step_version_key_unique');
            $table->index(['workflow_definition_version_id', 'sequence'], 'workflow_step_version_sequence_index');
        });
        Schema::create('workflow_step_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->nullable()->constrained('workflow_steps')->cascadeOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->json('condition')->nullable();
            $table->timestamps();
            $table->unique(['from_step_id', 'decision', 'to_step_id'], 'workflow_step_transition_unique');
        });
        Schema::table('workflow_instances', function (Blueprint $table): void {
            $table->foreignId('workflow_definition_version_id')->nullable()->after('company_id')->constrained()->restrictOnDelete();
            $table->enum('engine_status', ['draft', 'running', 'completed', 'rejected', 'cancelled', 'expired'])->default('draft')->after('status');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('idempotency_key', 128)->nullable();
            $table->char('checksum', 64)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unique(['company_id', 'idempotency_key'], 'workflow_instance_company_key_unique');
            $table->index(['company_id', 'engine_status']);
        });
        Schema::create('workflow_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('assignment_source');
            $table->enum('status', ['pending', 'claimed', 'approved', 'rejected', 'cancelled', 'expired'])->default('pending');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalate_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('escalation_level')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'assigned_user_id', 'status']);
            $table->index(['status', 'escalate_at']);
        });
        Schema::create('workflow_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 128);
            $table->char('checksum', 64);
            $table->timestamp('decided_at');
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique('workflow_task_id');
        });
        Schema::create('workflow_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata');
            $table->char('checksum', 64);
            $table->string('idempotency_key', 128);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['workflow_instance_id', 'created_at']);
        });
        Schema::create('workflow_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->char('payload_checksum', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['published_at', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['workflow_outbox', 'workflow_histories', 'workflow_decisions', 'workflow_tasks', 'workflow_step_transitions', 'workflow_steps', 'workflow_definition_versions', 'workflow_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('workflow_instances', function (Blueprint $table): void {
            $table->dropUnique('workflow_instance_company_key_unique');
            $table->dropIndex(['company_id', 'engine_status']);
            $table->dropConstrainedForeignId('workflow_definition_version_id');
            $table->dropConstrainedForeignId('started_by');
            $table->dropColumn(['engine_status', 'lock_version', 'idempotency_key', 'checksum', 'started_at', 'completed_at']);
        });
    }
};
