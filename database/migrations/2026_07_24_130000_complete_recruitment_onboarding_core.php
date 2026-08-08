<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_requisitions', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('id');
            $table->string('priority', 20)->default('normal')->index();
            $table->unsignedInteger('filled_hires')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->string('resume_storage_key')->nullable();
            $table->string('resume_mime_type', 150)->nullable();
            $table->unsignedBigInteger('resume_size')->nullable();
            $table->char('resume_checksum', 64)->nullable()->index();
            $table->string('resume_scan_status', 20)->nullable()->index();
            $table->unsignedInteger('resume_version')->default(0);
            $table->foreignId('merged_into_id')->nullable()->constrained('candidates')->nullOnDelete();
        });

        DB::statement('UPDATE candidates c JOIN applications a ON a.candidate_id = c.id JOIN recruitment_requisitions r ON r.id = a.recruitment_requisition_id JOIN job_positions j ON j.id = r.job_position_id SET c.company_id = j.company_id WHERE c.company_id IS NULL');

        Schema::table('candidates', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index(['company_id', 'status', 'created_at'], 'candidate_company_status_created_idx');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->text('stage_reason')->nullable();
            $table->foreignId('hired_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->uuid('hiring_reference')->nullable()->unique();
        });

        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('blocked_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('probation_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('probation_outcome', 30)->nullable();
            $table->text('probation_notes')->nullable();
        });

        DB::statement('UPDATE onboarding_cases o JOIN employees e ON e.id = o.employee_id SET o.company_id = e.company_id WHERE o.company_id IS NULL');

        Schema::table('onboarding_cases', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index(['company_id', 'status', 'start_date'], 'onboarding_company_status_start_idx');
        });

        Schema::create('application_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['application_id', 'created_at'], 'application_stage_timeline_idx');
        });

        Schema::create('application_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('title');
            $table->string('interview_type', 20);
            $table->string('status', 20)->default('scheduled')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 100);
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('scheduled_by')->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['company_id', 'status', 'starts_at'], 'interview_company_status_start_idx');
            $table->index(['application_id', 'round_number'], 'interview_application_round_idx');
        });

        Schema::create('application_interviewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('interviewer');
            $table->string('response_status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['application_interview_id', 'user_id'], 'interview_interviewer_unique');
        });

        Schema::create('interview_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('recommendation', 30);
            $table->json('scorecard')->nullable();
            $table->text('strengths')->nullable();
            $table->text('concerns')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['application_interview_id', 'interviewer_user_id'], 'interview_evaluation_unique');
        });

        Schema::create('candidate_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('offer_number');
            $table->foreignId('job_position_id')->constrained();
            $table->foreignId('organization_unit_id')->nullable()->constrained();
            $table->string('employment_type', 30);
            $table->decimal('base_salary', 18, 2);
            $table->char('currency', 3);
            $table->date('start_date');
            $table->date('probation_end_date')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('terms')->nullable();
            $table->string('document_storage_key')->nullable();
            $table->string('document_mime_type', 150)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->char('document_checksum', 64)->nullable();
            $table->string('scan_status', 20)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdraw_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'offer_number'], 'candidate_offer_number_unique');
            $table->index(['application_id', 'status'], 'candidate_offer_application_status_idx');
        });

        Schema::create('onboarding_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_case_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('code', 100);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamp('due_at')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['onboarding_case_id', 'code'], 'onboarding_checklist_code_unique');
        });

        Schema::create('onboarding_provisioning_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_case_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE recruitment_requisitions MODIFY status ENUM('draft','approval','approved','open','paused','filled','cancelled','rejected') NOT NULL");
            DB::statement("ALTER TABLE onboarding_cases MODIFY status ENUM('draft','ready','preboarding','in_progress','blocked','probation_review','completed','cancelled') NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['onboarding_provisioning_items', 'onboarding_checklist_items', 'candidate_offers', 'interview_evaluations', 'application_interviewers', 'application_interviews', 'application_stage_histories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
