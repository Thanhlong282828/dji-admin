<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityRemediationAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use RuntimeException;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

final class DataQualityRemediationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_is_deterministic_read_only_and_keeps_sensitive_phone_redacted(): void
    {
        $company = $this->company('DQ-A');
        $user = User::factory()->create(['phone_e164' => 'not-e164', 'phone_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $before = $this->counts();

        $first = app(DataQualityRemediationAuditService::class)->snapshot(null, 'schema_admin_testing');
        $second = app(DataQualityRemediationAuditService::class)->snapshot(null, 'schema_admin_testing');

        self::assertSame($first['checksum'], $second['checksum']);
        self::assertSame('DRY_RUN_READ_ONLY', $first['mode']);
        self::assertSame(0, $first['mutation_count']);
        self::assertSame($before, $this->counts());
        $json = json_encode($first, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('not-e164', $json);
        self::assertStringContainsString('sensitive_value_redacted', $json);
    }

    public function test_company_scope_is_tenant_isolated(): void
    {
        $companyA = $this->company('DQ-A');
        $companyB = $this->company('DQ-B');
        foreach ([$companyA, $companyB] as $company) {
            $user = User::factory()->create(['phone_e164' => 'invalid-'.$company->id, 'phone_verified_at' => now()]);
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $snapshot = app(DataQualityRemediationAuditService::class)->snapshot($companyA->id, 'schema_admin_testing');
        $finding = collect($snapshot['rules'])->firstWhere('rule_id', 'identity.invalid_verified_phone');

        self::assertSame(1, $finding['count']);
        self::assertSame([$companyA->id], $finding['records'][0]['company_ids']);
    }

    public function test_duplicate_rule_aliases_are_proven_by_record_key_sets(): void
    {
        $snapshot = app(DataQualityRemediationAuditService::class)->snapshot(null, 'schema_admin_testing');
        $relationships = collect($snapshot['relationships']);

        self::assertTrue($relationships->firstWhere('left_rule', 'journal_without_policy')['same_row_set']);
        self::assertTrue($relationships->firstWhere('left_rule', 'missing_account_mapping')['same_row_set']);
    }

    public function test_command_fails_closed_without_exact_database_authorization(): void
    {
        self::assertSame(1, Artisan::call('operations:data-quality-remediation-audit', ['--json' => true]));
        self::assertStringContainsString('exact expected database is required', strtolower(Artisan::output()));
        self::assertSame(1, Artisan::call('operations:data-quality-remediation-audit', ['--expected-database' => 'schema_admin', '--json' => true]));
        self::assertStringContainsString('must match', strtolower(Artisan::output()));
    }

    public function test_two_mysql_processes_produce_the_same_read_only_audit(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real process concurrency requires MySQL.');
        }

        $result = app(MysqlProcessRaceRunner::class)->run('dq_remediation_audit', [], []);

        self::assertSame([0, 0], $result['exit_codes']);
        $outputs = array_map(static function (string $output): array {
            $decoded = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        }, $result['outputs']);
        self::assertSame('committed', $outputs[0]['status']);
        self::assertSame('committed', $outputs[1]['status']);
        self::assertSame($outputs[0]['result']['checksum'], $outputs[1]['result']['checksum']);
        self::assertSame(0, $outputs[0]['result']['mutation_count']);
        self::assertSame(0, $outputs[1]['result']['mutation_count']);
    }

    public function test_invalid_company_scope_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(DataQualityRemediationAuditService::class)->snapshot(0, 'schema_admin_testing');
    }

    /** @return array<string,int> */
    private function counts(): array
    {
        return ['users' => DB::table('users')->count(), 'journals' => DB::table('payroll_accounting_journals')->count(), 'periods' => DB::table('accounting_periods')->count()];
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code.'-'.Str::lower(Str::random(5)), 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }
}
