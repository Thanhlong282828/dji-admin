<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 100);
            $table->string('name');
            $table->string('category', 80);
            $table->boolean('mandatory')->default(false);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'notification_template_company_code_unique');
            $table->index(['company_id', 'status', 'category']);
        });

        Schema::create('notification_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject_template');
            $table->text('body_template');
            $table->char('checksum', 64);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['notification_template_id', 'version'], 'notification_template_version_unique');
        });

        Schema::create('notification_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_template_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category', 80);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 80);
            $table->string('title');
            $table->text('body');
            $table->string('navigation_reference')->nullable();
            $table->json('safe_payload');
            $table->char('payload_checksum', 64);
            $table->string('idempotency_key', 160);
            $table->string('status', 20)->default('queued');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'notification_message_idempotency_unique');
            $table->index(['company_id', 'category', 'created_at']);
        });

        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resolution_source', 80);
            $table->timestamps();
            $table->unique(['notification_message_id', 'user_id'], 'notification_message_recipient_unique');
            $table->index(['company_id', 'user_id']);
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->foreignId('notification_message_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('artifact_uuid')->nullable()->after('idempotency_key');
            $table->index(['notification_message_id', 'recipient_id', 'channel'], 'notification_center_delivery_lookup');
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('result', 20);
            $table->string('failure_code', 80)->nullable();
            $table->string('safe_failure_message', 500)->nullable();
            $table->char('checksum', 64);
            $table->timestamp('attempted_at');
            $table->unique(['notification_delivery_id', 'attempt_number'], 'notification_delivery_attempt_unique');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 80)->default('*');
            $table->boolean('database_enabled')->default(true);
            $table->boolean('broadcast_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'user_id', 'category'], 'notification_preference_scope_unique');
        });

        Schema::create('notification_read_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->char('checksum', 64);
            $table->timestamps();
            $table->unique(['notification_message_id', 'user_id'], 'notification_read_state_unique');
            $table->index(['company_id', 'user_id', 'read_at']);
        });

        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 100);
            $table->json('safe_payload');
            $table->char('payload_checksum', 64);
            $table->string('idempotency_key', 160);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'notification_outbox_idempotency_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_read_states');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_center_delivery_lookup');
            $table->dropConstrainedForeignId('notification_message_id');
            $table->dropColumn('artifact_uuid');
        });
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notification_messages');
        Schema::dropIfExists('notification_template_versions');
        Schema::dropIfExists('notification_templates');
    }
};
