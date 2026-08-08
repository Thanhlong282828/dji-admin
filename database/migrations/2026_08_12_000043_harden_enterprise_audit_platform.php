<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_source_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_module', 80);
            $table->string('source_event_id', 191);
            $table->string('consumer_name', 120);
            $table->foreignId('audit_event_id')->constrained('audit_events')->cascadeOnDelete();
            $table->char('checksum', 64);
            $table->timestamp('consumed_at');
            $table->unique(['source_module', 'source_event_id', 'consumer_name'], 'audit_source_consumer_uq');
            $table->index(['company_id', 'consumed_at'], 'audit_source_scope_idx');
        });

        Schema::create('audit_archive_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('range_start');
            $table->timestamp('range_end');
            $table->unsignedBigInteger('event_count');
            $table->char('checksum', 64);
            $table->string('artifact_reference', 191);
            $table->string('storage_disk', 30)->default('local');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('status', 24)->default('archived');
            $table->string('idempotency_key', 191);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['company_id', 'idempotency_key'], 'audit_archive_idem_uq');
            $table->index(['company_id', 'range_start', 'range_end'], 'audit_archive_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_archive_artifacts');
        Schema::dropIfExists('audit_source_checkpoints');
    }
};
