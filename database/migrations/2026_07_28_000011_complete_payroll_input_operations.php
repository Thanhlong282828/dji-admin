<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_input_snapshots', 'idempotency_key')) {
            Schema::table('payroll_input_snapshots', function (Blueprint $table): void {
                $table->uuid('idempotency_key')->nullable()->after('status');
                $table->text('note')->nullable()->after('idempotency_key');
                $table->unsignedInteger('employee_count')->default(0)->after('note');
                $table->unsignedInteger('attendance_record_count')->default(0)->after('employee_count');
                $table->unsignedInteger('leave_record_count')->default(0)->after('attendance_record_count');
                $table->unsignedInteger('overtime_record_count')->default(0)->after('leave_record_count');
                $table->unsignedInteger('blocking_issue_count')->default(0)->after('overtime_record_count');
                $table->unsignedInteger('warning_issue_count')->default(0)->after('blocking_issue_count');
                $table->unsignedBigInteger('scheduled_minutes')->default(0)->after('warning_issue_count');
                $table->unsignedBigInteger('worked_minutes')->default(0)->after('scheduled_minutes');
                $table->unsignedBigInteger('paid_leave_minutes')->default(0)->after('worked_minutes');
                $table->unsignedBigInteger('unpaid_leave_minutes')->default(0)->after('paid_leave_minutes');
                $table->unsignedBigInteger('approved_overtime_minutes')->default(0)->after('unpaid_leave_minutes');
                $table->timestamp('validated_at')->nullable()->after('generated_at');
                $table->timestamp('superseded_at')->nullable()->after('validated_at');
                $table->foreignId('superseded_by_snapshot_id')->nullable()->after('superseded_at')->constrained('payroll_input_snapshots')->nullOnDelete();
                $table->unique(['company_id', 'payroll_period_id', 'idempotency_key'], 'payroll_input_idempotency_unique');
                $table->index(['company_id', 'status', 'generated_at'], 'payroll_input_list_idx');
            });
        }

        if (! Schema::hasColumn('payroll_employee_inputs', 'source_hash')) {
            Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
                $table->string('employee_number_snapshot')->nullable()->after('employee_id');
                $table->string('employee_name_snapshot')->nullable()->after('employee_number_snapshot');
                $table->foreignId('organization_unit_id')->nullable()->after('assignment_id')->constrained('organization_units')->nullOnDelete();
                $table->string('organization_unit_snapshot')->nullable()->after('organization_unit_id');
                $table->foreignId('job_position_id')->nullable()->after('organization_unit_snapshot')->constrained('job_positions')->nullOnDelete();
                $table->string('job_position_snapshot')->nullable()->after('job_position_id');
                $table->date('employment_start')->nullable()->after('job_position_snapshot');
                $table->date('employment_end')->nullable()->after('employment_start');
                $table->unsignedInteger('weekday_overtime_minutes')->default(0)->after('approved_overtime_minutes');
                $table->unsignedInteger('weekend_overtime_minutes')->default(0)->after('weekday_overtime_minutes');
                $table->unsignedInteger('holiday_overtime_minutes')->default(0)->after('weekend_overtime_minutes');
                $table->unsignedInteger('attendance_record_count')->default(0)->after('absence_minutes');
                $table->unsignedInteger('leave_record_count')->default(0)->after('attendance_record_count');
                $table->unsignedInteger('overtime_record_count')->default(0)->after('leave_record_count');
                $table->string('input_status', 20)->default('ready')->after('overtime_record_count');
                $table->char('source_hash', 64)->nullable()->after('input_status');
                $table->index(['company_id', 'payroll_input_snapshot_id', 'input_status'], 'payroll_employee_input_list_idx');
            });
        }

        if (! Schema::hasTable('payroll_input_validation_issues')) {
            Schema::create('payroll_input_validation_issues', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('payroll_input_snapshot_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('code', 80);
                $table->string('severity', 20);
                $table->string('scope', 30)->default('snapshot');
                $table->boolean('is_blocking')->default(false);
                $table->string('safe_message');
                $table->json('context')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'payroll_input_snapshot_id', 'is_blocking'], 'payroll_input_issue_scope_idx');
                $table->foreign('payroll_input_snapshot_id', 'pi_issue_snapshot_fk')->references('id')->on('payroll_input_snapshots')->cascadeOnDelete();
                $table->foreign('employee_id', 'pi_issue_employee_fk')->references('id')->on('employees')->nullOnDelete();
            });
        } else {
            $foreignKeys = array_column(Schema::getForeignKeys('payroll_input_validation_issues'), 'name');
            Schema::table('payroll_input_validation_issues', function (Blueprint $table) use ($foreignKeys): void {
                if (! in_array('pi_issue_snapshot_fk', $foreignKeys, true)) {
                    $table->foreign('payroll_input_snapshot_id', 'pi_issue_snapshot_fk')->references('id')->on('payroll_input_snapshots')->cascadeOnDelete();
                }
                if (! in_array('pi_issue_employee_fk', $foreignKeys, true)) {
                    $table->foreign('employee_id', 'pi_issue_employee_fk')->references('id')->on('employees')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('payroll_input_histories')) {
            Schema::create('payroll_input_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('payroll_input_snapshot_id');
                $table->string('action', 40);
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->uuid('idempotency_key');
                $table->json('safe_context')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['payroll_input_snapshot_id', 'action', 'idempotency_key'], 'payroll_input_history_idempotency_unique');
                $table->foreign('payroll_input_snapshot_id', 'pi_history_snapshot_fk')->references('id')->on('payroll_input_snapshots')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_input_histories');
        Schema::dropIfExists('payroll_input_validation_issues');
        Schema::table('payroll_employee_inputs', function (Blueprint $table): void {
            $table->dropIndex('payroll_employee_input_list_idx');
            $table->dropConstrainedForeignId('organization_unit_id');
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropColumn(['employee_number_snapshot', 'employee_name_snapshot', 'organization_unit_snapshot', 'job_position_snapshot', 'employment_start', 'employment_end', 'weekday_overtime_minutes', 'weekend_overtime_minutes', 'holiday_overtime_minutes', 'attendance_record_count', 'leave_record_count', 'overtime_record_count', 'input_status', 'source_hash']);
        });
        Schema::table('payroll_input_snapshots', function (Blueprint $table): void {
            $table->dropUnique('payroll_input_idempotency_unique');
            $table->dropIndex('payroll_input_list_idx');
            $table->dropConstrainedForeignId('superseded_by_snapshot_id');
            $table->dropColumn(['idempotency_key', 'note', 'employee_count', 'attendance_record_count', 'leave_record_count', 'overtime_record_count', 'blocking_issue_count', 'warning_issue_count', 'scheduled_minutes', 'worked_minutes', 'paid_leave_minutes', 'unpaid_leave_minutes', 'approved_overtime_minutes', 'validated_at', 'superseded_at']);
        });
    }
};
