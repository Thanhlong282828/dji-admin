<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table): void {
            $table->foreignId('hired_employee_id')->nullable()->unique()->after('reservation_status')->constrained('employees')->nullOnDelete();
            $table->timestamp('hired_at')->nullable()->after('hired_employee_id');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('hired_at')->nullable()->after('hired_employee_id');
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('hired_employee_id')->nullable()->unique()->after('status')->constrained('employees')->nullOnDelete();
            $table->foreignId('hired_application_id')->nullable()->unique()->after('hired_employee_id')->constrained('applications')->nullOnDelete();
            $table->timestamp('hired_at')->nullable()->after('hired_application_id');
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('source', 30)->default('manual')->after('employment_type')->index();
            $table->foreignId('source_candidate_id')->nullable()->unique()->after('source')->constrained('candidates')->nullOnDelete();
            $table->foreignId('source_application_id')->nullable()->unique()->after('source_candidate_id')->constrained('applications')->nullOnDelete();
            $table->foreignId('source_offer_id')->nullable()->unique()->after('source_application_id')->constrained('candidate_offers')->nullOnDelete();
        });
        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->foreignId('source_offer_id')->nullable()->unique()->after('employee_id')->constrained('candidate_offers')->nullOnDelete();
        });
        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->foreignId('application_id')->nullable()->unique()->after('employee_id')->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->unique()->after('application_id')->constrained('candidate_offers')->nullOnDelete();
            $table->foreignId('requisition_id')->nullable()->after('offer_id')->constrained('recruitment_requisitions')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->after('requisition_id')->constrained()->nullOnDelete();
            $table->foreignId('organization_unit_id')->nullable()->after('job_position_id')->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->after('organization_unit_id')->constrained('users')->nullOnDelete();
            $table->string('template_code', 100)->nullable()->after('owner_user_id');
            $table->foreignId('created_by')->nullable()->after('template_code')->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'employee_id'], 'onboarding_company_employee_unique');
        });
        Schema::table('onboarding_provisioning_items', function (Blueprint $table): void {
            $table->string('code', 100)->nullable()->after('onboarding_case_id');
            $table->string('provider', 50)->default('internal')->after('type');
            $table->string('provider_status', 30)->default('internal')->after('provider');
            $table->unique(['onboarding_case_id', 'code'], 'onboarding_provisioning_code_unique');
        });
        Schema::create('hire_transaction_markers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->unique()->constrained('candidate_offers')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('onboarding_case_id')->nullable()->unique()->constrained('onboarding_cases')->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 20)->default('processing')->index();
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hire_transaction_markers');
        Schema::table('onboarding_provisioning_items', function (Blueprint $table): void {
            $table->dropUnique('onboarding_provisioning_code_unique');
            $table->dropColumn(['code', 'provider', 'provider_status']);
        });
        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->dropUnique('onboarding_company_employee_unique');
            foreach (['application_id', 'offer_id', 'requisition_id', 'job_position_id', 'organization_unit_id', 'owner_user_id', 'created_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn('template_code');
        });
        Schema::table('employment_contracts', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_offer_id'));
        Schema::table('employees', function (Blueprint $table): void {
            foreach (['source_candidate_id', 'source_application_id', 'source_offer_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn('source');
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hired_employee_id');
            $table->dropConstrainedForeignId('hired_application_id');
            $table->dropColumn('hired_at');
        });
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('hired_at'));
        Schema::table('candidate_offers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hired_employee_id');
            $table->dropColumn('hired_at');
        });
    }
};
