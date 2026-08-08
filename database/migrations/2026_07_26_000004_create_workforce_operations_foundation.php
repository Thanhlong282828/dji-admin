<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_calendars')) {
            $this->resumeAfterMysqlDdlFailure();

            return;
        }
        Schema::create('work_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('timezone', 80);
            $table->unsignedInteger('weekly_work_minutes');
            $table->string('status', 20)->default('active')->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'effective_from', 'effective_to']);
        });
        Schema::create('work_calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_calendar_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_working_day');
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->foreignId('default_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['work_calendar_id', 'weekday']);
        });
        Schema::create('employee_work_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('timezone', 80);
            $table->foreignId('work_calendar_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->unsignedInteger('weekly_minutes');
            $table->unsignedInteger('daily_minutes');
            $table->unsignedSmallInteger('fte_basis_points')->default(10000);
            $table->boolean('attendance_required')->default(true);
            $table->boolean('overtime_eligible')->default(true);
            $table->boolean('leave_eligible')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'effective_from', 'effective_to'], 'work_profile_effective');
        });
        Schema::table('work_shifts', function (Blueprint $table): void {
            $table->string('timezone', 80)->default('Asia/Ho_Chi_Minh')->after('name');
            $table->unsignedInteger('planned_work_minutes')->default(480)->after('crosses_midnight');
            $table->unsignedInteger('grace_in_minutes')->default(0)->after('planned_work_minutes');
            $table->unsignedInteger('grace_out_minutes')->default(0)->after('grace_in_minutes');
            $table->string('rounding_rule', 20)->default('none')->after('grace_out_minutes');
            $table->unsignedInteger('minimum_half_day_minutes')->default(240)->after('rounding_rule');
            $table->unsignedInteger('minimum_full_day_minutes')->default(480)->after('minimum_half_day_minutes');
            $table->string('status', 20)->default('active')->index()->after('minimum_full_day_minutes');
            $table->unsignedInteger('version')->default(1)->after('status');
        });
        Schema::create('shift_break_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('kind', 20);
            $table->boolean('is_paid')->default(false);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('minimum_work_minutes')->default(0);
            $table->timestamps();
            $table->unique(['work_shift_id', 'code']);
        });
        Schema::table('employee_shift_assignments', function (Blueprint $table): void {
            $table->string('source', 30)->default('manual')->after('assignment_type');
            $table->boolean('is_primary')->default(true)->after('source');
            $table->unsignedInteger('version')->default(1)->after('is_primary');
        });
        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->date('local_date');
            $table->string('event_type', 30);
            $table->string('source', 30);
            $table->string('device_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('deduplication_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invalidated_at')->nullable();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'deduplication_key']);
            $table->index(['company_id', 'employee_id', 'local_date', 'occurred_at'], 'attendance_event_timeline');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
            $table->string('presence_status', 30)->default('unapproved')->after('attendance_status');
            $table->string('approval_status', 20)->default('draft')->index()->after('presence_status');
            $table->string('calculation_status', 20)->default('pending')->after('approval_status');
            $table->json('anomalies')->nullable()->after('calculation_status');
            $table->unsignedInteger('calculation_version')->default(1)->after('anomalies');
            $table->unsignedInteger('version')->default(1)->after('calculation_version');
            $table->unique(['company_id', 'employee_id', 'work_date'], 'attendance_daily_unique');
        });
        Schema::table('attendance_adjustment_requests', function (Blueprint $table): void {
            $table->unsignedInteger('requested_break_minutes')->nullable()->after('requested_check_out_at');
            $table->unsignedInteger('expected_record_version')->after('reason');
            $table->unsignedInteger('version')->default(1)->after('status');
        });
        Schema::create('attendance_adjustment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_adjustment_request_id');
            $table->string('action', 30);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('attendance_adjustment_request_id', 'attendance_adjustment_history_request_fk')->references('id')->on('attendance_adjustment_requests')->cascadeOnDelete();
        });
        Schema::table('company_holidays', function (Blueprint $table): void {
            $table->foreignId('work_calendar_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_working_override')->default(false)->after('is_paid');
            $table->string('region', 80)->nullable()->after('country_code');
            $table->string('source', 30)->default('manual')->after('region');
        });
        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('unit', 20)->default('minutes');
            $table->string('accrual_method', 30);
            $table->unsignedInteger('annual_entitlement_minutes')->default(0);
            $table->json('carry_forward_rule')->nullable();
            $table->unsignedInteger('maximum_carry_forward_minutes')->default(0);
            $table->boolean('negative_balance_allowed')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->json('approval_workflow')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('employee_leave_policy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_policy_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'effective_from'], 'leave_policy_assignment_effective');
        });
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->after('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('timezone', 80)->default('Asia/Ho_Chi_Minh')->after('ends_at');
            $table->unsignedInteger('requested_minutes')->default(0)->after('timezone');
            $table->foreignId('current_approver_id')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('withdrawn_at')->nullable()->after('cancelled_at');
            $table->string('attachment_storage_key')->nullable()->after('withdrawn_at');
            $table->string('attachment_mime_type')->nullable()->after('attachment_storage_key');
            $table->string('attachment_checksum', 64)->nullable()->after('attachment_mime_type');
        });
        Schema::table('leave_balance_transactions', function (Blueprint $table): void {
            $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->after('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
            $table->date('effective_date')->after('leave_request_id');
            $table->integer('minutes')->after('transaction_type');
            $table->string('reference_type', 80)->nullable()->after('reference');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->uuid('idempotency_key')->after('description')->unique();
            $table->index(['company_id', 'employee_id', 'leave_policy_id', 'effective_date'], 'leave_ledger_balance');
        });
        Schema::create('overtime_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedInteger('minimum_minutes')->default(0);
            $table->string('rounding_rule', 20)->default('none');
            $table->unsignedInteger('daily_limit_minutes')->default(240);
            $table->unsignedInteger('weekly_limit_minutes')->default(720);
            $table->boolean('requires_preapproval')->default(true);
            $table->json('eligible_day_types');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('employee_overtime_policy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('overtime_policy_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->foreignId('overtime_policy_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('actual_minutes')->default(0)->after('approved_minutes');
            $table->unsignedInteger('payable_minutes')->default(0)->after('actual_minutes');
            $table->foreignId('approver_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('approver_id');
        });
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('cutoff_at');
            $table->date('payment_date');
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lock_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'period_start', 'period_end']);
        });
        Schema::create('payroll_input_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('snapshot_version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'snapshot_version'], 'payroll_snapshot_period_version_unique');
        });
        Schema::create('payroll_employee_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_input_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('employee_assignments')->nullOnDelete();
            foreach (['scheduled_minutes', 'worked_minutes', 'paid_leave_minutes', 'unpaid_leave_minutes', 'approved_overtime_minutes', 'late_minutes', 'early_leave_minutes', 'absence_minutes'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->unsignedSmallInteger('fte_basis_points')->default(10000);
            $table->json('readiness_issues')->nullable();
            $table->timestamps();
            $table->unique(['payroll_input_snapshot_id', 'employee_id'], 'payroll_snapshot_employee_unique');
        });
    }

    private function resumeAfterMysqlDdlFailure(): void
    {
        $foreignNames = array_column(Schema::getForeignKeys('attendance_adjustment_histories'), 'name');
        Schema::table('attendance_adjustment_histories', function (Blueprint $table) use ($foreignNames): void {
            if (! in_array('attendance_adjustment_history_request_fk', $foreignNames, true)) {
                $table->foreign('attendance_adjustment_request_id', 'attendance_adjustment_history_request_fk')->references('id')->on('attendance_adjustment_requests')->cascadeOnDelete();
            }
            if (! in_array('attendance_adjustment_histories_actor_id_foreign', $foreignNames, true) && ! in_array('attendance_adjustment_history_actor_fk', $foreignNames, true)) {
                $table->foreign('actor_id', 'attendance_adjustment_history_actor_fk')->references('id')->on('users')->restrictOnDelete();
            }
        });
        if (! Schema::hasColumn('company_holidays', 'work_calendar_id')) {
            Schema::table('company_holidays', function (Blueprint $table): void {
                $table->foreignId('work_calendar_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_working_override')->default(false)->after('is_paid');
                $table->string('region', 80)->nullable()->after('country_code');
                $table->string('source', 30)->default('manual')->after('region');
            });
        }
        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->string('unit', 20)->default('minutes');
                $table->string('accrual_method', 30);
                $table->unsignedInteger('annual_entitlement_minutes')->default(0);
                $table->json('carry_forward_rule')->nullable();
                $table->unsignedInteger('maximum_carry_forward_minutes')->default(0);
                $table->boolean('negative_balance_allowed')->default(false);
                $table->boolean('requires_attachment')->default(false);
                $table->json('approval_workflow')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['company_id', 'code']);
            });
        }
        if (! Schema::hasTable('employee_leave_policy_assignments')) {
            Schema::create('employee_leave_policy_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_policy_id')->constrained()->restrictOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'employee_id', 'effective_from'], 'leave_policy_assignment_effective');
            });
        }
        if (! Schema::hasColumn('leave_requests', 'company_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_policy_id')->nullable()->after('leave_type_id')->constrained()->restrictOnDelete();
                $table->string('timezone', 80)->default('Asia/Ho_Chi_Minh')->after('ends_at');
                $table->unsignedInteger('requested_minutes')->default(0)->after('timezone');
                $table->foreignId('current_approver_id')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
                $table->unsignedInteger('version')->default(1)->after('status');
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
                $table->timestamp('withdrawn_at')->nullable()->after('cancelled_at');
                $table->string('attachment_storage_key')->nullable()->after('withdrawn_at');
                $table->string('attachment_mime_type')->nullable()->after('attachment_storage_key');
                $table->string('attachment_checksum', 64)->nullable()->after('attachment_mime_type');
            });
        }
        if (! Schema::hasColumn('leave_balance_transactions', 'company_id')) {
            Schema::table('leave_balance_transactions', function (Blueprint $table): void {
                $table->foreignId('company_id')->after('id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->after('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('leave_policy_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
                $table->date('effective_date')->after('leave_request_id');
                $table->integer('minutes')->after('transaction_type');
                $table->string('reference_type', 80)->nullable()->after('reference');
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
                $table->uuid('idempotency_key')->after('description')->unique();
                $table->index(['company_id', 'employee_id', 'leave_policy_id', 'effective_date'], 'leave_ledger_balance');
            });
        }
        if (! Schema::hasTable('overtime_policies')) {
            Schema::create('overtime_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->unsignedInteger('minimum_minutes')->default(0);
                $table->string('rounding_rule', 20)->default('none');
                $table->unsignedInteger('daily_limit_minutes')->default(240);
                $table->unsignedInteger('weekly_limit_minutes')->default(720);
                $table->boolean('requires_preapproval')->default(true);
                $table->json('eligible_day_types');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['company_id', 'code']);
            });
            Schema::create('employee_overtime_policy_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('overtime_policy_id')->constrained()->restrictOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->timestamps();
            });
            Schema::table('overtime_requests', function (Blueprint $table): void {
                $table->foreignId('overtime_policy_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('actual_minutes')->default(0)->after('approved_minutes');
                $table->unsignedInteger('payable_minutes')->default(0)->after('actual_minutes');
                $table->foreignId('approver_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->unsignedInteger('version')->default(1)->after('approver_id');
            });
        }
        if (! Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->date('period_start');
                $table->date('period_end');
                $table->timestamp('cutoff_at');
                $table->date('payment_date');
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('lock_reason')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'period_start', 'period_end']);
            });
        }
        if (! Schema::hasTable('payroll_input_snapshots')) {
            Schema::create('payroll_input_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('snapshot_version')->default(1);
                $table->string('status', 20)->default('draft');
                $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('generated_at');
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
                $table->unique(['payroll_period_id', 'snapshot_version'], 'payroll_snapshot_period_version_unique');
            });
        } elseif (! in_array('payroll_snapshot_period_version_unique', array_column(Schema::getIndexes('payroll_input_snapshots'), 'name'), true)) {
            Schema::table('payroll_input_snapshots', function (Blueprint $table): void {
                $table->unique(['payroll_period_id', 'snapshot_version'], 'payroll_snapshot_period_version_unique');
            });
        }
        if (! Schema::hasTable('payroll_employee_inputs')) {
            Schema::create('payroll_employee_inputs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('payroll_input_snapshot_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained('employment_contracts')->nullOnDelete();
                $table->foreignId('assignment_id')->nullable()->constrained('employee_assignments')->nullOnDelete();
                foreach (['scheduled_minutes', 'worked_minutes', 'paid_leave_minutes', 'unpaid_leave_minutes', 'approved_overtime_minutes', 'late_minutes', 'early_leave_minutes', 'absence_minutes'] as $column) {
                    $table->unsignedInteger($column)->default(0);
                }
                $table->unsignedSmallInteger('fte_basis_points')->default(10000);
                $table->json('readiness_issues')->nullable();
                $table->timestamps();
                $table->unique(['payroll_input_snapshot_id', 'employee_id'], 'payroll_snapshot_employee_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_inputs');
        Schema::dropIfExists('payroll_input_snapshots');
        Schema::dropIfExists('payroll_periods');
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('overtime_policy_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn(['actual_minutes', 'payable_minutes', 'version']);
        });
        Schema::dropIfExists('employee_overtime_policy_assignments');
        Schema::dropIfExists('overtime_policies');
        Schema::table('leave_balance_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('leave_policy_id');
            $table->dropColumn(['effective_date', 'minutes', 'reference_type', 'reference_id', 'idempotency_key']);
        });
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('leave_policy_id');
            $table->dropConstrainedForeignId('current_approver_id');
            $table->dropColumn(['timezone', 'requested_minutes', 'version', 'approved_at', 'rejected_at', 'withdrawn_at', 'attachment_storage_key', 'attachment_mime_type', 'attachment_checksum']);
        });
        Schema::dropIfExists('employee_leave_policy_assignments');
        Schema::dropIfExists('leave_policies');
        Schema::table('company_holidays', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_calendar_id');
            $table->dropColumn(['is_working_override', 'region', 'source']);
        });
        Schema::dropIfExists('attendance_adjustment_histories');
        Schema::table('attendance_adjustment_requests', function (Blueprint $table): void {
            $table->dropColumn(['requested_break_minutes', 'expected_record_version', 'version']);
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_daily_unique');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['presence_status', 'approval_status', 'calculation_status', 'anomalies', 'calculation_version', 'version']);
        });
        Schema::dropIfExists('attendance_events');
        Schema::table('employee_shift_assignments', function (Blueprint $table): void {
            $table->dropColumn(['source', 'is_primary', 'version']);
        });
        Schema::dropIfExists('shift_break_rules');
        Schema::table('work_shifts', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'planned_work_minutes', 'grace_in_minutes', 'grace_out_minutes', 'rounding_rule', 'minimum_half_day_minutes', 'minimum_full_day_minutes', 'status', 'version']);
        });
        Schema::dropIfExists('employee_work_profiles');
        Schema::dropIfExists('work_calendar_days');
        Schema::dropIfExists('work_calendars');
    }
};
