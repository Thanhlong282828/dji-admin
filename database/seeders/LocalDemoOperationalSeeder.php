<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Spatie\Permission\Models\Role;

final class LocalDemoOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'SCHEMA-TECH-VN')->firstOrFail();
        $admin = User::query()->where('email', 'admin@local.test')->firstOrFail();
        $this->actors($company->id);
        $employees = Employee::query()->where('company_id', $company->id)->whereIn('employment_status', ['active', 'probation', 'leave'])->orderBy('employee_code')->limit(18)->get();
        $employeeIds = array_values($employees->pluck('id')->map(fn (mixed $id): int => $this->requiredId($id, 'employee'))->all());
        $this->workforce($company->id, $employeeIds, $admin->id);
        $this->notifications($company->id, $admin->id);
        $this->integration($company->id, $admin->id);
        $this->iam($company->id, $admin->id);
        $this->workflow($company->id, $admin->id);
        app(AuditRecorderInterface::class)->record(new AuditEventData($company->id, 'user', $admin->id, 'bootstrap', 'LocalDemo', 'LocalDemoBootstrapCompleted', 'local_demo_dataset', 'SCHEMA-LOCAL-DEMO', [], ['status' => 'ready'], ['synthetic_demo' => true, 'external_delivery' => false], 'local-demo-bootstrap-v1', $this->uuid(701)));
    }

    private function actors(int $companyId): void
    {
        foreach ([
            ['hr.manager@local.test', 'HR Manager Demo', 'HR Manager'],
            ['hr.staff@local.test', 'HR Staff Demo', 'HR Admin'],
            ['payroll.manager@local.test', 'Payroll Manager Demo', 'C&B'],
            ['recruiter@local.test', 'Recruiter Demo', 'Recruiter'],
            ['line.manager@local.test', 'Line Manager Demo', 'Manager'],
            ['employee@local.test', 'Employee Demo', 'Employee'],
            ['auditor@local.test', 'Auditor Demo', 'Viewer'],
        ] as [$email, $name, $roleName]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            if (! $user->exists) {
                $user->password = Hash::make('LocalDemoActor123!');
            }
            $user->save();
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $role = Role::findByName($roleName, 'web');
            $user->syncRoles([$role]);
            DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $user->id], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param list<int> $employeeIds */
    private function workforce(int $companyId, array $employeeIds, int $actorId): void
    {
        DB::table('work_calendars')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-VN-5D'], ['name' => 'Lịch làm việc Việt Nam 5 ngày (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $calendarId = $this->requiredId(DB::table('work_calendars')->where('company_id', $companyId)->where('code', 'LOCAL-VN-5D')->value('id'), 'work calendar');
        DB::table('work_shifts')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-OFFICE'], ['name' => 'Ca hành chính 08:30–17:30 (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:30:00', 'ends_at' => '17:30:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 5, 'grace_out_minutes' => 5, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'version' => 1, 'rules' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        $shiftId = $this->requiredId(DB::table('work_shifts')->where('company_id', $companyId)->where('code', 'LOCAL-OFFICE')->value('id'), 'work shift');

        foreach ($employeeIds as $index => $employeeId) {
            DB::table('employee_work_profiles')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'effective_from' => '2026-01-01'], ['timezone' => 'Asia/Ho_Chi_Minh', 'work_calendar_id' => $calendarId, 'default_shift_id' => $shiftId, 'weekly_minutes' => 2400, 'daily_minutes' => 480, 'fte_basis_points' => 10000, 'attendance_required' => true, 'overtime_eligible' => true, 'leave_eligible' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            for ($day = 1; $day <= 5; $day++) {
                $date = today()->subDays($day);
                $late = ($index + $day) % 5 === 0 ? 15 : 0;
                $overtime = ($index + $day) % 7 === 0 ? 60 : 0;
                DB::table('attendance_records')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'work_date' => $date->toDateString()], ['work_shift_id' => $shiftId, 'check_in_at' => $date->copy()->setTime(8, 30)->addMinutes($late), 'check_out_at' => $date->copy()->setTime(17, 30)->addMinutes($overtime), 'source' => 'local_demo', 'scheduled_minutes' => 480, 'worked_minutes' => 480 + $overtime - $late, 'break_minutes' => 60, 'late_minutes' => $late, 'early_leave_minutes' => 0, 'overtime_minutes' => $overtime, 'attendance_status' => $late > 0 ? 'late' : 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'anomalies' => json_encode($late > 0 ? ['late_arrival'] : [], JSON_THROW_ON_ERROR), 'worked_hours' => number_format((480 + $overtime - $late) / 60, 2, '.', ''), 'overtime_hours' => number_format($overtime / 60, 2, '.', ''), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        DB::table('leave_types')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO'], ['name' => 'Nghỉ phép năm (Demo)', 'accrual_policy' => json_encode(['method' => 'annual', 'synthetic_demo' => true], JSON_THROW_ON_ERROR), 'carry_forward_policy' => json_encode(['maximum_minutes' => 960], JSON_THROW_ON_ERROR), 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $typeId = $this->requiredId(DB::table('leave_types')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO')->value('id'), 'leave type');
        DB::table('leave_policies')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO-2026'], ['leave_type_id' => $typeId, 'name' => 'Chính sách phép năm synthetic 2026', 'unit' => 'minutes', 'accrual_method' => 'annual', 'annual_entitlement_minutes' => 5760, 'carry_forward_rule' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'maximum_carry_forward_minutes' => 960, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'approval_workflow' => json_encode(['levels' => 1], JSON_THROW_ON_ERROR), 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $policyId = $this->requiredId(DB::table('leave_policies')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO-2026')->value('id'), 'leave policy');
        foreach (array_slice($employeeIds, 0, 6) as $index => $employeeId) {
            DB::table('leave_balances')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => 2026], ['opening' => 0, 'accrued' => 5760, 'used' => $index * 480, 'adjusted' => 0, 'carried' => 0, 'expired' => 0, 'reserved' => $index < 2 ? 480 : 0, 'remaining' => 5760 - ($index * 480), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $status = ['pending', 'approved', 'rejected'][$index % 3];
            $key = '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            DB::table('leave_requests')->updateOrInsert(['idempotency_key' => $key], ['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'leave_policy_id' => $policyId, 'starts_at' => today()->addDays($index + 3), 'ends_at' => today()->addDays($index + 3), 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'duration_type' => 'full_day', 'days' => 1, 'reason' => 'Synthetic local demo leave request', 'status' => $status, 'version' => 1, 'approved_by' => $status === 'approved' ? $actorId : null, 'submitted_at' => now(), 'approved_at' => $status === 'approved' ? now() : null, 'rejected_at' => $status === 'rejected' ? now() : null, 'reviewed_at' => $status !== 'pending' ? now() : null, 'review_comment' => $status === 'rejected' ? 'Synthetic scheduling conflict' : null, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function notifications(int $companyId, int $recipientId): void
    {
        foreach ([['welcome', 'Welcome to Minh Long Tech', 'unread', null], ['policy', 'Leave policy updated', 'sent', null], ['retry', 'Synthetic channel retry', 'failed', 'Synthetic local channel unavailable']] as $index => [$action, $title, $status, $failure]) {
            $key = 'local-demo-notification-'.$action;
            $payload = ['synthetic_demo' => true, 'category' => $action];
            DB::table('notification_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 101), 'company_id' => $companyId, 'category' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'action' => $action, 'title' => $title, 'body' => 'SYNTHETIC DEMO DATA — no external delivery.', 'navigation_reference' => 'dashboard', 'safe_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'status' => $status, 'actor_id' => $recipientId, 'created_at' => now(), 'updated_at' => now()]);
            $messageId = $this->requiredId(DB::table('notification_messages')->where('idempotency_key', $key)->value('id'), 'notification message');
            $deliveryKey = $this->uuid($index + 201);
            DB::table('notification_deliveries')->updateOrInsert(['idempotency_key' => $deliveryKey], ['company_id' => $companyId, 'notification_message_id' => $messageId, 'notification_type' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'entity_version' => 1, 'recipient_id' => $recipientId, 'channel' => 'database', 'action' => $action, 'status' => $status === 'failed' ? 'failed' : 'sent', 'attempts' => $status === 'failed' ? 1 : 0, 'sent_at' => $status === 'failed' ? null : now(), 'failed_at' => $status === 'failed' ? now() : null, 'failure_message' => $failure, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notification_recipients')->updateOrInsert(['notification_message_id' => $messageId, 'user_id' => $recipientId], ['company_id' => $companyId, 'resolution_source' => 'local_demo', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function integration(int $companyId, int $actorId): void
    {
        $configuration = ['adapter' => 'local_null', 'external_http' => false];
        DB::table('integration_connections')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-NULL'], ['uuid' => $this->uuid(301), 'name' => 'Local Null Adapter (Synthetic)', 'type' => 'generic', 'status' => 'active', 'health_status' => 'healthy', 'base_url' => null, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'credential_reference' => null, 'timeout_seconds' => 1, 'max_attempts' => 3, 'version' => 1, 'checksum' => hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR)), 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $connectionId = $this->requiredId(DB::table('integration_connections')->where('company_id', $companyId)->where('code', 'LOCAL-NULL')->value('id'), 'integration connection');
        foreach (['queued', 'failed', 'dead_letter'] as $index => $status) {
            $key = $this->uuid($index + 401);
            $payload = ['synthetic_demo' => true, 'sequence' => $index + 1];
            DB::table('integration_outbound_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 501), 'company_id' => $companyId, 'connection_id' => $connectionId, 'source_type' => 'local_demo', 'source_id' => null, 'message_type' => 'synthetic.demo', 'status' => $status, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'attempt_count' => $index, 'available_at' => now(), 'failure_code' => $status === 'queued' ? null : 'LOCAL_DEMO_FAILURE', 'safe_failure_message' => $status === 'queued' ? null : 'Synthetic failure; no HTTP request was made.', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'dead_letter') {
                $messageId = $this->requiredId(DB::table('integration_outbound_messages')->where('idempotency_key', $key)->value('id'), 'integration outbound message');
                DB::table('integration_dead_letters')->updateOrInsert(['outbound_message_id' => $messageId], ['company_id' => $companyId, 'status' => 'open', 'reason_code' => 'LOCAL_DEMO_FAILURE', 'safe_reason' => 'Synthetic dead letter; external delivery disabled.', 'replay_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function iam(int $companyId, int $adminId): void
    {
        $subject = User::query()->where('email', 'employee@local.test')->firstOrFail();
        $roleId = $this->requiredId(Role::findByName('Employee', 'web')->id, 'Employee role');
        $key = $this->uuid(601);
        $checksum = hash('sha256', 'local-demo-iam-request');
        DB::table('iam_access_requests')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid(602), 'company_id' => $companyId, 'requester_id' => $subject->id, 'subject_user_id' => $subject->id, 'status' => 'requested', 'approval_mode' => 'iam', 'reason' => 'Synthetic local demo access request', 'version' => 1, 'checksum' => $checksum, 'created_at' => now(), 'updated_at' => now()]);
        $requestId = $this->requiredId(DB::table('iam_access_requests')->where('idempotency_key', $key)->value('id'), 'IAM access request');
        DB::table('iam_access_request_items')->updateOrInsert(['access_request_id' => $requestId, 'entitlement_type' => 'role', 'entitlement_id' => $roleId], ['risk_level' => 'standard', 'checksum' => hash('sha256', $checksum.'-item'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_access_reviews')->updateOrInsert(['company_id' => $companyId, 'name' => 'Q3 Synthetic Access Review'], ['uuid' => $this->uuid(603), 'scope_type' => 'company', 'scope_id' => $companyId, 'status' => 'draft', 'due_date' => today()->addMonth(), 'version' => 1, 'snapshot_checksum' => hash('sha256', 'local-demo-access-review'), 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function workflow(int $companyId, int $adminId): void
    {
        $approver = User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $definition = DB::table('workflow_definitions')->where('company_id', $companyId)->where('code', 'LOCAL-LEAVE-APPROVAL')->value('id');
        $definitionId = is_numeric($definition) ? (int) $definition : app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData($companyId, $adminId, 'LOCAL-LEAVE-APPROVAL', 'Synthetic Leave Approval', 'LOCAL DEMO ONLY — sequential maker-checker approval.', 'sequential', [
            ['key' => 'manager-review', 'name' => 'Line manager review', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $approver->id, 'due_after_minutes' => 1440],
        ]));
        $status = DB::table('workflow_definitions')->where('id', $definitionId)->value('status');
        if ($status !== 'published') {
            app(WorkflowDefinitionService::class)->publish($companyId, $definitionId, $adminId);
        }
        $existing = DB::table('workflow_instances')->where('company_id', $companyId)->where('idempotency_key', 'local-demo-leave-workflow')->value('id');
        if (! is_numeric($existing)) {
            app(WorkflowExecutionService::class)->start(new StartWorkflowData($companyId, $adminId, $definitionId, 'leave_request', 1, 'local-demo-leave-workflow', ['synthetic_demo' => true]));
        }
    }

    private function uuid(int $suffix): string
    {
        return '00000000-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }

    private function requiredId(mixed $value, string $resource): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new \RuntimeException("Missing numeric identifier for {$resource}.");
        }

        return (int) $value;
    }
}
