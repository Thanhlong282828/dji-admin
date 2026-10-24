<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_acceptances', function (Blueprint $table): void {
            $table->string('reversal_idempotency_key', 120)->nullable()->after('accounting_journal_id');
            $table->foreignId('reversal_adjustment_id')->nullable()->after('reversal_idempotency_key')->constrained('accounting_adjustment_requests')->restrictOnDelete();
            $table->foreignId('reversal_journal_id')->nullable()->after('reversal_adjustment_id')->constrained('payroll_accounting_journals')->restrictOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversal_journal_id');
            $table->foreignId('reversal_requested_by')->nullable()->after('reversal_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->after('reversal_requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversal_requested_at')->nullable()->after('reversed_by');
            $table->timestamp('reversed_at')->nullable()->after('reversal_requested_at');
            $table->unique(['company_id', 'reversal_idempotency_key'], 'service_acceptance_reversal_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('service_acceptances', function (Blueprint $table): void {
            $table->dropUnique('service_acceptance_reversal_key_unique');
            $table->dropConstrainedForeignId('reversal_adjustment_id');
            $table->dropConstrainedForeignId('reversal_journal_id');
            $table->dropConstrainedForeignId('reversal_requested_by');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversal_idempotency_key', 'reversal_reason', 'reversal_requested_at', 'reversed_at']);
        });
    }
};
