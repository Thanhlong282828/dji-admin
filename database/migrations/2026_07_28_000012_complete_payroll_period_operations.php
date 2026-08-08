<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('lock_reason')->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 36)->nullable()->after('created_by');
            $table->text('note')->nullable()->after('idempotency_key');
            $table->unique(['company_id', 'idempotency_key'], 'payroll_period_idempotency_unique');
            $table->index(['company_id', 'status', 'period_start', 'id'], 'payroll_period_list_index');
        });

        Schema::create('payroll_period_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedInteger('version');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'payroll_period_id', 'id'], 'payroll_period_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_histories');
        Schema::table('payroll_periods', function (Blueprint $table): void {
            $table->dropIndex('payroll_period_list_index');
            $table->dropUnique('payroll_period_idempotency_unique');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['idempotency_key', 'note']);
        });
    }
};
