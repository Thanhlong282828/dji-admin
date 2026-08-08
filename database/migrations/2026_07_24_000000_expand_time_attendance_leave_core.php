<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table): void {
            $table->unique(['company_id', 'code'], 'work_shifts_company_code_unique');
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->unique(['company_id', 'code'], 'leave_types_company_code_unique');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->unsignedInteger('scheduled_minutes')->default(0)->after('source');
            $table->unsignedInteger('worked_minutes')->default(0)->after('scheduled_minutes');
            $table->unsignedInteger('break_minutes')->default(0)->after('worked_minutes');
            $table->unsignedInteger('late_minutes')->default(0)->after('break_minutes');
            $table->unsignedInteger('early_leave_minutes')->default(0)->after('late_minutes');
            $table->unsignedInteger('overtime_minutes')->default(0)->after('early_leave_minutes');
            $table->string('attendance_status', 30)->default('incomplete')->index()->after('overtime_minutes');
            $table->index(['work_shift_id', 'work_date'], 'attendance_shift_date_index');
        });

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->string('duration_type', 20)->default('full_day')->after('ends_at');
            $table->string('start_session', 20)->nullable()->after('duration_type');
            $table->string('end_session', 20)->nullable()->after('start_session');
            $table->timestamp('submitted_at')->nullable()->after('approved_by');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->text('review_comment')->nullable()->after('reviewed_at');
            $table->timestamp('cancelled_at')->nullable()->after('review_comment');
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('assignment_type', 20)->default('fixed');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('days_of_week')->nullable();
            $table->json('rotation_pattern')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'effective_from'], 'shift_assignment_lookup');
            $table->index(['work_shift_id', 'effective_from', 'effective_to'], 'shift_assignment_effective');
        });

        Schema::create('attendance_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_check_in_at')->nullable();
            $table->timestamp('requested_check_out_at')->nullable();
            $table->string('requested_source', 30)->nullable();
            $table->text('reason');
            $table->string('evidence_storage_key')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'status'], 'attendance_adjustment_tenant');
        });

        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            foreach (['opening', 'accrued', 'used', 'adjusted', 'carried', 'expired', 'reserved', 'remaining'] as $column) {
                $table->decimal($column, 9, 2)->default(0);
            }
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'employee_id', 'leave_type_id', 'year'], 'leave_balance_period_unique');
        });

        Schema::create('leave_balance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('amount', 9, 2);
            $table->decimal('balance_before', 9, 2);
            $table->decimal('balance_after', 9, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['leave_balance_id', 'created_at'], 'leave_ledger_timeline');
        });

        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('requested_minutes');
            $table->unsignedInteger('approved_minutes')->default(0);
            $table->text('reason');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'work_date'], 'overtime_tenant_employee_date');
        });

        Schema::create('company_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->boolean('recurring')->default(false);
            $table->string('country_code', 2)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'holiday_date'], 'company_holiday_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_holidays');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_balance_transactions');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('attendance_adjustment_requests');
        Schema::dropIfExists('employee_shift_assignments');

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropColumn(['duration_type', 'start_session', 'end_session', 'submitted_at', 'reviewed_at', 'review_comment', 'cancelled_at']);
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_shift_date_index');
            $table->dropColumn(['scheduled_minutes', 'worked_minutes', 'break_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'attendance_status']);
        });
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropUnique('leave_types_company_code_unique'));
        Schema::table('work_shifts', fn (Blueprint $table) => $table->dropUnique('work_shifts_company_code_unique'));
    }
};
