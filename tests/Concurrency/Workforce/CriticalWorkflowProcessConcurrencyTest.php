<?php

namespace Tests\Concurrency\Workforce;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class CriticalWorkflowProcessConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_attendance_adjustment_decision_races_have_one_transition(): void
    {
        foreach ([['adjustment.approve', 'adjustment.approve'], ['adjustment.approve', 'adjustment.reject'], ['adjustment.approve', 'adjustment.cancel']] as [$actionA, $actionB]) {
            $context = $this->context('ADJ');
            try {
                $record = $this->attendanceRecord($context, 'draft');
                $adjustment = $this->adjustment($context, $record, 'submitted');
                $base = ['company_id' => $context['company'], 'subject_id' => $adjustment, 'version' => 1, 'comment' => 'Concurrent decision'];
                $race = $this->race()->run('attendance_workflow', $base + ['actor_id' => $context['approver_a'], 'action' => $actionA], $base + ['actor_id' => $context['approver_b'], 'action' => $actionB]);

                self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
                self::assertContains(DB::table('attendance_adjustment_requests')->where('id', $adjustment)->value('status'), ['approved', 'rejected', 'cancelled']);
                self::assertSame(1, DB::table('attendance_adjustment_histories')->where('attendance_adjustment_request_id', $adjustment)->count());
                self::assertSame(1, DB::table('activity_log')->where('log_name', 'attendance')->where('properties->company_id', $context['company'])->count());
                self::assertLessThanOrEqual(2, (int) DB::table('attendance_records')->where('id', $record)->value('calculation_version'));
            } finally {
                $this->cleanup($context);
            }
        }
    }

    public function test_adjustment_submit_and_period_lock_are_atomic(): void
    {
        $context = $this->context('ASL');
        try {
            $record = $this->attendanceRecord($context, 'approved');
            $adjustment = $this->adjustment($context, $record, 'draft');
            $period = $this->period($context, 'review');
            $race = $this->race()->run('attendance_workflow', ['company_id' => $context['company'], 'subject_id' => $adjustment, 'actor_id' => $context['owner'], 'version' => 1, 'action' => 'adjustment.submit'], ['company_id' => $context['company'], 'subject_id' => $period, 'actor_id' => $context['approver_a'], 'version' => 1, 'action' => 'period.lock', 'reason' => 'Concurrency lock'], 'attendance_workflow');

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            $periodStatus = DB::table('payroll_periods')->where('id', $period)->value('status');
            $adjustmentStatus = DB::table('attendance_adjustment_requests')->where('id', $adjustment)->value('status');
            self::assertFalse($periodStatus === 'locked' && $adjustmentStatus === 'submitted');
            self::assertSame($periodStatus === 'locked', (bool) DB::table('attendance_records')->where('id', $record)->value('is_locked'));
        } finally {
            $this->cleanup($context);
        }
    }

    public function test_period_lock_and_attendance_approval_races_preserve_readiness(): void
    {
        $context = $this->context('PLL');
        try {
            $record = $this->attendanceRecord($context, 'approved');
            $period = $this->period($context, 'review');
            $payload = ['company_id' => $context['company'], 'subject_id' => $period, 'version' => 1, 'action' => 'period.lock', 'reason' => 'Lock exactly once'];
            $race = $this->race()->run('attendance_workflow', $payload + ['actor_id' => $context['approver_a']], $payload + ['actor_id' => $context['approver_b']]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('locked', DB::table('payroll_periods')->where('id', $period)->value('status'));
            self::assertTrue((bool) DB::table('attendance_records')->where('id', $record)->value('is_locked'));
            self::assertSame(1, DB::table('activity_log')->where('event', 'period_locked')->where('properties->company_id', $context['company'])->count());
        } finally {
            $this->cleanup($context);
        }

        $context = $this->context('APL');
        try {
            $record = $this->attendanceRecord($context, 'submitted');
            $period = $this->period($context, 'review');
            $race = $this->race()->run('attendance_workflow', ['company_id' => $context['company'], 'subject_id' => $record, 'actor_id' => $context['approver_a'], 'version' => 1, 'action' => 'attendance.approve'], ['company_id' => $context['company'], 'subject_id' => $period, 'actor_id' => $context['approver_b'], 'version' => 1, 'action' => 'period.lock', 'reason' => 'Approval race']);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            $periodStatus = DB::table('payroll_periods')->where('id', $period)->value('status');
            $approvalStatus = DB::table('attendance_records')->where('id', $record)->value('approval_status');
            self::assertSame('approved', $approvalStatus);
            if ($periodStatus === 'locked') {
                self::assertTrue((bool) DB::table('attendance_records')->where('id', $record)->value('is_locked'));
            }
        } finally {
            $this->cleanup($context);
        }
    }

    public function test_leave_transition_and_accrual_races_are_idempotent(): void
    {
        foreach ([['submitted', 'submitted', 'draft'], ['approved', 'rejected', 'submitted'], ['approved', 'withdrawn', 'submitted']] as [$targetA, $targetB, $initial]) {
            $context = $this->context('LVR');
            try {
                [$policy, $request] = $this->leaveFixture($context, $initial);
                $payload = ['company_id' => $context['company'], 'subject_id' => $request, 'version' => 1, 'comment' => 'Concurrent leave'];
                $race = $this->race()->run('leave_transition', $payload + ['actor_id' => $targetA === 'submitted' ? $context['owner'] : $context['approver_a'], 'target' => $targetA], $payload + ['actor_id' => $targetB === 'submitted' || $targetB === 'withdrawn' ? $context['owner'] : $context['approver_b'], 'target' => $targetB]);
                self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
                self::assertSame(1, DB::table('leave_request_histories')->where('leave_request_id', $request)->count());
                self::assertLessThanOrEqual(1, DB::table('leave_balance_transactions')->where('leave_request_id', $request)->where('transaction_type', 'reservation')->count());
                self::assertLessThanOrEqual(1, DB::table('leave_balance_transactions')->where('leave_request_id', $request)->where('transaction_type', 'release')->count());
                self::assertLessThanOrEqual(1, DB::table('leave_balance_transactions')->where('leave_request_id', $request)->where('transaction_type', 'usage')->count());
                self::assertNotSame('', (string) $policy);
            } finally {
                $this->cleanup($context);
            }
        }

        $context = $this->context('LAC');
        try {
            [$policy] = $this->leaveFixture($context, null, true);
            $payload = ['date' => '2027-01-01'];
            $race = $this->race()->run('leave_accrual', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('leave_balance_transactions')->where('company_id', $context['company'])->where('leave_policy_id', $policy)->where('transaction_type', 'accrual')->count());
            self::assertSame(5760, (int) DB::table('leave_balance_transactions')->where('company_id', $context['company'])->where('leave_policy_id', $policy)->where('transaction_type', 'accrual')->sum('minutes'));
        } finally {
            $this->cleanup($context);
        }
    }

    public function test_overtime_transition_reconciliation_and_lock_races_are_serialized(): void
    {
        $context = $this->context('OTS');
        try {
            [$policy, $requestA] = $this->overtimeFixture($context, 'draft');
            $requestB = $this->overtimeRequest($context, $policy, 'draft', '18:30:00', '20:00:00');
            $base = ['company_id' => $context['company'], 'actor_id' => $context['owner'], 'version' => 1, 'action' => 'request.submit', 'idempotency_key' => (string) Str::uuid()];
            $race = $this->race()->run('overtime_workflow', $base + ['subject_id' => $requestA], $base + ['subject_id' => $requestB, 'idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('overtime_requests')->whereIn('id', [$requestA, $requestB])->where('status', 'submitted')->count());
        } finally {
            $this->cleanup($context);
        }

        $context = $this->context('OTD');
        try {
            [$policy, $request] = $this->overtimeFixture($context, 'submitted');
            $base = ['company_id' => $context['company'], 'subject_id' => $request, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'comment' => 'Concurrent overtime'];
            $race = $this->race()->run('overtime_workflow', $base + ['actor_id' => $context['approver_a'], 'action' => 'request.approve'], $base + ['actor_id' => $context['approver_b'], 'action' => 'request.reject', 'idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('overtime_request_histories')->where('overtime_request_id', $request)->count());
            self::assertContains(DB::table('overtime_requests')->where('id', $request)->value('status'), ['approved', 'rejected']);
            self::assertNotSame('', (string) $policy);
        } finally {
            $this->cleanup($context);
        }

        foreach ([false, true] as $againstLock) {
            $context = $this->context($againstLock ? 'ORL' : 'ORR');
            try {
                [$policy, $request] = $this->overtimeFixture($context, 'approved');
                $record = $this->attendanceRecord($context, 'approved', 120);
                $period = $this->period($context, 'review');
                $reconcile = ['company_id' => $context['company'], 'subject_id' => $request, 'actor_id' => $context['approver_a'], 'version' => 1, 'action' => 'request.reconcile', 'idempotency_key' => (string) Str::uuid()];
                $second = $againstLock ? ['company_id' => $context['company'], 'subject_id' => $period, 'actor_id' => $context['approver_b'], 'version' => 1, 'action' => 'period.lock', 'reason' => 'Reconciliation race'] : $reconcile + ['actor_id' => $context['approver_b'], 'idempotency_key' => (string) Str::uuid()];
                $race = $this->race()->run('overtime_workflow', $reconcile, $second, $againstLock ? 'attendance_workflow' : null);
                self::assertContains($race['exit_codes'], [[0, 2], [0, 0]], implode("\n", $race['outputs']));
                self::assertLessThanOrEqual(1, DB::table('overtime_request_histories')->where('overtime_request_id', $request)->where('action', 'request.reconcile')->count());
                self::assertLessThanOrEqual(2, (int) DB::table('overtime_requests')->where('id', $request)->value('calculation_version'));
                if (DB::table('payroll_periods')->where('id', $period)->value('status') === 'locked') {
                    self::assertContains(DB::table('overtime_requests')->where('id', $request)->value('calculation_status'), ['pending', 'reconciled']);
                }
                self::assertNotSame(0, $record);
                self::assertNotSame('', (string) $policy);
            } finally {
                $this->cleanup($context);
            }
        }
    }

    public function test_notification_delivery_race_creates_one_marker_per_channel(): void
    {
        $context = $this->context('NTF');
        try {
            $payload = ['company_id' => $context['company'], 'recipient_id' => $context['owner'], 'notification_type' => 'attendance', 'entity_type' => 'attendance_record', 'entity_id' => 991, 'action' => 'approved', 'version' => 1];
            $race = $this->race()->run('notification_delivery', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(2, DB::table('notification_deliveries')->where('company_id', $context['company'])->where('recipient_id', $context['owner'])->count());
            self::assertSame(1, DB::table('notifications')->where('notifiable_id', $context['owner'])->count());
            self::assertSame(['broadcast', 'database'], DB::table('notification_deliveries')->where('company_id', $context['company'])->orderBy('channel')->pluck('channel')->all());
        } finally {
            $this->cleanup($context);
        }
    }

    /** @return array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} */
    private function context(string $prefix): array
    {
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertContains(DB::connection()->getDatabaseName(), ['schema_admin_test', 'schema_admin_testing']);
        $token = $prefix.'-'.Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $token, 'name' => 'Schema Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $owner = User::factory()->create();
        $approverA = User::factory()->create();
        $approverB = User::factory()->create();
        foreach ([$owner, $approverA, $approverB] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $owner->id, 'employee_code' => $token, 'full_name' => 'Race Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return ['company' => (int) $company->id, 'employee' => (int) $employee->id, 'owner' => (int) $owner->id, 'approver_a' => (int) $approverA->id, 'approver_b' => (int) $approverB->id];
    }

    /** @param array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} $context */
    private function attendanceRecord(array $context, string $approval, int $overtimeMinutes = 0): int
    {
        return (int) DB::table('attendance_records')->insertGetId(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'work_date' => '2027-01-15', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => $overtimeMinutes, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => $approval, 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} $context */
    private function adjustment(array $context, int $record, string $status): int
    {
        return (int) DB::table('attendance_adjustment_requests')->insertGetId(['company_id' => $context['company'], 'attendance_record_id' => $record, 'employee_id' => $context['employee'], 'requested_by' => $context['owner'], 'idempotency_key' => (string) Str::uuid(), 'requested_check_in_at' => '2027-01-15 08:00:00', 'requested_check_out_at' => '2027-01-15 17:00:00', 'requested_break_minutes' => 60, 'requested_source' => 'manual_adjustment', 'reason' => 'Concurrency fixture', 'expected_record_version' => 1, 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} $context */
    private function period(array $context, string $status): int
    {
        return (int) DB::table('payroll_periods')->insertGetId(['company_id' => $context['company'], 'code' => 'P-'.Str::upper(Str::random(6)), 'name' => 'January 2027', 'period_start' => '2027-01-01', 'period_end' => '2027-01-31', 'cutoff_at' => '2027-02-01 00:00:00', 'payment_date' => '2027-02-05', 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array{company:int,employee:int,owner:int,approver_a:int,approver_b:int}  $context
     * @return array{int,int|null}
     */
    private function leaveFixture(array $context, ?string $requestStatus, bool $assignment = false): array
    {
        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $context['company'], 'code' => 'AL-'.Str::upper(Str::random(5)), 'name' => 'Annual', 'accrual_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $policy = (int) DB::table('leave_policies')->insertGetId(['company_id' => $context['company'], 'leave_type_id' => $type, 'code' => 'POL-'.Str::upper(Str::random(5)), 'name' => 'Annual', 'unit' => 'minutes', 'accrual_method' => 'annual_grant', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 0, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        if ($assignment) {
            DB::table('employee_leave_policy_assignments')->insert(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'leave_policy_id' => $policy, 'effective_from' => '2026-01-01', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($requestStatus === null) {
            return [$policy, null];
        }
        $balance = (int) DB::table('leave_balances')->insertGetId(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'leave_type_id' => $type, 'year' => 2027, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('leave_balance_transactions')->insert(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'leave_policy_id' => $policy, 'leave_balance_id' => $balance, 'effective_date' => '2027-01-01', 'transaction_type' => 'opening', 'minutes' => 600, 'amount' => '1.25', 'balance_before' => '0', 'balance_after' => '1.25', 'reference' => 'fixture', 'reference_type' => 'fixture', 'reference_id' => $policy, 'description' => 'Opening', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $context['owner'], 'created_at' => now()]);
        $request = (int) DB::table('leave_requests')->insertGetId(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'leave_type_id' => $type, 'leave_policy_id' => $policy, 'starts_at' => '2027-01-15 08:00:00', 'ends_at' => '2027-01-15 17:00:00', 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'days' => 1, 'reason' => 'Concurrency leave', 'status' => $requestStatus, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        if ($requestStatus === 'submitted') {
            DB::table('leave_balance_transactions')->insert(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'leave_policy_id' => $policy, 'leave_balance_id' => $balance, 'leave_request_id' => $request, 'effective_date' => '2027-01-15', 'transaction_type' => 'reservation', 'minutes' => -480, 'amount' => '-1.00', 'balance_before' => '1.25', 'balance_after' => '0.25', 'reference' => 'leave:'.$request, 'reference_type' => 'leave_request', 'reference_id' => $request, 'description' => 'reservation', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $context['owner'], 'created_at' => now()]);
        }

        return [$policy, $request];
    }

    /**
     * @param  array{company:int,employee:int,owner:int,approver_a:int,approver_b:int}  $context
     * @return array{int,int}
     */
    private function overtimeFixture(array $context, string $status): array
    {
        $policy = (int) DB::table('overtime_policies')->insertGetId(['company_id' => $context['company'], 'code' => 'OT-'.Str::upper(Str::random(5)), 'name' => 'Overtime', 'minimum_minutes' => 30, 'rounding_rule' => 'none', 'daily_limit_minutes' => 240, 'weekly_limit_minutes' => 600, 'requires_preapproval' => true, 'eligible_day_types' => '["working_day"]', 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_overtime_policy_assignments')->insert(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'overtime_policy_id' => $policy, 'effective_from' => '2026-01-01', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);

        return [$policy, $this->overtimeRequest($context, $policy, $status, '18:00:00', '19:30:00')];
    }

    /** @param array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} $context */
    private function overtimeRequest(array $context, int $policy, string $status, string $start, string $end): int
    {
        return (int) DB::table('overtime_requests')->insertGetId(['company_id' => $context['company'], 'employee_id' => $context['employee'], 'overtime_policy_id' => $policy, 'work_date' => '2027-01-15', 'starts_at' => '2027-01-15 '.$start, 'ends_at' => '2027-01-15 '.$end, 'requested_minutes' => 90, 'approved_minutes' => $status === 'approved' ? 90 : 0, 'actual_minutes' => 0, 'payable_minutes' => 0, 'calculation_status' => 'pending', 'calculation_version' => 1, 'reason' => 'Concurrency overtime', 'status' => $status, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function race(): MysqlProcessRaceRunner
    {
        return new MysqlProcessRaceRunner;
    }

    /** @param array{company:int,employee:int,owner:int,approver_a:int,approver_b:int} $context */
    private function cleanup(array $context): void
    {
        $company = $context['company'];
        foreach (['notification_deliveries', 'attendance_adjustment_histories', 'attendance_approval_histories', 'attendance_adjustment_requests', 'attendance_events', 'attendance_records', 'leave_attachments', 'leave_request_histories', 'leave_balance_transactions', 'leave_balances', 'leave_requests', 'employee_leave_policy_assignments', 'leave_policies', 'leave_types', 'overtime_request_histories', 'overtime_requests', 'employee_overtime_policy_assignments', 'overtime_policies', 'payroll_employee_inputs', 'payroll_input_snapshots', 'payroll_periods', 'employee_shift_assignments', 'employee_work_profiles', 'work_calendar_days', 'company_holidays', 'work_calendars', 'shift_break_rules', 'work_shifts'] as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->where('company_id', $company)->delete();
            }
        }
        DB::table('notifications')->whereIn('notifiable_id', [$context['owner'], $context['approver_a'], $context['approver_b']])->delete();
        DB::table('activity_log')->where('properties->company_id', $company)->delete();
        DB::table('company_user_access')->where('company_id', $company)->delete();
        DB::table('employees')->where('company_id', $company)->delete();
        DB::table('users')->whereIn('id', [$context['owner'], $context['approver_a'], $context['approver_b']])->delete();
        DB::table('companies')->where('id', $company)->delete();
        self::assertSame(0, DB::table('companies')->where('id', $company)->count());
    }
}
