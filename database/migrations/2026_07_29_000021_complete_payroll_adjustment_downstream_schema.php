<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payroll_payment_batch_items MODIFY payroll_item_id BIGINT UNSIGNED NULL');
        Schema::table('payroll_payment_batch_items', function (Blueprint $table): void {
            $table->string('source_type', 40)->default('regular_payroll')->after('payroll_item_id');
            $table->unsignedBigInteger('adjustment_request_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('adjustment_run_id')->nullable()->after('adjustment_request_id');
            $table->unsignedBigInteger('source_payroll_item_id')->nullable()->after('adjustment_run_id');
            $table->unsignedInteger('source_calculation_version')->nullable()->after('source_payroll_item_id');
            $table->unsignedInteger('delta_calculation_version')->nullable()->after('source_calculation_version');
            $table->string('active_adjustment_payment_key', 191)->nullable()->after('active_intent_key');
            $table->index(['company_id', 'source_type'], 'ppbi_source_idx');
            $table->index('adjustment_request_id', 'ppbi_adj_req_idx');
            $table->index('adjustment_run_id', 'ppbi_adj_run_idx');
            $table->unique(['company_id', 'active_adjustment_payment_key'], 'ppbi_adj_active_uq');
            $table->foreign('adjustment_request_id', 'ppbi_adj_req_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
            $table->foreign('adjustment_run_id', 'ppbi_adj_run_fk')->references('id')->on('payroll_adjustment_runs')->restrictOnDelete();
            $table->foreign('source_payroll_item_id', 'ppbi_src_item_fk')->references('id')->on('payroll_items')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE payroll_accounting_journals MODIFY payroll_run_id BIGINT UNSIGNED NULL');
        Schema::table('payroll_accounting_journals', function (Blueprint $table): void {
            $table->string('journal_source_type', 40)->default('regular_payroll')->after('payroll_run_id');
            $table->unsignedBigInteger('adjustment_request_id')->nullable()->after('journal_source_type');
            $table->unsignedBigInteger('adjustment_run_id')->nullable()->after('adjustment_request_id');
            $table->unsignedBigInteger('source_payroll_run_id')->nullable()->after('adjustment_run_id');
            $table->unsignedBigInteger('source_payroll_item_id')->nullable()->after('source_payroll_run_id');
            $table->unsignedInteger('source_calculation_version')->nullable()->after('source_payroll_item_id');
            $table->unsignedInteger('delta_calculation_version')->nullable()->after('source_calculation_version');
            $table->string('active_adjustment_journal_key', 191)->nullable()->after('active_intent_key');
            $table->index(['company_id', 'journal_source_type'], 'paj_source_idx');
            $table->index('adjustment_request_id', 'paj_adj_req_idx');
            $table->index('adjustment_run_id', 'paj_adj_run_idx');
            $table->unique(['company_id', 'active_adjustment_journal_key'], 'paj_adj_active_uq');
            $table->foreign('adjustment_request_id', 'paj_adj_req_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
            $table->foreign('adjustment_run_id', 'paj_adj_run_fk')->references('id')->on('payroll_adjustment_runs')->restrictOnDelete();
            $table->foreign('source_payroll_run_id', 'paj_src_run_fk')->references('id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('source_payroll_item_id', 'paj_src_item_fk')->references('id')->on('payroll_items')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE payroll_accounting_journal_sources MODIFY payroll_item_id BIGINT UNSIGNED NULL');
        Schema::table('payroll_accounting_journal_sources', function (Blueprint $table): void {
            $table->string('downstream_source_type', 40)->default('regular_payroll')->after('source_type');
            $table->unsignedBigInteger('adjustment_request_id')->nullable();
            $table->unsignedBigInteger('adjustment_run_id')->nullable();
            $table->unsignedBigInteger('adjustment_component_id')->nullable();
            $table->unsignedBigInteger('source_payroll_run_id')->nullable();
            $table->unsignedBigInteger('source_payroll_item_id')->nullable();
            $table->bigInteger('historical_amount_minor')->nullable();
            $table->bigInteger('corrected_amount_minor')->nullable();
            $table->bigInteger('delta_amount_minor')->nullable();
            $table->unsignedInteger('source_calculation_version')->nullable();
            $table->unsignedInteger('delta_calculation_version')->nullable();
            $table->index(['company_id', 'downstream_source_type'], 'pajs_source_idx');
            $table->index('adjustment_request_id', 'pajs_adj_req_idx');
            $table->index('adjustment_component_id', 'pajs_adj_comp_idx');
            $table->foreign('adjustment_request_id', 'pajs_adj_req_fk')->references('id')->on('payroll_adjustment_requests')->restrictOnDelete();
            $table->foreign('adjustment_run_id', 'pajs_adj_run_fk')->references('id')->on('payroll_adjustment_runs')->restrictOnDelete();
            $table->foreign('adjustment_component_id', 'pajs_adj_comp_fk')->references('id')->on('payroll_adjustment_components')->restrictOnDelete();
            $table->foreign('source_payroll_run_id', 'pajs_src_run_fk')->references('id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('source_payroll_item_id', 'pajs_src_item_fk')->references('id')->on('payroll_items')->restrictOnDelete();
        });

        Schema::table('payroll_adjustment_payslips', function (Blueprint $table): void {
            $table->string('storage_disk', 30)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->char('snapshot_checksum', 64)->nullable();
            $table->unsignedInteger('generation_version')->default(1);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreign('generated_by', 'paps_generated_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('payroll_adjustment_downstream_intents', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('artifact_type', 60)->nullable();
            $table->unsignedBigInteger('payment_batch_item_id')->nullable();
            $table->unsignedBigInteger('accounting_journal_id')->nullable();
            $table->unsignedBigInteger('adjustment_statement_id')->nullable();
            $table->unsignedBigInteger('recovery_id')->nullable();
            $table->index(['company_id', 'intent_type', 'status'], 'padi_type_status_idx');
            $table->index(['artifact_type', 'downstream_record_id'], 'padi_artifact_idx');
            $table->unique(['company_id', 'idempotency_key'], 'padi_idempotency_uq');
            $table->foreign('payment_batch_item_id', 'padi_payment_item_fk')->references('id')->on('payroll_payment_batch_items')->restrictOnDelete();
            $table->foreign('accounting_journal_id', 'padi_journal_fk')->references('id')->on('payroll_accounting_journals')->restrictOnDelete();
            $table->foreign('adjustment_statement_id', 'padi_statement_fk')->references('id')->on('payroll_adjustment_payslips')->restrictOnDelete();
            $table->foreign('recovery_id', 'padi_recovery_fk')->references('id')->on('payroll_adjustment_recoveries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_adjustment_downstream_intents', function (Blueprint $table): void {
            $table->dropForeign('padi_payment_item_fk');
            $table->dropForeign('padi_journal_fk');
            $table->dropForeign('padi_statement_fk');
            $table->dropForeign('padi_recovery_fk');
            $table->dropIndex('padi_type_status_idx');
            $table->dropIndex('padi_artifact_idx');
            $table->dropUnique('padi_idempotency_uq');
            $table->dropColumn([
                'idempotency_key', 'attempt_count', 'last_attempt_at', 'processing_started_at',
                'completed_at', 'failed_at', 'failure_code', 'failure_message', 'artifact_type',
                'payment_batch_item_id', 'accounting_journal_id', 'adjustment_statement_id', 'recovery_id',
            ]);
        });

        Schema::table('payroll_adjustment_payslips', function (Blueprint $table): void {
            $table->dropForeign('paps_generated_by_fk');
            $table->dropColumn([
                'storage_disk', 'storage_path', 'file_name', 'mime_type', 'file_size', 'checksum',
                'snapshot_checksum', 'generation_version', 'generated_by', 'generated_at',
            ]);
        });

        Schema::table('payroll_accounting_journal_sources', function (Blueprint $table): void {
            $table->dropForeign('pajs_adj_req_fk');
            $table->dropForeign('pajs_adj_run_fk');
            $table->dropForeign('pajs_adj_comp_fk');
            $table->dropForeign('pajs_src_run_fk');
            $table->dropForeign('pajs_src_item_fk');
            $table->dropIndex('pajs_source_idx');
            $table->dropIndex('pajs_adj_req_idx');
            $table->dropIndex('pajs_adj_comp_idx');
            $table->dropColumn([
                'downstream_source_type', 'adjustment_request_id', 'adjustment_run_id',
                'adjustment_component_id', 'source_payroll_run_id', 'source_payroll_item_id',
                'historical_amount_minor', 'corrected_amount_minor', 'delta_amount_minor',
                'source_calculation_version', 'delta_calculation_version',
            ]);
        });
        DB::statement('ALTER TABLE payroll_accounting_journal_sources MODIFY payroll_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('payroll_accounting_journals', function (Blueprint $table): void {
            $table->dropForeign('paj_adj_req_fk');
            $table->dropForeign('paj_adj_run_fk');
            $table->dropForeign('paj_src_run_fk');
            $table->dropForeign('paj_src_item_fk');
            $table->dropIndex('paj_source_idx');
            $table->dropIndex('paj_adj_req_idx');
            $table->dropIndex('paj_adj_run_idx');
            $table->dropUnique('paj_adj_active_uq');
            $table->dropColumn([
                'journal_source_type', 'adjustment_request_id', 'adjustment_run_id',
                'source_payroll_run_id', 'source_payroll_item_id', 'source_calculation_version',
                'delta_calculation_version', 'active_adjustment_journal_key',
            ]);
        });
        DB::statement('ALTER TABLE payroll_accounting_journals MODIFY payroll_run_id BIGINT UNSIGNED NOT NULL');

        Schema::table('payroll_payment_batch_items', function (Blueprint $table): void {
            $table->dropForeign('ppbi_adj_req_fk');
            $table->dropForeign('ppbi_adj_run_fk');
            $table->dropForeign('ppbi_src_item_fk');
            $table->dropIndex('ppbi_source_idx');
            $table->dropIndex('ppbi_adj_req_idx');
            $table->dropIndex('ppbi_adj_run_idx');
            $table->dropUnique('ppbi_adj_active_uq');
            $table->dropColumn([
                'source_type', 'adjustment_request_id', 'adjustment_run_id', 'source_payroll_item_id',
                'source_calculation_version', 'delta_calculation_version', 'active_adjustment_payment_key',
            ]);
        });
        DB::statement('ALTER TABLE payroll_payment_batch_items MODIFY payroll_item_id BIGINT UNSIGNED NOT NULL');
    }
};
