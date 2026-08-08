<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->timestamp('effective_at');
            $table->uuid('correlation_id');
            $table->uuid('causation_id')->nullable();
            $table->string('idempotency_key', 128);
            $table->char('payload_checksum', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'idempotency_key'], 'employee_lifecycle_idempotency_unique');
            $table->index(['company_id', 'employee_id', 'effective_at'], 'employee_lifecycle_timeline_index');
        });

        Schema::create('employment_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('transition_type', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->text('reason')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('checksum', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'employee_id', 'effective_at'], 'employment_transition_timeline_index');
        });

        Schema::create('employee_positions_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('job_positions')->restrictOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('employee_positions_history')->restrictOnDelete();
            $table->char('checksum', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'employee_id', 'effective_from'], 'employee_position_history_index');
        });

        Schema::create('employee_manager_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->char('checksum', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'employee_id', 'effective_from'], 'employee_manager_history_index');
        });

        Schema::create('employee_documents_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('document_type', 64);
            $table->string('document_reference', 255);
            $table->char('checksum', 64);
            $table->unsignedInteger('version');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'employee_id', 'document_type', 'version'], 'employee_document_history_version_unique');
        });

        Schema::create('employee_lifecycle_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('event_type', 160);
            $table->json('payload');
            $table->char('payload_checksum', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'published_at'], 'employee_lifecycle_outbox_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_lifecycle_outbox');
        Schema::dropIfExists('employee_documents_history');
        Schema::dropIfExists('employee_manager_history');
        Schema::dropIfExists('employee_positions_history');
        Schema::dropIfExists('employment_transitions');
        Schema::dropIfExists('employee_lifecycle_events');
    }
};
