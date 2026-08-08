<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iam_access_requests', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $t->string('status', 24)->default('requested');
            $t->text('reason');
            $t->uuid('idempotency_key')->unique();
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('provisioned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('provisioned_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status', 'created_at']);
        });
        Schema::create('iam_access_request_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('access_request_id')->constrained('iam_access_requests')->cascadeOnDelete();
            $t->string('entitlement_type', 16);
            $t->unsignedBigInteger('entitlement_id');
            $t->string('risk_level', 16)->default('standard');
            $t->timestamp('effective_from')->nullable();
            $t->timestamp('effective_to')->nullable();
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['access_request_id', 'entitlement_type', 'entitlement_id'], 'iam_request_item_unique');
        });
        Schema::create('iam_access_assignments', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('access_request_item_id')->nullable()->constrained('iam_access_request_items')->nullOnDelete();
            $t->string('entitlement_type', 16);
            $t->unsignedBigInteger('entitlement_id');
            $t->string('status', 16)->default('active');
            $t->boolean('privileged')->default(false);
            $t->text('reason')->nullable();
            $t->timestamp('effective_from');
            $t->timestamp('effective_to')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['company_id', 'user_id', 'entitlement_type', 'entitlement_id', 'status'], 'iam_assignment_state_unique');
            $t->index(['company_id', 'status', 'effective_to']);
        });
        Schema::create('iam_role_entitlements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->string('risk_level', 16)->default('standard');
            $t->boolean('privileged')->default(false);
            $t->foreignId('business_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('technical_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('review_frequency_days')->default(365);
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['company_id', 'role_id', 'permission_id'], 'iam_role_entitlement_unique');
        });
        Schema::create('iam_privileged_roles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->string('risk_level', 16)->default('privileged');
            $t->boolean('requires_two_step_approval')->default(true);
            $t->unsignedInteger('max_duration_hours')->default(8);
            $t->char('checksum', 64);
            $t->timestamps();
            $t->unique(['company_id', 'role_id']);
        });
        Schema::create('iam_sod_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('left_type', 16);
            $t->unsignedBigInteger('left_id');
            $t->string('right_type', 16);
            $t->unsignedBigInteger('right_id');
            $t->string('severity', 16)->default('blocking');
            $t->boolean('active')->default(true);
            $t->char('checksum', 64);
            $t->timestamps();
        });
        Schema::create('iam_sod_violations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sod_rule_id')->constrained('iam_sod_rules')->restrictOnDelete();
            $t->foreignId('access_request_id')->nullable()->constrained('iam_access_requests')->nullOnDelete();
            $t->string('status', 16)->default('open');
            $t->char('fingerprint', 64)->unique();
            $t->char('checksum', 64);
            $t->timestamps();
        });
        Schema::create('iam_access_reviews', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('scope_type', 20);
            $t->unsignedBigInteger('scope_id')->nullable();
            $t->string('status', 20)->default('draft');
            $t->date('due_date');
            $t->unsignedInteger('version')->default(1);
            $t->char('snapshot_checksum', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('iam_access_review_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('access_review_id')->constrained('iam_access_reviews')->cascadeOnDelete();
            $t->foreignId('access_assignment_id')->constrained('iam_access_assignments')->restrictOnDelete();
            $t->string('decision', 16)->default('pending');
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->char('snapshot_checksum', 64);
            $t->timestamps();
            $t->unique(['access_review_id', 'access_assignment_id'], 'iam_review_assignment_unique');
        });
        Schema::create('iam_certifications', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('access_review_id')->constrained('iam_access_reviews')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->char('checksum', 64);
            $t->foreignId('certified_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('certified_at');
            $t->timestamps();
            $t->unique(['access_review_id', 'version']);
        });
        Schema::create('iam_governance_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('aggregate_type', 32);
            $t->unsignedBigInteger('aggregate_id');
            $t->string('action', 40);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->char('checksum', 64);
            $t->timestamp('occurred_at');
            $t->index(['company_id', 'aggregate_type', 'aggregate_id'], 'iam_history_aggregate_index');
        });
        Schema::create('iam_outbox', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('event_type');
            $t->string('aggregate_type', 32);
            $t->unsignedBigInteger('aggregate_id');
            $t->json('payload');
            $t->char('checksum', 64);
            $t->uuid('idempotency_key')->unique();
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at');
            $t->timestamp('created_at');
            $t->index(['published_at', 'available_at']);
        });
    }

    public function down(): void
    {
        foreach (['iam_outbox', 'iam_governance_histories', 'iam_certifications', 'iam_access_review_items', 'iam_access_reviews', 'iam_sod_violations', 'iam_sod_rules', 'iam_privileged_roles', 'iam_role_entitlements', 'iam_access_assignments', 'iam_access_request_items', 'iam_access_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
