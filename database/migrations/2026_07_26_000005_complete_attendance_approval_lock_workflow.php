<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_adjustment_requests', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('requested_by');
            $table->unique(['company_id', 'idempotency_key'], 'attendance_adjustment_idempotency_unique');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->after('locked_by')->constrained('payroll_periods')->nullOnDelete();
            $table->index(['company_id', 'approval_status', 'calculation_status', 'work_date'], 'attendance_period_readiness');
        });
        Schema::create('attendance_approval_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->unsignedInteger('record_version');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'attendance_record_id', 'created_at'], 'attendance_approval_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_approval_histories');
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_period_readiness');
            $table->dropConstrainedForeignId('payroll_period_id');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
        Schema::table('attendance_adjustment_requests', function (Blueprint $table): void {
            $table->dropUnique('attendance_adjustment_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
