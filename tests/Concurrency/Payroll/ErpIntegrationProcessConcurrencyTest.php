<?php

namespace Tests\Concurrency\Payroll;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Payroll\Domain\Services\ErpExportService;
use Modules\Payroll\Domain\Services\ErpIntegrationBrowserFixtureService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('payroll-mysql-concurrency')]
final class ErpIntegrationProcessConcurrencyTest extends TestCase
{
    public function test_create_execute_retry_and_reverse_process_races_are_idempotent(): void
    {
        $fixture = app(ErpIntegrationBrowserFixtureService::class);
        $data = $fixture->create('testing');
        try {
            $company = $data['company_id'];
            $actor = $data['user_id'];
            $connection = $data['connection_id'];
            $journal = $this->integer(DB::table('payroll_accounting_journals')->where('company_id', $company)->value('id'));
            foreach (DB::table('erp_exports')->where('company_id', $company)->pluck('artifact_path') as $path) {
                if (is_string($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
            foreach (['erp_access_logs', 'erp_export_logs', 'erp_export_items', 'erp_exports'] as $table) {
                DB::table($table)->where('company_id', $company)->delete();
            }
            $payload = ['company_id' => $company, 'connection_id' => $connection, 'journal_id' => $journal, 'actor_id' => $actor];
            $create = (new MysqlProcessRaceRunner)->run('erp_export_create', $payload + ['idempotency_key' => (string) Str::uuid()], $payload + ['idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 0], $create['exit_codes'], implode("\n", $create['outputs']));
            self::assertSame(1, DB::table('erp_exports')->where('company_id', $company)->count());
            self::assertSame(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->count(), DB::table('erp_export_items')->where('company_id', $company)->count());
            $export = $this->integer(DB::table('erp_exports')->where('company_id', $company)->value('id'));
            $checker = $this->integer(DB::table('users')->where('email', 'browser.adjustment.checker.positive@example.test')->value('id'));
            app(ErpExportService::class)->validate($company, $export, $checker);
            $action = ['company_id' => $company, 'export_id' => $export, 'actor_id' => $actor];
            $execute = (new MysqlProcessRaceRunner)->run('erp_export_execute', $action, $action);
            self::assertSame([0, 0], $execute['exit_codes'], implode("\n", $execute['outputs']));
            $row = DB::table('erp_exports')->where('id', $export)->first();
            self::assertNotNull($row);
            $checksum = $this->string($row->artifact_checksum ?? null);
            $path = $this->string($row->artifact_path ?? null);
            self::assertSame(1, $this->integer($row->attempt_count ?? null));
            self::assertTrue(Storage::disk('local')->exists($path));
            $retry = (new MysqlProcessRaceRunner)->run('erp_export_retry', $action, $action);
            self::assertSame([0, 0], $retry['exit_codes'], implode("\n", $retry['outputs']));
            $retried = DB::table('erp_exports')->where('id', $export)->first();
            self::assertNotNull($retried);
            self::assertSame(1, $this->integer($retried->attempt_count ?? null));
            self::assertSame($checksum, $retried->artifact_checksum);
            self::assertSame($path, $retried->artifact_path);
            $reverse = (new MysqlProcessRaceRunner)->run('erp_export_reverse', $action, $action);
            self::assertSame([0, 0], $reverse['exit_codes'], implode("\n", $reverse['outputs']));
            self::assertSame('reversed', DB::table('erp_exports')->where('id', $export)->value('status'));
            self::assertSame(1, DB::table('erp_export_logs')->where('erp_export_id', $export)->where('action', 'reversed')->count());
        } finally {
            $fixture->cleanup('testing');
        }
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric identifier.');
    }

    private function string(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : self::fail('Expected non-empty string.');
    }
}
