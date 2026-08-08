<?php

namespace Tests\Concurrency\Audit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('audit-mysql-concurrency')]
final class EnterpriseAuditProcessConcurrencyTest extends TestCase
{
    public function test_record_export_retention_and_archive_process_races_have_one_winner(): void
    {
        Artisan::call('audit:browser-fixture');
        try {
            $company = $this->id('companies', 'code', 'AUDIT-FIXTURE');
            $actor = $this->id('users', 'email', 'audit.browser@schema.test');
            $runner = new MysqlProcessRaceRunner;
            $workflow = (int) DB::table('workflow_instances')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'workflow_type' => 'audit_race', 'subject_type' => 'employee', 'subject_id' => 9001, 'definition_snapshot' => '{}', 'status' => 'approved', 'engine_status' => 'completed', 'started_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $sourceUuid = (string) Str::uuid();
            $sourcePayload = json_encode(['actor_id' => $actor, 'status' => 'completed'], JSON_THROW_ON_ERROR);
            $source = (int) DB::table('workflow_outbox')->insertGetId(['event_id' => $sourceUuid, 'company_id' => $company, 'workflow_instance_id' => $workflow, 'event_type' => 'workflow.completed', 'payload' => $sourcePayload, 'payload_checksum' => hash('sha256', $sourcePayload), 'occurred_at' => now(), 'created_at' => now()]);
            $this->assertRace($runner, 'audit_source_workflow', ['source_id' => $source]);
            self::assertSame(1, DB::table('audit_source_checkpoints')->where('source_event_id', $sourceUuid)->count());
            self::assertSame(1, DB::table('audit_events')->where('source_module', 'Workflow')->where('metadata', 'like', '%'.$sourceUuid.'%')->count());

            $key = (string) Str::uuid();
            $record = ['company_id' => $company, 'resource_id' => 'race-resource', 'idempotency_key' => $key];
            $this->assertRace($runner, 'audit_record', $record);
            self::assertSame(1, DB::table('audit_events')->where('idempotency_key', $key)->count());

            $export = ['company_id' => $company, 'actor_id' => $actor, 'idempotency_key' => (string) Str::uuid()];
            $this->assertRace($runner, 'audit_export', $export);
            self::assertSame(1, DB::table('audit_exports')->where('idempotency_key', $export['idempotency_key'])->count());

            $retention = ['company_id' => $company, 'actor_id' => $actor, 'name' => 'Race retention'];
            $this->assertRace($runner, 'audit_retention', $retention);
            self::assertSame(1, DB::table('audit_retention_policies')->where(['company_id' => $company, 'name' => 'Race retention'])->count());

            $archive = ['company_id' => $company, 'actor_id' => $actor, 'range_start' => '2026-01-01', 'range_end' => '2026-12-31', 'idempotency_key' => (string) Str::uuid()];
            $this->assertRace($runner, 'audit_archive', $archive);
            self::assertSame(1, DB::table('audit_archive_artifacts')->where('idempotency_key', $archive['idempotency_key'])->count());
        } finally {
            Artisan::call('audit:browser-fixture', ['--cleanup' => true]);
        }
    }

    /** @param array<string, bool|int|string|null> $payload */
    private function assertRace(MysqlProcessRaceRunner $runner, string $scenario, array $payload): void
    {
        $race = $runner->run($scenario, $payload, $payload);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
    }

    private function id(string $table, string $column, int|string $value): int
    {
        $id = DB::table($table)->where($column, $value)->value('id');

        return is_numeric($id) ? (int) $id : self::fail("Missing fixture {$table}");
    }
}
