<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payslip_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'payroll_run_id', 'status']);
        });
        Schema::create('payroll_payslips', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('calculation_version');
            $table->unsignedInteger('payslip_version')->default(1);
            $table->string('document_type', 20)->default('official');
            $table->string('template_version', 50)->default('payroll-payslip-v1');
            $table->string('status', 30)->default('pending');
            $table->string('storage_disk', 30)->default('local');
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->uuid('idempotency_key');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 1000)->nullable();
            $table->string('delivery_status', 30)->default('not_delivered');
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_item_id', 'calculation_version', 'payslip_version', 'document_type'], 'payslip_version_unique');
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'status', 'generated_at']);
        });
        Schema::create('payroll_payslip_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_payslip_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('snapshot_payload');
            $table->char('payload_checksum', 64);
            $table->timestamps();
        });
        Schema::create('payroll_payslip_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_payslip_id')->constrained()->restrictOnDelete();
            $table->string('actor_type', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 255)->nullable();
            $table->timestamp('accessed_at');
            $table->index(['company_id', 'payroll_payslip_id', 'accessed_at'], 'payslip_access_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payslip_access_logs');
        Schema::dropIfExists('payroll_payslip_snapshots');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_payslip_batches');
    }
};
