<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Audit\Application\Commands\ArchiveAuditRangeCommand;
use Modules\Audit\Application\Commands\CreateAuditExportCommand;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Services\AuditExportService;
use Modules\Audit\Domain\Services\AuditRetentionService;
use Modules\Audit\Infrastructure\Ingestion\EmployeeAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\IamAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\IntegrationAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\NotificationAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\PayrollAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\WorkflowAuditConsumer;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class EnterpriseAuditPlatformTest extends TestCase
{
    use RefreshDatabase;

    private int $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'AUDIT-TEST', 'name' => 'Audit Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $this->company, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['audit.view', 'audit.search', 'audit.export', 'audit.retention.manage'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p));
        }
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('audit/'.$this->company);
        Storage::disk('local')->deleteDirectory('audit-archive/'.$this->company);
        parent::tearDown();
    }

    public function test_record_is_idempotent_immutable_and_redacted(): void
    {
        $data = new AuditEventData($this->company, 'user', (int) $this->user->id, 'approved', 'Workflow', 'WorkflowApproved', 'workflow_instance', '12', ['status' => 'pending', 'token' => 'old'], ['status' => 'approved', 'token' => 'new'], ['request_id' => 'req-1'], (string) Str::uuid(), (string) Str::uuid());
        $recorder = app(AuditRecorderInterface::class);
        $one = $recorder->record($data);
        $two = $recorder->record($data);
        $this->assertSame($one, $two);
        $this->assertSame(1, DB::table('audit_events')->count());
        $this->assertSame(2, DB::table('audit_event_snapshots')->count());
        $payload = DB::table('audit_event_snapshots')->where('snapshot_type', 'after')->value('payload');
        $this->assertIsString($payload);
        $this->assertStringNotContainsString('new', $payload);
        $this->assertStringContainsString('REDACTED', $payload);
        $checksum = DB::table('audit_events')->value('payload_checksum');
        $this->assertIsString($checksum);
        $this->assertSame(64, strlen($checksum));
    }

    public function test_auth_and_company_isolation(): void
    {
        $this->get('/admin/audit?company_id='.$this->company)->assertRedirect();
        $this->actingAs($this->user)->get('/admin/audit?company_id='.$this->company)->assertOk()->assertSee('Audit Dashboard');
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'AUDIT-FOREIGN', 'name' => 'Foreign', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->user)->get('/admin/audit?company_id='.$foreign)->assertNotFound();
    }

    public function test_csv_and_pdf_exports_are_private_verified_and_idempotent(): void
    {
        app(AuditRecorderInterface::class)->record(new AuditEventData($this->company, 'user', (int) $this->user->id, 'created', 'Employee', 'EmployeeCreated', 'employee', '=FORMULA', [], ['status' => 'active'], [], 'audit-export-source'));
        $service = app(AuditExportService::class);
        $csv = new CreateAuditExportCommand($this->company, (int) $this->user->id, 'csv', [], 'csv-idem');
        $first = $service->create($csv);
        $this->assertSame($first, $service->create($csv));
        $response = $this->actingAs($this->user)->get(route('admin.audit.exports.download', ['export' => $first, 'company_id' => $this->company]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=FORMULA", (string) $response->getContent());

        $pdf = $service->create(new CreateAuditExportCommand($this->company, (int) $this->user->id, 'pdf', [], 'pdf-idem'));
        $this->actingAs($this->user)->get(route('admin.audit.exports.download', ['export' => $pdf, 'company_id' => $this->company]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, DB::table('audit_exports')->count());
    }

    public function test_tampered_export_is_blocked_and_access_is_logged(): void
    {
        $service = app(AuditExportService::class);
        $id = $service->create(new CreateAuditExportCommand($this->company, (int) $this->user->id, 'csv', [], 'tamper-idem'));
        DB::table('audit_exports')->where('id', $id)->update(['checksum' => str_repeat('0', 64)]);
        $this->actingAs($this->user)->get(route('admin.audit.exports.download', ['export' => $id, 'company_id' => $this->company]))->assertConflict();
        $this->assertDatabaseHas('audit_access_logs', ['company_id' => $this->company, 'action' => 'audit_export_download', 'result' => 'blocked']);
    }

    public function test_retention_policy_appends_evidence_without_deleting_events(): void
    {
        $this->actingAs($this->user)->post(route('admin.audit.retention.store'), ['company_id' => $this->company, 'name' => 'Seven years', 'retention_days' => 2555])->assertRedirect();
        $this->assertDatabaseHas('audit_retention_policies', ['company_id' => $this->company, 'name' => 'Seven years']);
        $this->assertDatabaseHas('audit_events', ['company_id' => $this->company, 'action' => 'retention_policy_applied']);
    }

    public function test_supported_domain_outboxes_ingest_once_without_cross_module_mutation(): void
    {
        foreach ($this->sourceFixtures() as [$module, $consumer, $sourceId]) {
            $first = $consumer->consume($sourceId);
            $this->assertIsInt($first);
            $this->assertNull($consumer->consume($sourceId));
            $event = DB::table('audit_events')->where('id', $first)->first();
            $this->assertNotNull($event);
            $values = get_object_vars($event);
            $this->assertSame($module, $values['source_module']);
            $this->assertIsString($values['resource_type']);
            $this->assertNotSame('', $values['resource_type']);
            $this->assertIsString($values['resource_id']);
            $this->assertNotSame('', $values['resource_id']);
            $this->assertIsInt($values['actor_id']);
            $this->assertSame((int) $this->user->id, $values['actor_id']);
            $this->assertIsNumeric($values['correlation_id']);
            $this->assertIsString($values['payload_checksum']);
            $this->assertSame(64, strlen($values['payload_checksum']));
        }
        $this->assertSame(6, DB::table('audit_events')->count());
        $this->assertSame(6, DB::table('audit_source_checkpoints')->count());
        $snapshots = DB::table('audit_event_snapshots')->pluck('payload')->implode(' ');
        $this->assertStringNotContainsString('salary-secret', $snapshots);
        $this->assertStringNotContainsString('bank-secret', $snapshots);
    }

    public function test_tampered_event_snapshot_is_blocked_on_detail(): void
    {
        $id = app(AuditRecorderInterface::class)->record(new AuditEventData($this->company, 'user', (int) $this->user->id, 'updated', 'Payroll', 'PayrollUpdated', 'payroll_run', '88', [], ['status' => 'approved'], [], 'tampered-event'));
        DB::table('audit_event_snapshots')->where(['audit_event_id' => $id, 'snapshot_type' => 'after'])->update(['payload' => json_encode(['status' => 'tampered'], JSON_THROW_ON_ERROR)]);
        $this->actingAs($this->user)->get(route('admin.audit.events.show', ['event' => $id, 'company_id' => $this->company]))->assertConflict();
    }

    public function test_archive_range_creates_one_immutable_artifact_without_deleting_events(): void
    {
        for ($index = 0; $index < 3; $index++) {
            app(AuditRecorderInterface::class)->record(new AuditEventData($this->company, 'user', (int) $this->user->id, 'updated', 'Employee', 'EmployeeUpdated', 'employee', (string) $index, [], ['status' => 'active', 'token' => 'secret'], [], 'archive-source-'.$index));
        }
        $service = app(AuditRetentionService::class);
        $command = new ArchiveAuditRangeCommand($this->company, (int) $this->user->id, today()->subDay()->toDateString(), today()->addDay()->toDateString(), 'archive-idem');
        $first = $service->archive($command);
        $this->assertSame($first, $service->archive($command));
        $artifact = DB::table('audit_archive_artifacts')->where('id', $first)->first();
        $this->assertNotNull($artifact);
        $values = get_object_vars($artifact);
        $this->assertIsInt($values['event_count']);
        $this->assertSame(3, $values['event_count']);
        $this->assertSame('archived', $values['status']);
        $this->assertIsString($values['checksum']);
        $this->assertSame(64, strlen($values['checksum']));
        $this->assertIsString($values['artifact_reference']);
        $this->assertStringNotContainsString('/', $values['artifact_reference']);
        $this->assertTrue($service->verifyArchive($this->company, $first));
        $this->assertSame(4, DB::table('audit_events')->where('company_id', $this->company)->count());
    }

    public function test_pdf_export_uses_bounded_memory_for_ten_thousand_events(): void
    {
        $now = now();
        for ($chunk = 0; $chunk < 20; $chunk++) {
            $rows = [];
            for ($offset = 0; $offset < 500; $offset++) {
                $index = ($chunk * 500) + $offset;
                $rows[] = ['uuid' => (string) Str::uuid(), 'company_id' => $this->company, 'idempotency_key' => 'memory-'.$index, 'actor_type' => 'system', 'action' => 'updated', 'source_module' => 'Workflow', 'source_event' => 'workflow.updated', 'resource_type' => 'workflow_instance', 'resource_id' => (string) $index, 'metadata' => '{}', 'payload_checksum' => hash('sha256', (string) $index), 'occurred_at' => $now, 'created_at' => $now];
            }
            DB::table('audit_events')->insert($rows);
        }
        gc_collect_cycles();
        $before = memory_get_usage(true);
        $id = app(AuditExportService::class)->create(new CreateAuditExportCommand($this->company, (int) $this->user->id, 'pdf', [], 'memory-pdf'));
        $delta = memory_get_peak_usage(true) - $before;
        $this->assertLessThan(32 * 1024 * 1024, $delta, 'Streaming PDF memory delta grew beyond 32 MiB.');
        $this->assertDatabaseHas('audit_exports', ['id' => $id, 'format' => 'pdf', 'status' => 'completed']);
    }

    public function test_permission_matrix_and_restricted_data_are_not_exposed(): void
    {
        $withoutPermission = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $this->company, 'user_id' => $withoutPermission->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($withoutPermission)->get(route('admin.audit.dashboard', ['company_id' => $this->company]))->assertForbidden();
        app(AuditRecorderInterface::class)->record(new AuditEventData($this->company, 'user', (int) $this->user->id, 'updated', 'Payroll', 'PayrollUpdated', 'payroll_run', '99', [], ['salary' => 'restricted-salary', 'tax' => 'restricted-tax', 'bank' => 'restricted-bank', 'credential' => 'restricted-credential', 'token' => 'restricted-token'], [], 'security-matrix-event'));
        $response = $this->actingAs($this->user)->get(route('admin.audit.timeline', ['company_id' => $this->company]));
        $response->assertOk();
        foreach (['restricted-salary', 'restricted-tax', 'restricted-bank', 'restricted-credential', 'restricted-token'] as $secret) {
            $response->assertDontSee($secret);
        }
    }

    /** @return list<array{string, WorkflowAuditConsumer|NotificationAuditConsumer|IamAuditConsumer|IntegrationAuditConsumer|EmployeeAuditConsumer|PayrollAuditConsumer, int}> */
    private function sourceFixtures(): array
    {
        $actor = (int) $this->user->id;
        $payload = json_encode(['actor_id' => $actor, 'status' => 'approved', 'salary' => 'salary-secret', 'bank_account' => 'bank-secret'], JSON_THROW_ON_ERROR);
        $workflow = (int) DB::table('workflow_instances')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $this->company, 'workflow_type' => 'audit_fixture', 'subject_type' => 'employee', 'subject_id' => 9001, 'definition_snapshot' => '{}', 'status' => 'approved', 'engine_status' => 'completed', 'started_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        $workflowEvent = (int) DB::table('workflow_outbox')->insertGetId(['event_id' => (string) Str::uuid(), 'company_id' => $this->company, 'workflow_instance_id' => $workflow, 'event_type' => 'workflow.completed', 'payload' => $payload, 'payload_checksum' => hash('sha256', $payload), 'occurred_at' => now(), 'created_at' => now()]);
        $notificationEvent = (int) DB::table('notification_outbox')->insertGetId(['event_id' => (string) Str::uuid(), 'company_id' => $this->company, 'event_type' => 'notification.delivered', 'safe_payload' => $payload, 'payload_checksum' => hash('sha256', $payload), 'idempotency_key' => (string) Str::uuid(), 'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $iamEvent = (int) DB::table('iam_outbox')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $this->company, 'event_type' => 'iam.access.provisioned', 'aggregate_type' => 'access_request', 'aggregate_id' => 7001, 'payload' => $payload, 'checksum' => hash('sha256', $payload), 'idempotency_key' => (string) Str::uuid(), 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
        $integrationEvent = (int) DB::table('integration_outbox')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $this->company, 'event_type' => 'integration.delivered', 'aggregate_type' => 'outbound_message', 'aggregate_id' => 8001, 'payload' => $payload, 'checksum' => hash('sha256', $payload), 'idempotency_key' => (string) Str::uuid(), 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
        $employee = (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $this->company, 'employee_code' => 'AUDIT-E001', 'full_name' => 'Audit Employee', 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $employeeEvent = (int) DB::table('employee_lifecycle_outbox')->insertGetId(['event_id' => (string) Str::uuid(), 'company_id' => $this->company, 'employee_id' => $employee, 'event_type' => 'employee.transferred', 'payload' => $payload, 'payload_checksum' => hash('sha256', $payload), 'occurred_at' => now(), 'created_at' => now()]);
        $payrollEvent = (int) DB::table('activity_log')->insertGetId(['log_name' => 'payroll-run', 'description' => 'Payroll Run approved', 'subject_type' => 'payroll_run', 'subject_id' => 6001, 'event' => 'approved', 'causer_type' => User::class, 'causer_id' => $actor, 'attribute_changes' => '{}', 'properties' => json_encode(['company_id' => $this->company, 'actor_id' => $actor, 'salary' => 'salary-secret'], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return [
            ['Workflow', app(WorkflowAuditConsumer::class), $workflowEvent],
            ['Notification', app(NotificationAuditConsumer::class), $notificationEvent],
            ['Iam', app(IamAuditConsumer::class), $iamEvent],
            ['Integration', app(IntegrationAuditConsumer::class), $integrationEvent],
            ['Employee', app(EmployeeAuditConsumer::class), $employeeEvent],
            ['Payroll', app(PayrollAuditConsumer::class), $payrollEvent],
        ];
    }
}
