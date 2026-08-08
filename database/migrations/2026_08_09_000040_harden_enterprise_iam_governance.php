<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iam_access_requests', function (Blueprint $table): void {
            $table->foreignId('workflow_instance_id')->nullable()->after('version')->constrained('workflow_instances')->nullOnDelete();
            $table->unsignedInteger('workflow_version')->nullable()->after('workflow_instance_id');
            $table->string('approval_mode', 24)->default('iam')->after('status');
            $table->index(['company_id', 'workflow_instance_id'], 'iam_request_workflow_index');
        });
        Schema::table('iam_access_assignments', function (Blueprint $table): void {
            $table->foreignId('requested_by')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
        });
        Schema::table('iam_sod_violations', function (Blueprint $table): void {
            $table->string('severity', 16)->default('blocking')->after('status');
            $table->timestamp('detected_at')->nullable()->after('checksum');
            $table->timestamp('resolved_at')->nullable()->after('detected_at');
            $table->string('resolution', 32)->nullable()->after('resolved_at');
        });
        Schema::table('iam_access_review_items', function (Blueprint $table): void {
            $table->text('reason')->nullable()->after('decision');
            $table->unsignedInteger('version')->default(1)->after('reason');
            $table->char('checksum', 64)->nullable()->after('snapshot_checksum');
            $table->json('snapshot')->nullable()->after('checksum');
        });
        Schema::table('workflow_outbox', function (Blueprint $table): void {
            $table->timestamp('iam_consumed_at')->nullable()->after('published_at');
            $table->index(['iam_consumed_at', 'created_at'], 'workflow_iam_consumer_index');
        });
        Schema::table('employee_lifecycle_outbox', function (Blueprint $table): void {
            $table->timestamp('iam_consumed_at')->nullable()->after('published_at');
            $table->index(['iam_consumed_at', 'created_at'], 'employee_iam_consumer_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_lifecycle_outbox', function (Blueprint $table): void {
            $table->dropIndex('employee_iam_consumer_index');
            $table->dropColumn('iam_consumed_at');
        });
        Schema::table('workflow_outbox', function (Blueprint $table): void {
            $table->dropIndex('workflow_iam_consumer_index');
            $table->dropColumn('iam_consumed_at');
        });
        Schema::table('iam_access_review_items', function (Blueprint $table): void {
            $table->dropColumn(['reason', 'version', 'checksum', 'snapshot']);
        });
        Schema::table('iam_sod_violations', function (Blueprint $table): void {
            $table->dropColumn(['severity', 'detected_at', 'resolved_at', 'resolution']);
        });
        Schema::table('iam_access_assignments', function (Blueprint $table): void {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['requested_by', 'reviewed_by']);
        });
        Schema::table('iam_access_requests', function (Blueprint $table): void {
            $table->dropIndex('iam_request_workflow_index');
            $table->dropForeign(['workflow_instance_id']);
            $table->dropColumn(['workflow_instance_id', 'workflow_version', 'approval_mode']);
        });
    }
};
