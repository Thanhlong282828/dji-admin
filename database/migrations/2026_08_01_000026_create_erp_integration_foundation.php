<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_connections', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name', 160);
            $t->string('provider', 40);
            $t->text('credential_reference')->nullable();
            $t->string('status', 20)->default('active');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'name'], 'erp_conn_company_name_uq');
            $t->index(['company_id', 'provider', 'status'], 'erp_conn_scope_idx');
        });
        Schema::create('erp_mapping_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('erp_connection_id')->constrained('erp_connections')->cascadeOnDelete();
            $t->string('source_account_code', 50);
            $t->string('external_account_code', 100);
            $t->json('dimension_mapping')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->unique(['erp_connection_id', 'source_account_code'], 'erp_map_conn_account_uq');
            $t->index(['company_id', 'status'], 'erp_map_scope_idx');
        });
        Schema::create('erp_exports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('erp_connection_id')->constrained('erp_connections')->restrictOnDelete();
            $t->foreignId('accounting_journal_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $t->string('provider', 40);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('export_version')->default(1);
            $t->uuid('idempotency_key');
            $t->string('active_intent_key', 160)->nullable();
            $t->char('payload_checksum', 64);
            $t->uuid('external_reference')->nullable();
            $t->json('journal_snapshot');
            $t->string('artifact_disk', 30)->nullable();
            $t->string('artifact_path')->nullable();
            $t->string('artifact_name')->nullable();
            $t->string('artifact_mime', 100)->nullable();
            $t->unsignedBigInteger('artifact_size')->nullable();
            $t->char('artifact_checksum', 64)->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('validated_at')->nullable();
            $t->timestamp('exported_at')->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->string('failure_code', 100)->nullable();
            $t->string('failure_message', 500)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key'], 'erp_export_idem_uq');
            $t->unique('active_intent_key', 'erp_export_active_uq');
            $t->index(['company_id', 'status', 'created_at'], 'erp_export_scope_idx');
        });
        Schema::create('erp_export_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('erp_export_id')->constrained('erp_exports')->cascadeOnDelete();
            $t->foreignId('accounting_journal_line_id')->constrained('payroll_accounting_journal_lines')->restrictOnDelete();
            $t->unsignedInteger('line_number');
            $t->string('source_account_code', 50);
            $t->string('external_account_code', 100);
            $t->string('description', 255);
            $t->unsignedBigInteger('debit_minor')->default(0);
            $t->unsignedBigInteger('credit_minor')->default(0);
            $t->char('currency', 3);
            $t->json('dimension_snapshot')->nullable();
            $t->char('item_checksum', 64);
            $t->timestamps();
            $t->unique(['erp_export_id', 'accounting_journal_line_id'], 'erp_item_line_uq');
            $t->index(['company_id', 'erp_export_id'], 'erp_item_scope_idx');
        });
        Schema::create('erp_export_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('erp_export_id')->constrained('erp_exports')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20)->nullable();
            $t->string('message', 500)->nullable();
            $t->json('safe_context')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['erp_export_id', 'created_at'], 'erp_log_export_idx');
        });
        Schema::create('erp_access_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('erp_export_id')->constrained('erp_exports')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50);
            $t->string('result', 30);
            $t->timestamp('accessed_at');
            $t->index(['erp_export_id', 'action', 'accessed_at'], 'erp_access_export_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_access_logs');
        Schema::dropIfExists('erp_export_logs');
        Schema::dropIfExists('erp_export_items');
        Schema::dropIfExists('erp_exports');
        Schema::dropIfExists('erp_mapping_rules');
        Schema::dropIfExists('erp_connections');
    }
};
