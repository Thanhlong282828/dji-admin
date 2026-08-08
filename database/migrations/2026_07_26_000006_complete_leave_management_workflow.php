<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('approval_workflow')->index();
        });
        Schema::table('employee_leave_policy_assignments', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('effective_to');
            $table->uuid('idempotency_key')->nullable()->after('version');
            $table->unique(['company_id', 'idempotency_key'], 'leave_assignment_idempotency_unique');
            $table->index(['company_id', 'employee_id', 'leave_policy_id', 'effective_from'], 'leave_assignment_effective_lookup');
        });
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('version');
            $table->unique(['company_id', 'idempotency_key'], 'leave_request_idempotency_unique');
        });
        Schema::create('leave_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->unsignedInteger('request_version');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'leave_request_id', 'created_at'], 'leave_request_history_timeline');
        });
        Schema::create('leave_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('storage_key');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('scan_status', 20)->default('pending');
            $table->string('status', 20)->default('quarantined');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'leave_attachment_idempotency_unique');
            $table->index(['company_id', 'leave_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_attachments');
        Schema::dropIfExists('leave_request_histories');
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropUnique('leave_request_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
        Schema::table('employee_leave_policy_assignments', function (Blueprint $table): void {
            $table->dropIndex('leave_assignment_effective_lookup');
            $table->dropUnique('leave_assignment_idempotency_unique');
            $table->dropColumn(['version', 'idempotency_key']);
        });
        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
