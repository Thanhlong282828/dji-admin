<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('code', 64);
            $t->string('name');
            $t->string('type', 32);
            $t->string('status', 24)->default('draft');
            $t->string('health_status', 24)->default('unknown');
            $t->string('base_url')->nullable();
            $t->json('configuration')->nullable();
            $t->text('credential_reference')->nullable();
            $t->unsignedInteger('timeout_seconds')->default(30);
            $t->unsignedInteger('max_attempts')->default(3);
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
            $t->index(['company_id', 'status', 'type']);
        });
        Schema::create('integration_connectors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $t->string('adapter', 64);
            $t->json('capabilities')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['connection_id', 'adapter']);
        });
        Schema::create('integration_outbound_messages', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('integration_connections')->restrictOnDelete();
            $t->string('source_type', 80);
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('message_type', 80);
            $t->string('status', 24)->default('queued');
            $t->json('payload');
            $t->char('payload_checksum', 64);
            $t->uuid('idempotency_key');
            $t->unsignedInteger('attempt_count')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('failure_code')->nullable();
            $t->string('safe_failure_message', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key'], 'int_out_company_idem_uq');
            $t->index(['company_id', 'status', 'available_at'], 'int_out_status_idx');
            $t->index(['company_id', 'source_type', 'source_id'], 'int_out_source_idx');
        });
        Schema::create('integration_inbound_messages', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('integration_connections')->restrictOnDelete();
            $t->string('provider', 80);
            $t->string('event_id', 191);
            $t->string('event_type', 80);
            $t->string('status', 24)->default('received');
            $t->json('payload');
            $t->char('payload_checksum', 64);
            $t->timestamp('received_at');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'provider', 'event_id'], 'int_in_provider_event_uq');
            $t->index(['company_id', 'status', 'received_at'], 'int_in_status_idx');
        });
        Schema::create('integration_delivery_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outbound_message_id')->constrained('integration_outbound_messages')->cascadeOnDelete();
            $t->unsignedInteger('attempt_number');
            $t->string('status', 24);
            $t->string('external_reference')->nullable();
            $t->string('response_code', 64)->nullable();
            $t->char('response_checksum', 64)->nullable();
            $t->string('safe_message', 500)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['outbound_message_id', 'attempt_number'], 'int_attempt_message_number_uq');
        });
        Schema::create('integration_dead_letters', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outbound_message_id')->constrained('integration_outbound_messages')->cascadeOnDelete()->unique();
            $t->string('status', 24)->default('open');
            $t->string('reason_code', 80);
            $t->string('safe_reason', 500);
            $t->unsignedInteger('replay_count')->default(0);
            $t->uuid('last_replay_key')->nullable()->unique();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });
        Schema::create('integration_external_references', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('outbound_message_id')->constrained('integration_outbound_messages')->cascadeOnDelete()->unique('int_ext_message_uq');
            $t->string('provider', 80);
            $t->string('external_reference', 191);
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['company_id', 'provider', 'external_reference'], 'int_ext_provider_ref_uq');
        });
        Schema::create('integration_webhook_receipts', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('integration_connections')->restrictOnDelete();
            $t->string('provider', 80);
            $t->string('event_id', 191);
            $t->char('signature_fingerprint', 64);
            $t->char('payload_checksum', 64);
            $t->string('status', 24);
            $t->timestamp('received_at');
            $t->timestamps();
            $t->unique(['company_id', 'provider', 'event_id'], 'int_webhook_event_uq');
        });
        Schema::create('integration_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('aggregate_type', 80);
            $t->unsignedBigInteger('aggregate_id');
            $t->string('action', 80);
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24)->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('metadata')->nullable();
            $t->char('checksum', 64);
            $t->timestamp('occurred_at');
            $t->index(['company_id', 'aggregate_type', 'aggregate_id'], 'int_history_aggregate_idx');
        });
        Schema::create('integration_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('event_type', 100);
            $t->string('aggregate_type', 80);
            $t->unsignedBigInteger('aggregate_id');
            $t->json('payload');
            $t->char('checksum', 64);
            $t->uuid('idempotency_key')->unique();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('created_at');
            $t->index(['published_at', 'available_at']);
        });
    }

    public function down(): void
    {
        foreach (['integration_outbox', 'integration_histories', 'integration_webhook_receipts', 'integration_external_references', 'integration_dead_letters', 'integration_delivery_attempts', 'integration_inbound_messages', 'integration_outbound_messages', 'integration_connectors', 'integration_connections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
