<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('user_id', 'employees_user_unique');
            $table->unique('identity_number_hash', 'employees_identity_hash_unique');
            $table->unique(['company_id', 'work_email'], 'employees_company_work_email_unique');
            $table->index(['company_id', 'employment_status', 'employment_type'], 'employees_company_status_type_idx');
            $table->index(['company_id', 'join_date'], 'employees_company_join_date_idx');
            $table->index(['company_id', 'manager_id'], 'employees_company_manager_idx');
        });

        Schema::table('employee_assignments', function (Blueprint $table): void {
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'employee_assignment_active_idx');
            $table->index(['employee_id', 'assignment_type', 'effective_from'], 'employee_assignment_primary_idx');
            $table->index(['organization_unit_id', 'effective_from', 'effective_to'], 'employee_assignment_unit_effective_idx');
        });

        Schema::table('employee_histories', function (Blueprint $table): void {
            $table->index(['employee_id', 'effective_at'], 'employee_history_timeline_idx');
            $table->index(['employee_id', 'event_type'], 'employee_history_event_idx');
        });

        Schema::table('employee_credentials', function (Blueprint $table): void {
            $table->index(['employee_id', 'type'], 'employee_credential_type_idx');
            $table->index(['employee_id', 'expires_at'], 'employee_credential_expiry_idx');
        });

        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->index(['employee_id', 'status', 'starts_at', 'ends_at'], 'employment_contract_active_idx');
        });

        Schema::table('data_consents', function (Blueprint $table): void {
            $table->index(['employee_id', 'purpose', 'decided_at'], 'data_consent_history_idx');
            $table->index(['granted', 'expires_at'], 'data_consent_expiry_idx');
        });

        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->unique(['employee_id', 'type', 'name', 'version'], 'employee_document_version_unique');
            $table->index(['employee_id', 'type'], 'employee_document_type_idx');
            $table->index('checksum', 'employee_document_checksum_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->dropUnique('employee_document_version_unique');
            $table->dropIndex('employee_document_type_idx');
            $table->dropIndex('employee_document_checksum_idx');
        });
        Schema::table('data_consents', function (Blueprint $table): void {
            $table->dropIndex('data_consent_history_idx');
            $table->dropIndex('data_consent_expiry_idx');
        });
        Schema::table('employment_contracts', fn (Blueprint $table) => $table->dropIndex('employment_contract_active_idx'));
        Schema::table('employee_credentials', function (Blueprint $table): void {
            $table->dropIndex('employee_credential_type_idx');
            $table->dropIndex('employee_credential_expiry_idx');
        });
        Schema::table('employee_histories', function (Blueprint $table): void {
            $table->dropIndex('employee_history_timeline_idx');
            $table->dropIndex('employee_history_event_idx');
        });
        Schema::table('employee_assignments', function (Blueprint $table): void {
            $table->dropIndex('employee_assignment_active_idx');
            $table->dropIndex('employee_assignment_primary_idx');
            $table->dropIndex('employee_assignment_unit_effective_idx');
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_user_unique');
            $table->dropUnique('employees_identity_hash_unique');
            $table->dropUnique('employees_company_work_email_unique');
            $table->dropIndex('employees_company_status_type_idx');
            $table->dropIndex('employees_company_join_date_idx');
            $table->dropIndex('employees_company_manager_idx');
        });
    }
};
