<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_requisitions', function (Blueprint $table): void {
            $table->index(['job_position_id', 'status', 'target_date'], 'requisition_position_status_target_idx');
            $table->index(['requested_by', 'status'], 'requisition_requester_status_idx');
            $table->index(['status', 'created_at'], 'requisition_dashboard_idx');
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['source', 'consent'], 'candidate_source_consent_idx');
            $table->index(['consent', 'created_at'], 'candidate_consent_created_idx');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->index(['recruitment_requisition_id', 'stage', 'stage_changed_at'], 'application_pipeline_idx');
            $table->index(['stage', 'stage_changed_at'], 'application_dashboard_idx');
            $table->index(['candidate_id', 'stage'], 'application_candidate_stage_idx');
        });
        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->index(['employee_id', 'status'], 'onboarding_employee_status_idx');
            $table->index(['status', 'start_date'], 'onboarding_status_start_idx');
            $table->index(['status', 'probation_review_date'], 'onboarding_probation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->dropIndex('onboarding_employee_status_idx');
            $table->dropIndex('onboarding_status_start_idx');
            $table->dropIndex('onboarding_probation_idx');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex('application_pipeline_idx');
            $table->dropIndex('application_dashboard_idx');
            $table->dropIndex('application_candidate_stage_idx');
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropIndex('candidate_source_consent_idx');
            $table->dropIndex('candidate_consent_created_idx');
            $table->dropSoftDeletes();
        });
        Schema::table('recruitment_requisitions', function (Blueprint $table): void {
            $table->dropIndex('requisition_position_status_target_idx');
            $table->dropIndex('requisition_requester_status_idx');
            $table->dropIndex('requisition_dashboard_idx');
        });
    }
};
