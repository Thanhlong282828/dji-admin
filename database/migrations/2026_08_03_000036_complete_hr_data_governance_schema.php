<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_data_quality_runs', function (Blueprint $table): void {
            $table->unsignedInteger('rule_version')->default(1)->after('run_date');
            $table->unsignedInteger('issue_count')->default(0)->after('status');
        });

        Schema::table('hr_data_quality_issues', function (Blueprint $table): void {
            $table->enum('status', ['open', 'reviewed', 'approved', 'resolved'])->default('open')->after('severity');
            $table->foreignId('created_by')->nullable()->after('message')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->after('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->text('resolution_reason')->nullable()->after('resolution_key');
            $table->char('resolution_checksum', 64)->nullable()->after('resolution_reason');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->index(['run_id', 'status', 'severity'], 'hr_quality_issue_status_index');
        });

        Schema::table('hr_governance_audit_events', function (Blueprint $table): void {
            $table->index(['company_id', 'entity_type', 'entity_id', 'created_at'], 'hr_governance_entity_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('hr_governance_audit_events', fn (Blueprint $table) => $table->dropIndex('hr_governance_entity_timeline_index'));
        Schema::table('hr_data_quality_issues', function (Blueprint $table): void {
            $table->dropIndex('hr_quality_issue_status_index');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'resolution_reason', 'resolution_checksum', 'reviewed_at', 'approved_at']);
        });
        Schema::table('hr_data_quality_runs', fn (Blueprint $table) => $table->dropColumn(['rule_version', 'issue_count']));
    }
};
