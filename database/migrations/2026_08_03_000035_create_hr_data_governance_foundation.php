<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_data_quality_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('entity_type');
            $t->enum('severity', ['info', 'warning', 'blocking']);
            $t->json('configuration')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('hr_data_quality_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->date('run_date');
            $t->enum('status', ['running', 'completed', 'failed']);
            $t->char('checksum', 64)->nullable();
            $t->string('idempotency_key', 128);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key'], 'hr_quality_run_company_key_unique');
        });
        Schema::create('hr_data_quality_issues', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('run_id')->constrained('hr_data_quality_runs')->cascadeOnDelete();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->string('rule_code');
            $t->enum('severity', ['info', 'warning', 'blocking']);
            $t->text('message');
            $t->char('checksum', 64);
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('resolution_key', 128)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['run_id', 'entity_type', 'entity_id', 'rule_code'], 'hr_quality_issue_identity_unique');
        });
        Schema::create('hr_governance_audit_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->string('action');
            $t->json('metadata');
            $t->char('checksum', 64);
            $t->string('idempotency_key', 128);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('hr_consent_governance_history', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('consent_id')->constrained('data_consents')->restrictOnDelete();
            $t->string('purpose');
            $t->unsignedInteger('version');
            $t->enum('action', ['granted', 'renewed', 'withdrawn', 'expired']);
            $t->timestamp('expires_at')->nullable();
            $t->char('checksum', 64);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['consent_id', 'version']);
        });
        Schema::create('hr_document_governance_history', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('document_id')->constrained('employee_documents')->restrictOnDelete();
            $t->enum('status', ['uploaded', 'verified', 'expired', 'archived']);
            $t->unsignedInteger('version');
            $t->date('retention_date')->nullable();
            $t->char('checksum', 64);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['document_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['hr_document_governance_history', 'hr_consent_governance_history', 'hr_governance_audit_events', 'hr_data_quality_issues', 'hr_data_quality_runs', 'hr_data_quality_rules'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
