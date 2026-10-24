<?php

namespace Tests\Feature\FixedAsset;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AssetLifecycleService;
use Tests\TestCase;

final class FixedAssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_impairment_lifecycle_is_maker_checked_policy_backed_and_idempotent(): void
    {
        $fixture = $this->fixture();
        $asset = $this->asset($fixture, 'ASSET-IMPAIRMENT', 100_000);
        $this->policy($fixture, 'ASSET_IMPAIRMENT', 'LOSS_OF_VALUE', [
            ['debit', 'IMPAIRMENT_LOSS', $fixture['impairment_loss']],
            ['credit', 'ASSET_VALUE', $fixture['fixed_asset']],
        ]);
        $service = app(AssetLifecycleService::class);
        $payload = ['asset_id' => $asset, 'reference' => 'IMP-001', 'reason' => 'LOSS_OF_VALUE', 'review_date' => '2026-09-15', 'impairment_amount_minor' => 20_000, 'idempotency_key' => 'impairment-idempotency-001'];

        $document = $service->createImpairment($fixture['company'], $fixture['maker'], $payload);
        self::assertSame($document, $service->createImpairment($fixture['company'], $fixture['maker'], $payload));
        self::assertSame(1, DB::table('finance_asset_impairments')->where('idempotency_key', $payload['idempotency_key'])->count());

        try {
            $service->approveImpairment($fixture['company'], $document, $fixture['maker']);
            self::fail('Maker must not approve their impairment request.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('approval', $exception->errors());
        }
        $service->approveImpairment($fixture['company'], $document, $fixture['checker']);
        $row = DB::table('finance_asset_impairments')->where('id', $document)->firstOrFail();
        self::assertSame($fixture['company'], (int) $row->company_id);
        self::assertSame($fixture['checker']->id, (int) $row->approved_by);
        self::assertNotNull($row->approved_at);

        $service->postImpairment($fixture['company'], $document, $fixture['checker']);
        self::assertSame($document, $service->postImpairment($fixture['company'], $document, $fixture['checker']));
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'asset_impairment', 'source_id' => $document])->firstOrFail();
        self::assertSame(20_000, (int) $journal->total_debit_minor);
        self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'asset_impairment', 'source_id' => $document])->count());
    }

    public function test_disposal_posts_loss_and_gain_then_retires_each_asset_once(): void
    {
        $fixture = $this->fixture();
        $this->disposalPolicies($fixture);
        $service = app(AssetLifecycleService::class);

        foreach ([['LOSS', 70_000, -30_000], ['GAIN', 130_000, 30_000]] as [$classification, $proceeds, $expected]) {
            $asset = $this->asset($fixture, 'ASSET-'.$classification, 100_000);
            $document = $service->createDisposal($fixture['company'], $fixture['maker'], ['asset_id' => $asset, 'reference' => 'DISP-'.$classification, 'disposal_date' => '2026-09-20', 'reason' => 'Approved lifecycle test', 'proceeds_minor' => $proceeds, 'idempotency_key' => 'disposal-'.strtolower($classification)]);
            $service->approveDisposal($fixture['company'], $document, $fixture['checker']);
            $service->postDisposal($fixture['company'], $document, $fixture['checker']);
            self::assertSame($document, $service->postDisposal($fixture['company'], $document, $fixture['checker']));

            $row = DB::table('finance_asset_disposals')->where('id', $document)->firstOrFail();
            self::assertSame(100_000, (int) $row->book_value_minor);
            self::assertSame($expected, (int) $row->gain_loss_minor);
            self::assertSame('disposed', DB::table('finance_assets')->where('id', $asset)->value('status'));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'asset_disposal', 'source_id' => $document])->count());
            $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'asset_disposal', 'source_id' => $document])->firstOrFail();
            self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
            self::assertNotNull($row->accounting_policy_snapshot);
        }
    }

    /** @return array{maker:User,checker:User,company:int,branch:int,category:int,fixed_asset:int,accumulated:int,cash:int,impairment_loss:int,disposal_loss:int,disposal_gain:int} */
    private function fixture(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FA-TEST', 'name' => 'Fixed Asset Test', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => 'EQUIPMENT', 'name' => 'Equipment', 'created_at' => now(), 'updated_at' => now()]);
        $fixed_asset = $this->account($company, $maker, '1300', 'Fixed Asset', 'asset', 'debit');
        $accumulated = $this->account($company, $maker, '1390', 'Accumulated Depreciation', 'contra_asset', 'credit');
        $cash = $this->account($company, $maker, '1110', 'Cash Proceeds', 'asset', 'debit');
        $impairment_loss = $this->account($company, $maker, '5310', 'Impairment Loss', 'expense', 'debit');
        $disposal_loss = $this->account($company, $maker, '5320', 'Disposal Loss', 'expense', 'debit');
        $disposal_gain = $this->account($company, $maker, '7110', 'Disposal Gain', 'revenue', 'credit');

        return compact('maker', 'checker', 'company', 'branch', 'category', 'fixed_asset', 'accumulated', 'cash', 'impairment_loss', 'disposal_loss', 'disposal_gain');
    }

    /** @param array{maker:User,company:int,branch:int,category:int} $fixture */
    private function asset(array $fixture, string $code, int $cost): int
    {
        $id = (int) DB::table('finance_assets')->insertGetId(['company_id' => $fixture['company'], 'asset_category_id' => $fixture['category'], 'branch_id' => $fixture['branch'], 'asset_code' => $code, 'name' => $code, 'acquisition_cost_minor' => $cost, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 60, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('finance_asset_assignments')->insert(['company_id' => $fixture['company'], 'asset_id' => $id, 'branch_id' => $fixture['branch'], 'effective_from' => '2026-01-01', 'reason' => 'Initial assignment', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** @param array{maker:User,checker:User,company:int} $fixture @param list<array{0:string,1:string,2:int}> $lines */
    private function policy(array $fixture, string $event, string $classification, array $lines): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($fixture['company'], $fixture['maker'], ['policy_key' => $event.'-'.$classification, 'source_type' => strtolower($event), 'business_event' => $event, 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => array_map(static fn (array $line): array => ['entry_side' => $line[0], 'line_role' => $line[1], 'account_id' => $line[2]], $lines)]);
        $service->submit($fixture['company'], $id, $fixture['maker']);
        $service->approve($fixture['company'], $id, $fixture['checker']);
    }

    /** @param array<string,mixed> $fixture */
    private function disposalPolicies(array $fixture): void
    {
        foreach (['LOSS', 'GAIN', 'EVEN'] as $classification) {
            $this->policy($fixture, 'ASSET_DISPOSAL', $classification, [['debit', 'ACCUMULATED_DEPRECIATION', $fixture['accumulated']], ['debit', 'CASH_PROCEEDS', $fixture['cash']], ['debit', 'DISPOSAL_LOSS', $fixture['disposal_loss']], ['credit', 'FIXED_ASSET', $fixture['fixed_asset']], ['credit', 'DISPOSAL_GAIN', $fixture['disposal_gain']]]);
        }
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $balance): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
