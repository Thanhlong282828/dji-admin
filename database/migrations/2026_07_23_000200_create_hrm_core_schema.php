<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('tax_code')->nullable();
            $t->char('country_code', 2)->default('VN');
            $t->char('currency', 3)->default('VND');
            $t->string('timezone')->default('Asia/Ho_Chi_Minh');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('cost_centers', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->decimal('annual_budget', 18, 2)->default(0);
            $t->char('currency', 3)->default('VND');
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('organization_units', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('parent_id')->nullable()->constrained('organization_units');
            $t->foreignId('cost_center_id')->nullable()->constrained();
            $t->enum('type', ['company', 'branch', 'division', 'department', 'section', 'team']);
            $t->string('code');
            $t->string('name');
            $t->unsignedInteger('headcount_limit')->default(0);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('job_positions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('organization_unit_id')->constrained();
            $t->string('code');
            $t->string('title');
            $t->string('job_grade')->nullable();
            $t->unsignedInteger('approved_headcount')->default(1);
            $t->decimal('budgeted_cost', 18, 2)->default(0);
            $t->json('competency_requirements')->nullable();
            $t->boolean('is_critical')->default(false);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('employees', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('employee_code')->unique();
            $t->string('full_name');
            $t->date('date_of_birth')->nullable();
            $t->string('gender', 20)->nullable();
            $t->string('personal_email')->nullable();
            $t->string('work_email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('nationality', 2)->default('VN');
            $t->string('identity_number')->nullable();
            $t->string('identity_number_hash')->nullable()->index();
            $t->date('identity_expires_at')->nullable()->index();
            $t->json('addresses')->nullable();
            $t->json('emergency_contacts')->nullable();
            $t->json('dependants')->nullable();
            $t->date('join_date');
            $t->date('official_date')->nullable();
            $t->enum('employment_status', ['preboarding', 'probation', 'active', 'suspended', 'leave', 'terminated', 'alumni'])->index();
            $t->string('employment_type')->default('full_time');
            $t->date('termination_date')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('employee_assignments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('organization_unit_id')->constrained();
            $t->foreignId('job_position_id')->constrained();
            $t->foreignId('manager_id')->nullable()->constrained('employees');
            $t->enum('assignment_type', ['primary', 'concurrent', 'temporary']);
            $t->decimal('allocation_percent', 5, 2)->default(100);
            $t->string('work_location')->nullable();
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->timestamps();
        });
        Schema::create('employee_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->enum('event_type', ['transfer', 'promotion', 'salary_change', 'discipline', 'award', 'status_change']);
            $t->json('before')->nullable();
            $t->json('after');
            $t->text('reason')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('effective_at');
            $t->timestamps();
        });
        Schema::create('employee_credentials', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->enum('type', ['education', 'certificate', 'language', 'skill', 'license', 'passport', 'work_permit', 'award']);
            $t->string('name');
            $t->string('issuer')->nullable();
            $t->date('issued_at')->nullable();
            $t->date('expires_at')->nullable()->index();
            $t->string('document_url')->nullable();
            $t->json('attributes')->nullable();
            $t->timestamps();
        });
        Schema::create('employment_contracts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('contract_number')->unique();
            $t->enum('type', ['probation', 'fixed_term', 'indefinite', 'contractor']);
            $t->date('starts_at');
            $t->date('ends_at')->nullable()->index();
            $t->decimal('base_salary', 18, 2);
            $t->char('currency', 3)->default('VND');
            $t->json('terms');
            $t->string('signed_document_url')->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->enum('status', ['draft', 'approval', 'active', 'expired', 'terminated'])->index();
            $t->timestamps();
        });
        Schema::create('recruitment_requisitions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('job_position_id')->constrained();
            $t->foreignId('requested_by')->constrained('employees');
            $t->unsignedInteger('vacancies');
            $t->decimal('budget', 18, 2)->default(0);
            $t->enum('status', ['draft', 'approval', 'open', 'paused', 'filled', 'cancelled'])->index();
            $t->json('approval_context')->nullable();
            $t->date('target_date')->nullable();
            $t->timestamps();
        });
        Schema::create('candidates', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('full_name');
            $t->string('email')->index();
            $t->string('phone', 30)->nullable();
            $t->string('source')->nullable();
            $t->string('resume_url')->nullable();
            $t->string('profile_fingerprint')->nullable()->index();
            $t->json('profile')->nullable();
            $t->boolean('consent')->default(false);
            $t->timestamps();
        });
        Schema::create('applications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('recruitment_requisition_id')->constrained();
            $t->foreignId('candidate_id')->constrained();
            $t->enum('stage', ['received', 'screening', 'test', 'interview', 'reference', 'offer', 'hired', 'rejected'])->index();
            $t->decimal('score', 5, 2)->nullable();
            $t->json('evaluations')->nullable();
            $t->timestamp('stage_changed_at');
            $t->timestamps();
            $t->unique(['recruitment_requisition_id', 'candidate_id']);
        });
        Schema::create('onboarding_cases', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('buddy_id')->nullable()->constrained('employees');
            $t->date('start_date');
            $t->date('probation_review_date')->nullable();
            $t->json('checklist');
            $t->json('provisioning')->nullable();
            $t->enum('status', ['preboarding', 'in_progress', 'probation_review', 'completed', 'cancelled'])->index();
            $t->timestamps();
        });
        Schema::create('work_shifts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->boolean('crosses_midnight')->default(false);
            $t->json('rules');
            $t->timestamps();
        });
        Schema::create('attendance_records', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('work_shift_id')->nullable()->constrained();
            $t->date('work_date')->index();
            $t->timestamp('check_in_at')->nullable();
            $t->timestamp('check_out_at')->nullable();
            $t->string('source')->nullable();
            $t->decimal('worked_hours', 7, 2)->default(0);
            $t->decimal('overtime_hours', 7, 2)->default(0);
            $t->json('location')->nullable();
            $t->json('adjustment')->nullable();
            $t->boolean('is_locked')->default(false);
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });
        Schema::create('leave_types', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->json('accrual_policy');
            $t->json('carry_forward_policy')->nullable();
            $t->boolean('is_paid')->default(true);
            $t->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('leave_type_id')->constrained();
            $t->date('starts_at');
            $t->date('ends_at');
            $t->decimal('days', 7, 2);
            $t->text('reason')->nullable();
            $t->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->index();
            $t->foreignId('approved_by')->nullable()->constrained('employees');
            $t->timestamps();
        });
        Schema::create('payroll_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('code')->unique();
            $t->date('period_start');
            $t->date('period_end');
            $t->enum('status', ['draft', 'calculating', 'review', 'approved', 'locked', 'paid'])->index();
            $t->decimal('gross_total', 20, 2)->default(0);
            $t->decimal('deduction_total', 20, 2)->default(0);
            $t->decimal('net_total', 20, 2)->default(0);
            $t->json('formula_version');
            $t->timestamp('locked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('payroll_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained();
            $t->foreignId('employee_id')->constrained();
            $t->decimal('gross_salary', 18, 2);
            $t->decimal('insurance', 18, 2)->default(0);
            $t->decimal('personal_income_tax', 18, 2)->default(0);
            $t->decimal('deductions', 18, 2)->default(0);
            $t->decimal('net_salary', 18, 2);
            $t->json('breakdown');
            $t->string('payslip_url')->nullable();
            $t->timestamps();
            $t->unique(['payroll_run_id', 'employee_id']);
        });
        Schema::create('performance_cycles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('name');
            $t->date('starts_at');
            $t->date('ends_at');
            $t->json('rating_scale');
            $t->enum('status', ['draft', 'active', 'calibration', 'closed']);
            $t->timestamps();
        });
        Schema::create('performance_goals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('performance_cycle_id')->constrained();
            $t->foreignId('employee_id')->constrained();
            $t->foreignId('parent_goal_id')->nullable()->constrained('performance_goals');
            $t->enum('type', ['KPI', 'OKR', 'competency']);
            $t->string('title');
            $t->decimal('weight', 5, 2);
            $t->decimal('progress', 5, 2)->default(0);
            $t->json('measure');
            $t->json('reviews')->nullable();
            $t->timestamps();
        });
        Schema::create('learning_courses', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->enum('delivery', ['online', 'classroom', 'blended']);
            $t->decimal('budget', 18, 2)->default(0);
            $t->json('content');
            $t->json('required_positions')->nullable();
            $t->timestamps();
        });
        Schema::create('learning_enrollments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('learning_course_id')->constrained();
            $t->foreignId('employee_id')->constrained();
            $t->decimal('progress', 5, 2)->default(0);
            $t->decimal('score', 5, 2)->nullable();
            $t->date('certificate_expires_at')->nullable()->index();
            $t->enum('status', ['assigned', 'in_progress', 'completed', 'failed', 'expired']);
            $t->timestamps();
        });
        Schema::create('workflow_instances', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained();
            $t->string('workflow_type');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('definition_snapshot');
            $t->unsignedInteger('current_step')->default(1);
            $t->enum('status', ['pending', 'approved', 'rejected', 'returned', 'cancelled'])->index();
            $t->timestamp('due_at')->nullable()->index();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('workflow_actions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workflow_instance_id')->constrained();
            $t->foreignId('actor_id')->constrained('users');
            $t->string('action');
            $t->text('comment')->nullable();
            $t->json('context')->nullable();
            $t->timestamp('acted_at');
            $t->timestamps();
        });
        Schema::create('employee_documents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->nullable()->constrained();
            $t->string('type');
            $t->string('name');
            $t->unsignedInteger('version')->default(1);
            $t->string('storage_key');
            $t->string('checksum');
            $t->date('expires_at')->nullable()->index();
            $t->json('access_policy');
            $t->timestamp('signed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('integration_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('connector');
            $t->string('direction');
            $t->string('correlation_id')->index();
            $t->enum('status', ['pending', 'success', 'retrying', 'failed'])->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->json('request_meta')->nullable();
            $t->json('response_meta')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('next_retry_at')->nullable();
            $t->timestamps();
        });
        Schema::create('data_consents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('employee_id')->constrained();
            $t->string('purpose');
            $t->string('policy_version');
            $t->boolean('granted');
            $t->timestamp('decided_at');
            $t->timestamp('expires_at')->nullable();
            $t->string('evidence_url')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['data_consents', 'integration_logs', 'employee_documents', 'workflow_actions', 'workflow_instances', 'learning_enrollments', 'learning_courses', 'performance_goals', 'performance_cycles', 'payroll_items', 'payroll_runs', 'leave_requests', 'leave_types', 'attendance_records', 'work_shifts', 'onboarding_cases', 'applications', 'candidates', 'recruitment_requisitions', 'employment_contracts', 'employee_credentials', 'employee_histories', 'employee_assignments', 'employees', 'job_positions', 'organization_units', 'cost_centers', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
