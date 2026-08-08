<?php

namespace Tests\Concurrency\Employee;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\HRDataGovernanceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class HRDataGovernanceProcessConcurrencyTest extends TestCase
{
    public function test_quality_run_retry_resolve_and_archive_process_races(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'GOV-RACE-'.Str::random(5), 'name' => 'Governance Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'GOV-RACE', 'full_name' => 'Governance Race Employee', 'nationality' => 'VN', 'join_date' => '2026-08-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $runner = new MysqlProcessRaceRunner;
        $approverId = null;
        try {
            $runPayload = ['company_id' => (int) $company->id, 'actor_id' => (int) $user->id, 'idempotency_key' => 'race-run', 'date' => '2026-08-08'];
            $createRace = $runner->run('governance_quality_run', $runPayload, $runPayload);
            self::assertContains($createRace['exit_codes'], [[0, 0], [0, 2]], implode("\n", $createRace['outputs']));
            self::assertSame(1, DB::table('hr_data_quality_runs')->where('company_id', $company->id)->where('idempotency_key', 'race-run')->count());
            $issueCount = DB::table('hr_data_quality_issues')->count();
            $retryRace = $runner->run('governance_quality_retry', $runPayload, $runPayload);
            self::assertSame([0, 0], $retryRace['exit_codes'], implode("\n", $retryRace['outputs']));
            self::assertSame($issueCount, DB::table('hr_data_quality_issues')->count());

            $service = app(HRDataGovernanceService::class);
            $runValue = DB::table('hr_data_quality_runs')->where('idempotency_key', 'race-run')->value('id');
            self::assertIsNumeric($runValue);
            $runId = (int) $runValue;
            $issueValue = DB::table('hr_data_quality_issues')->where('run_id', $runId)->value('id');
            self::assertIsNumeric($issueValue);
            $issueId = (int) $issueValue;
            $service->reviewIssue((int) $company->id, $issueId, (int) $user->id, 'race-review', 'review');
            $approver = User::factory()->create();
            $approverId = (int) $approver->id;
            $service->approveIssue((int) $company->id, $issueId, (int) $approver->id, 'race-approve', 'approve');
            $resolvePayload = ['company_id' => (int) $company->id, 'issue_id' => $issueId, 'actor_id' => (int) $user->id, 'idempotency_key' => 'race-resolve', 'reason' => 'evidence'];
            $resolveRace = $runner->run('governance_issue_resolve', $resolvePayload, $resolvePayload);
            self::assertSame([0, 0], $resolveRace['exit_codes'], implode("\n", $resolveRace['outputs']));
            self::assertSame(1, DB::table('hr_governance_audit_events')->where('idempotency_key', 'race-resolve')->count());

            $documentId = (int) DB::table('employee_documents')->insertGetId(['employee_id' => $employee->id, 'type' => 'identity', 'name' => 'Race document', 'version' => 1, 'storage_key' => 'private/race.pdf', 'checksum' => hash('sha256', 'race'), 'mime_type' => 'application/pdf', 'size_bytes' => 4, 'scan_status' => 'clean', 'access_policy' => json_encode(['private' => true]), 'created_at' => now(), 'updated_at' => now()]);
            $archivePayload = ['company_id' => (int) $company->id, 'document_id' => $documentId, 'actor_id' => (int) $user->id, 'idempotency_key' => 'race-archive', 'retention_date' => '2036-08-08'];
            $archiveRace = $runner->run('governance_document_archive', $archivePayload, $archivePayload);
            self::assertContains($archiveRace['exit_codes'], [[0, 0], [0, 2]], implode("\n", $archiveRace['outputs']));
            self::assertSame(1, DB::table('hr_document_governance_history')->where('document_id', $documentId)->count());
            self::assertSame(1, DB::table('hr_governance_audit_events')->where('action', 'document_archived')->where('entity_id', $documentId)->count());
        } finally {
            DB::table('hr_governance_audit_events')->where('company_id', $company->id)->delete();
            DB::table('hr_document_governance_history')->where('company_id', $company->id)->delete();
            DB::table('employee_documents')->where('employee_id', $employee->id)->delete();
            DB::table('hr_data_quality_issues')->whereIn('run_id', DB::table('hr_data_quality_runs')->where('company_id', $company->id)->pluck('id'))->delete();
            DB::table('hr_data_quality_runs')->where('company_id', $company->id)->delete();
            $employee->forceDelete();
            if ($approverId !== null) {
                User::query()->whereKey($approverId)->delete();
            }
            $user->delete();
            $company->forceDelete();
        }
    }
}
