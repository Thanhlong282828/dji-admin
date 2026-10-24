<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateResolver;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\FxRateSnapshotService;
use Modules\Finance\Application\Services\FxRevaluationService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class FxFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_lifecycle_resolver_snapshot_and_maker_checker(): void
    {
        [$company, $maker, $checker] = $this->scope();
        $service = app(FxRateService::class);
        $id = $service->create($company->id, $maker, $this->rateData());
        $service->transition($company->id, $id, 'submit', $maker);
        try {
            $service->transition($company->id, $id, 'approve', $maker);
            self::fail('Maker must not approve own rate.');
        } catch (ValidationException $e) {
            self::assertSame('FX_RATE_MAKER_CHECKER_REQUIRED', $e->errors()['actor'][0]);
        }
        $service->transition($company->id, $id, 'approve', $checker);
        $service->transition($company->id, $id, 'activate', $checker);
        $resolved = app(FxRateResolver::class)->resolve($company->id, 'USD', 'VND', '2026-10-03', 'closing');
        self::assertSame($id, (int) $resolved['id']);
        $snapshot = app(FxRateSnapshotService::class)->capture($company->id, $resolved, 'fx_test', 77);
        self::assertDatabaseHas('finance_fx_rate_snapshots', ['id' => $snapshot, 'fx_rate_id' => $id, 'source_type' => 'fx_test', 'source_id' => 77]);
        self::assertSame($snapshot, app(FxRateSnapshotService::class)->capture($company->id, $resolved, 'fx_test', 77));
        self::assertSame(1, DB::table('finance_fx_rate_snapshots')->where(['company_id' => $company->id, 'source_type' => 'fx_test', 'source_id' => 77])->count());
    }

    public function test_snapshot_is_immutable_when_source_evidence_changes(): void
    {
        [$company, $maker, $checker] = $this->scope();
        $service = app(FxRateService::class);
        $id = $service->create($company->id, $maker, $this->rateData());
        $service->transition($company->id, $id, 'submit', $maker);
        $service->transition($company->id, $id, 'approve', $checker);
        $service->transition($company->id, $id, 'activate', $checker);
        $resolved = app(FxRateResolver::class)->resolve($company->id, 'USD', 'VND', '2026-10-03', 'closing');
        $snapshot = app(FxRateSnapshotService::class)->capture($company->id, $resolved, 'immutable_test', 88);
        $checksum = DB::table('finance_fx_rate_snapshots')->where('id', $snapshot)->value('checksum');
        $changed = $resolved;
        $changed['rate_decimal'] = '25000.000000000000';
        try {
            app(FxRateSnapshotService::class)->capture($company->id, $changed, 'immutable_test', 88);
            self::fail('Snapshot evidence must not be replaced.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_SNAPSHOT_CANONICAL_PAYLOAD_MISMATCH', $exception->errors()['snapshot'][0]);
        }
        self::assertSame($checksum, DB::table('finance_fx_rate_snapshots')->where('id', $snapshot)->value('checksum'));
    }

    public function test_rate_validation_and_missing_rate_fail_closed(): void
    {
        [$company, $maker] = $this->scope();
        $this->expectException(ValidationException::class);
        app(FxRateService::class)->create($company->id, $maker, $this->rateData(['quote_currency' => 'USD']));
    }

    public function test_decimal_conversion_rounds_half_up_and_identity_resolves(): void
    {
        $converter = app(FxMoneyConverter::class);
        self::assertSame(24_691, $converter->convertMinor(100, 'USD', 'VND', '24690.500000000000'));
        self::assertSame(2_469_050, $converter->convertMinor(10_000, 'USD', 'VND', '24690.500000000000'));
        self::assertSame(100, $converter->convertMinor(24_691, 'VND', 'USD', '0.000040500000'));
        self::assertSame(27_159, $converter->convertMinor(100, 'EUR', 'VND', '27158.500000000000'));
        self::assertSame(92, $converter->convertMinor(100, 'USD', 'EUR', '0.915000000000'));
        self::assertSame(123, $converter->convertMinor(123, 'USD', 'USD', '1.000000000000'));
        [$company] = $this->scope();
        $identity = app(FxRateResolver::class)->resolve($company->id, 'VND', 'VND', '2026-10-03', 'closing');
        self::assertTrue($identity['identity']);
        self::assertSame('1.000000000000', $identity['rate_decimal']);
    }

    public function test_rate_creation_idempotency_rejects_conflicting_semantic_payload(): void
    {
        [$company, $maker] = $this->scope();
        $service = app(FxRateService::class);
        $payload = $this->rateData();
        $id = $service->create($company->id, $maker, $payload);
        self::assertSame($id, $service->create($company->id, $maker, $payload));

        try {
            $service->create($company->id, $maker, $this->rateData(['rate_decimal' => '25000.000000000000']));
            self::fail('Conflicting idempotent retry must fail closed.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_RATE_IDEMPOTENCY_CONFLICT', $exception->errors()['idempotency_key'][0]);
        }
    }

    public function test_first_snapshot_rejects_payload_that_differs_from_canonical_rate(): void
    {
        [$company, $maker, $checker] = $this->scope();
        $service = app(FxRateService::class);
        $id = $service->create($company->id, $maker, $this->rateData());
        $service->transition($company->id, $id, 'submit', $maker);
        $service->transition($company->id, $id, 'approve', $checker);
        $service->transition($company->id, $id, 'activate', $checker);
        $resolved = app(FxRateResolver::class)->resolve($company->id, 'USD', 'VND', '2026-10-03', 'closing');
        $resolved['rate_decimal'] = '25000.000000000000';

        try {
            app(FxRateSnapshotService::class)->capture($company->id, $resolved, 'tampered_test', 99);
            self::fail('First snapshot must match the canonical rate payload.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_SNAPSHOT_CANONICAL_PAYLOAD_MISMATCH', $exception->errors()['snapshot'][0]);
        }
        self::assertDatabaseMissing('finance_fx_rate_snapshots', ['company_id' => $company->id, 'source_type' => 'tampered_test', 'source_id' => 99]);
    }

    public function test_workspaces_are_permission_and_company_scoped(): void
    {
        [$company, $user] = $this->scope();
        foreach (['finance.fx-rate.view', 'finance.fx-exposure.view', 'finance.fx-revaluation.view'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name));
        }
        $this->actingAs($user)->get(route('admin.finance.fx-rate', ['company_id' => $company->id]))->assertOk()->assertSee('FX Rate Governance');
        $this->actingAs($user)->get(route('admin.finance.fx-exposure', ['company_id' => $company->id]))->assertOk()->assertSee('FX Exposure')->assertSee('Functional currency')->assertSee('VND');
        $this->actingAs($user)->get(route('admin.finance.fx-revaluation', ['company_id' => $company->id]))->assertOk()->assertSee('FX Revaluation')->assertSee('COMPANY GOVERNANCE SOURCE');
        $other = Company::factory()->create();
        $this->actingAs($user)->get(route('admin.finance.fx-rate', ['company_id' => $other->id]))->assertNotFound();
    }

    public function test_revaluation_state_machine_and_maker_checker_are_server_enforced(): void
    {
        [$company, $maker, $checker] = $this->scope();
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company->id, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $maker->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company->id, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $run = (int) DB::table('finance_fx_revaluation_runs')->insertGetId(['company_id' => $company->id, 'branch_id' => null, 'accounting_period_id' => $period, 'as_of_date' => '2026-10-03', 'functional_currency' => 'VND', 'status' => 'draft', 'idempotency_key' => fake()->uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(FxRevaluationService::class);
        try {
            $service->transition($company->id, $run, 'approve', $checker);
            self::fail('Draft cannot be approved directly.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_REVALUATION_INVALID_TRANSITION', $exception->errors()['status'][0]);
        }
        $service->transition($company->id, $run, 'submit', $maker);
        try {
            $service->transition($company->id, $run, 'approve', $maker);
            self::fail('Maker cannot approve own run.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_REVALUATION_MAKER_CHECKER_REQUIRED', $exception->errors()['actor'][0]);
        }
        $service->transition($company->id, $run, 'approve', $checker);
        self::assertDatabaseHas('finance_fx_revaluation_runs', ['id' => $run, 'status' => 'approved', 'approved_by' => $checker->id]);
        try {
            $service->transition($company->id, $run, 'submit', $maker);
            self::fail('Approved run cannot return to submitted.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_REVALUATION_INVALID_TRANSITION', $exception->errors()['status'][0]);
        }
    }

    /** @return array{Company,User,User} */
    private function scope(): array
    {
        $company = Company::factory()->create(['functional_currency_code' => 'VND']);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        DB::table('company_user_access')->insert([['company_id' => $company->id, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company->id, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);

        return [$company, $maker, $checker];
    }

    /** @param array<string,mixed> $override @return array<string,mixed> */
    private function rateData(array $override = []): array
    {
        return $override + ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => '24690.500000000000', 'rate_date' => '2026-10-03', 'rate_type' => 'closing', 'source_reference' => 'BANK-RATE-20261003', 'effective_from' => '2026-10-03', 'effective_to' => null, 'idempotency_key' => '6ff9f6c9-3ad6-4933-9d9e-80f8504ac9b8'];
    }
}
