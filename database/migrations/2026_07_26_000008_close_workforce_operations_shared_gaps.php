<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_break_rules', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('code');
            $table->string('mode', 20)->default('flexible')->after('kind');
            $table->unsignedInteger('sort_order')->default(0)->after('minimum_work_minutes');
            $table->string('status', 20)->default('active')->after('sort_order')->index();
            $table->date('effective_from')->nullable()->after('status');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->unsignedInteger('version')->default(1)->after('effective_to');
            $table->index(['work_shift_id', 'status', 'sort_order'], 'shift_break_rule_editor_lookup');
        });

        Schema::table('leave_attachments', function (Blueprint $table): void {
            $table->foreignId('replaced_by_attachment_id')->nullable()->after('version')->constrained('leave_attachments')->nullOnDelete();
            $table->timestamp('removed_at')->nullable()->after('scanned_at');
            $table->foreignId('removed_by')->nullable()->after('removed_at')->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'leave_request_id', 'status', 'removed_at'], 'leave_attachment_runtime_lookup');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('notification_type', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('entity_version');
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('action', 50);
            $table->uuid('idempotency_key');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'notification_type', 'entity_type', 'entity_id', 'entity_version', 'recipient_id', 'channel', 'action'], 'notification_delivery_durable_unique');
            $table->unique('idempotency_key');
            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::table('leave_attachments', function (Blueprint $table): void {
            $table->dropIndex('leave_attachment_runtime_lookup');
            $table->dropConstrainedForeignId('removed_by');
            $table->dropConstrainedForeignId('replaced_by_attachment_id');
            $table->dropColumn('removed_at');
        });
        Schema::table('shift_break_rules', function (Blueprint $table): void {
            $table->dropIndex('shift_break_rule_editor_lookup');
            $table->dropColumn(['name', 'mode', 'sort_order', 'status', 'effective_from', 'effective_to', 'version']);
        });
    }
};
