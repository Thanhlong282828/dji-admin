<?php

namespace Tests\Concurrency\Payroll;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('payroll-mysql-concurrency')]
final class GovernmentFilingProcessConcurrencyTest extends TestCase
{
    public function test_full_filing_lifecycle_is_process_safe(): void
    {
        $token = Str::upper(Str::random(10));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'GTF-RACE-'.$token, 'name' => 'Government Filing Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $submitter = User::factory()->create();
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $maker->id, 'employee_code' => 'GTF-RACE-'.$token, 'full_name' => 'Government Filing Race Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $annual = (int) DB::table('payroll_annual_tax_reconciliations')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'employee_id' => $employee->id, 'tax_year' => 2026, 'jurisdiction' => 'VN', 'currency' => 'VND', 'reference' => 'ATR-RACE-'.$token, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'status' => 'finalized', 'result_type' => 'balanced', 'taxpayer_snapshot' => json_encode(['employee_code' => 'GTF-RACE-'.$token], JSON_THROW_ON_ERROR), 'company_tax_snapshot' => json_encode(['name' => $company->name], JSON_THROW_ON_ERROR), 'calculation_version' => 1, 'checksum_version' => 'annual-tax-source-v1', 'source_checksum' => hash('sha256', $token), 'created_by' => $maker->id, 'finalized_by' => $checker->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        try {
            $common = ['company_id' => $company->id, 'reconciliation_id' => $annual, 'actor_id' => $maker->id];
            $create = (new MysqlProcessRaceRunner)->run('government_filing_create', $common + ['idempotency_key' => (string) Str::uuid()], $common + ['idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 0], $create['exit_codes'], implode("\n", $create['outputs']));
            self::assertSame(1, DB::table('government_tax_filings')->where('company_id', $company->id)->count());
            self::assertSame(1, DB::table('government_tax_filing_items')->where('company_id', $company->id)->count());
            $filing = $this->integer(DB::table('government_tax_filings')->where('company_id', $company->id)->value('id'));
            $action = ['company_id' => $company->id, 'filing_id' => $filing, 'actor_id' => $maker->id];
            $generate = (new MysqlProcessRaceRunner)->run('government_filing_generate', $action, $action);
            self::assertSame([0, 0], $generate['exit_codes'], implode("\n", $generate['outputs']));
            self::assertSame(1, DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->count());
            $checksum = $this->string(DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->value('checksum'));
            $validate = (new MysqlProcessRaceRunner)->run('government_filing_validate', $action, $action);
            $validateCodes = $validate['exit_codes'];
            sort($validateCodes);
            self::assertSame([0, 2], $validateCodes, implode("\n", $validate['outputs']));
            self::assertSame(1, DB::table('government_tax_filing_logs')->where('government_tax_filing_id', $filing)->where('action', 'validated')->count());
            $approveAction = ['company_id' => $company->id, 'filing_id' => $filing, 'actor_id' => $checker->id];
            $approve = (new MysqlProcessRaceRunner)->run('government_filing_approve', $approveAction, $approveAction);
            $approveCodes = $approve['exit_codes'];
            sort($approveCodes);
            self::assertSame([0, 2], $approveCodes, implode("\n", $approve['outputs']));
            self::assertSame(1, DB::table('government_tax_filing_logs')->where('government_tax_filing_id', $filing)->where('action', 'approved')->count());
            $submit = ['company_id' => $company->id, 'filing_id' => $filing, 'actor_id' => $submitter->id, 'idempotency_key' => (string) Str::uuid()];
            $submitted = (new MysqlProcessRaceRunner)->run('government_filing_submit', $submit, $submit);
            self::assertSame([0, 0], $submitted['exit_codes'], implode("\n", $submitted['outputs']));
            self::assertSame(1, DB::table('government_tax_submission_attempts')->where('government_tax_filing_id', $filing)->count());
            $service = app(GovernmentTaxFilingService::class);
            $service->reject($company->id, $filing, $checker->id, 'Controlled local rejection');
            $retry = ['company_id' => $company->id, 'filing_id' => $filing, 'actor_id' => $submitter->id, 'idempotency_key' => (string) Str::uuid()];
            $retried = (new MysqlProcessRaceRunner)->run('government_filing_retry', $retry, $retry);
            self::assertSame([0, 0], $retried['exit_codes'], implode("\n", $retried['outputs']));
            self::assertSame(2, DB::table('government_tax_submission_attempts')->where('government_tax_filing_id', $filing)->count());
            self::assertSame('submitted', DB::table('government_tax_filings')->where('id', $filing)->value('status'));
            self::assertSame($checksum, DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->value('checksum'));
        } finally {
            foreach (DB::table('government_tax_filing_documents')->where('company_id', $company->id)->pluck('storage_path') as $path) {
                if (is_string($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
            foreach (['government_tax_access_logs', 'government_tax_submission_attempts', 'government_tax_validation_issues', 'government_tax_filing_logs', 'government_tax_filing_documents', 'government_tax_filing_items', 'government_tax_filings'] as $table) {
                DB::table($table)->where('company_id', $company->id)->delete();
            }
            DB::table('payroll_annual_tax_reconciliations')->where('id', $annual)->delete();
            Employee::withTrashed()->whereKey($employee->id)->forceDelete();
            $maker->delete();
            $checker->delete();
            $submitter->delete();
            $company->forceDelete();
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
