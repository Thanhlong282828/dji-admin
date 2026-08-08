<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_correlations', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('request_id', 100)->nullable();
            $t->string('root_source', 100);
            $t->char('checksum', 64);
            $t->timestamp('started_at');
            $t->timestamp('completed_at')->nullable();
            $t->index(['company_id', 'started_at'], 'audit_corr_scope_idx');
        });
        Schema::create('audit_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('correlation_id')->nullable()->constrained('audit_correlations')->nullOnDelete();
            $t->uuid('causation_id')->nullable();
            $t->string('idempotency_key', 191);
            $t->string('actor_type', 30);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action', 80);
            $t->string('source_module', 80);
            $t->string('source_event', 120);
            $t->string('resource_type', 100);
            $t->string('resource_id', 191);
            $t->string('ip_hash', 64)->nullable();
            $t->string('user_agent_hash', 64)->nullable();
            $t->json('metadata')->nullable();
            $t->char('payload_checksum', 64);
            $t->timestamp('occurred_at');
            $t->timestamp('created_at');
            $t->unique(['company_id', 'idempotency_key'], 'audit_event_idem_uq');
            $t->index(['company_id', 'occurred_at'], 'audit_event_time_idx');
            $t->index(['company_id', 'resource_type', 'resource_id'], 'audit_event_resource_idx');
            $t->index(['company_id', 'actor_type', 'actor_id'], 'audit_event_actor_idx');
        });
        Schema::create('audit_event_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('audit_event_id')->constrained('audit_events')->cascadeOnDelete();
            $t->string('snapshot_type', 16);
            $t->json('payload');
            $t->char('checksum', 64);
            $t->timestamp('created_at');
            $t->unique(['audit_event_id', 'snapshot_type'], 'audit_snapshot_type_uq');
        });
        Schema::create('audit_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('audit_event_id')->nullable()->constrained('audit_events')->nullOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('result', 30);
            $t->char('checksum', 64);
            $t->timestamp('accessed_at');
            $t->index(['company_id', 'accessed_at'], 'audit_access_scope_idx');
        });
        Schema::create('audit_exports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('status', 24);
            $t->string('format', 10);
            $t->json('filters');
            $t->string('storage_disk', 30)->nullable();
            $t->string('storage_path')->nullable();
            $t->string('file_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->char('checksum', 64)->nullable();
            $t->string('idempotency_key', 191);
            $t->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key'], 'audit_export_idem_uq');
            $t->index(['company_id', 'status'], 'audit_export_scope_idx');
        });
        Schema::create('audit_retention_policies', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('status', 24)->default('active');
            $t->unsignedInteger('retention_days');
            $t->string('archive_disk', 30)->default('local');
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'name'], 'audit_retention_name_uq');
        });
        Schema::create('audit_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('event_type', 100);
            $t->foreignId('audit_event_id')->nullable()->constrained('audit_events')->cascadeOnDelete();
            $t->json('payload');
            $t->char('checksum', 64);
            $t->string('idempotency_key', 191)->unique();
            $t->timestamp('available_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('created_at');
            $t->index(['published_at', 'available_at'], 'audit_outbox_pending_idx');
        });
    }

    public function down(): void
    {
        foreach (['audit_outbox', 'audit_retention_policies', 'audit_exports', 'audit_access_logs', 'audit_event_snapshots', 'audit_events', 'audit_correlations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
