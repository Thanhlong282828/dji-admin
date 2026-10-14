<?php

namespace Tests\Concurrency\Maintenance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Maintenance\Application\Services\MaintenanceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class MaintenancePartIssueProcessConcurrencyTest extends TestCase
{
    public function test_two_processes_issue_same_requirement_exactly_once(): void
    {
        $fixture = $this->fixture();
        try {
            $payload = ['company_id' => $fixture['company'], 'order_id' => $fixture['order'], 'part_id' => $fixture['part'], 'actor_id' => $fixture['maker']->id, 'request_idempotency_key' => 'request-a'];
            $other = array_replace($payload, ['actor_id' => $fixture['checker']->id, 'request_idempotency_key' => 'request-b']);
            $race = (new MysqlProcessRaceRunner)->run('maintenance_part_issue', $payload, $other);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('ISSUED', DB::table('maintenance_part_requirements')->where('id', $fixture['part'])->value('status'));
            self::assertSame(8, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('on_hand'));
            self::assertSame(8_000, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('inventory_value_minor'));
            self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', $fixture['key'])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $fixture['part'], 'posting_type' => 'maintenance_part_consumption'])->count());
            self::assertSame(1, DB::table('activity_log')->where(['log_name' => 'maintenance', 'event' => 'part_issued'])->whereRaw("JSON_EXTRACT(properties, '$.maintenance_id') = ?", [$fixture['order']])->whereRaw("JSON_EXTRACT(properties, '$.part_id') = ?", [$fixture['part']])->count());
            $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $fixture['part']])->firstOrFail();
            self::assertSame(2_000, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->sum('debit_minor'));
            self::assertSame(2_000, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->sum('credit_minor'));
            $snapshots = DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->pluck('mapping_snapshot')->map(static fn (string $value): array => json_decode($value, true, 512, JSON_THROW_ON_ERROR));
            self::assertTrue($snapshots->every(fn (array $snapshot): bool => $snapshot['policy_id'] === $fixture['policy'] && $snapshot['policy_version'] === 1));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_two_processes_cannot_double_return_same_business_reference_with_different_keys(): void
    {
        $fixture = $this->fixture();
        try {
            app(MaintenanceService::class)->issuePart($fixture['company'], $fixture['order'], $fixture['part'], (int) $fixture['maker']->id);
            $payload = ['company_id' => $fixture['company'], 'order_id' => $fixture['order'], 'part_id' => $fixture['part'], 'actor_id' => $fixture['maker']->id, 'quantity' => 1, 'return_reference' => 'RETURN-'.$fixture['key'], 'idempotency_key' => 'return-a-'.$fixture['key'], 'return_date' => '2026-10-12', 'reason' => 'Unused physical spare part return'];
            $other = array_replace($payload, ['actor_id' => $fixture['checker']->id, 'idempotency_key' => 'return-b-'.$fixture['key']]);
            $race = (new MysqlProcessRaceRunner)->run('maintenance_part_return', $payload, $other);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertStringContainsString('MAINTENANCE_PART_RETURN_CONFLICT', implode("\n", $race['outputs']));
            $return = DB::table('maintenance_part_returns')->where('maintenance_part_requirement_id', $fixture['part'])->firstOrFail();
            self::assertSame(1, DB::table('maintenance_part_returns')->where('maintenance_part_requirement_id', $fixture['part'])->count());
            self::assertSame(1, DB::table('inventory_movements')->where(['source_type' => 'maintenance_part_return', 'source_id' => $return->id])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_return', 'source_id' => $return->id])->count());
            self::assertSame(9, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('on_hand'));
            self::assertSame(9_000, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('inventory_value_minor'));
            self::assertSame(1, DB::table('activity_log')->where(['log_name' => 'maintenance', 'event' => 'part_returned'])->whereRaw("JSON_EXTRACT(properties, '$.part_return_id') = ?", [$return->id])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{maker:User,checker:User,company:int,branch:int,assetCategory:int,asset:int,order:int,profile:int,store:int,product:int,variant:int,location:int,part:int,policy:int,returnPolicy:int,key:string} */
    private function fixture(): array
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "maintenance-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "maintenance-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'MWO-'.$token, 'name' => 'Maintenance Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $assetCategory = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => 'CAT-'.$token, 'name' => 'Equipment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $asset = (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $assetCategory, 'branch_id' => $branch, 'asset_code' => 'ASSET-'.$token, 'name' => 'Race Asset', 'acquisition_cost_minor' => 100_000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-02', 'useful_life_months' => 36, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = (int) DB::table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'WO-'.$token, 'title' => 'Race Work Order', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'HIGH', 'status' => 'IN_PROGRESS', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Maintenance Race', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Maintenance Race', 'slug' => 'maintenance-race-'.$token, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'P-'.$token, 'name' => 'Spare Part', 'slug' => 'spare-'.$token, 'base_price_minor' => 1_000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'SKU-'.$token, 'name' => 'Default', 'price_minor' => 1_000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'WH-'.$token, 'name' => 'Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 10_000, 'average_unit_cost_minor' => 1_000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $key = 'maintenance-part-'.$token;
        $part = (int) DB::table('maintenance_part_requirements')->insertGetId(['work_order_id' => $order, 'product_variant_id' => $variant, 'inventory_location_id' => $location, 'quantity' => 2, 'status' => 'REQUIRED', 'idempotency_key' => $key, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $expense = $this->account($company, $maker, '5100', 'Maintenance Expense', 'expense', 'debit');
        $inventory = $this->account($company, $maker, '1200', 'Inventory Asset', 'asset', 'debit');
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'MWO-PART-'.$token, 'source_type' => 'maintenance_part_requirement', 'business_event' => 'MAINTENANCE_PART_CONSUMPTION', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense], ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);
        $returnPolicy = $policies->create($company, $maker, ['policy_key' => 'MWO-PART-RETURN-'.$token, 'source_type' => 'maintenance_part_return', 'business_event' => 'MAINTENANCE_PART_RETURN', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory], ['entry_side' => 'credit', 'line_role' => 'EXPENSE', 'account_id' => $expense]]]);
        $policies->submit($company, $returnPolicy, $maker);
        $policies->approve($company, $returnPolicy, $checker);

        return compact('maker', 'checker', 'company', 'branch', 'assetCategory', 'asset', 'order', 'profile', 'store', 'product', 'variant', 'location', 'part', 'policy', 'returnPolicy', 'key');
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{maker:User,checker:User,company:int,branch:int,assetCategory:int,asset:int,order:int,profile:int,store:int,product:int,variant:int,location:int,part:int,policy:int,returnPolicy:int,key:string} $fixture */
    private function cleanup(array $fixture): void
    {
        DB::table('maintenance_part_returns')->where('maintenance_part_requirement_id', $fixture['part'])->delete();
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $fixture['company'])->pluck('id');
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('inventory_movements')->where('inventory_location_id', $fixture['location'])->delete();
        DB::table('maintenance_part_requirements')->where('id', $fixture['part'])->delete();
        DB::table('maintenance_work_orders')->where('id', $fixture['order'])->delete();
        DB::table('inventory_balances')->where('inventory_location_id', $fixture['location'])->delete();
        DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', [$fixture['policy'], $fixture['returnPolicy']])->delete();
        DB::table('accounting_posting_policies')->whereIn('id', [$fixture['policy'], $fixture['returnPolicy']])->delete();
        DB::table('accounting_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('inventory_locations')->where('id', $fixture['location'])->delete();
        DB::table('product_variants')->where('id', $fixture['variant'])->delete();
        DB::table('products')->where('id', $fixture['product'])->delete();
        DB::table('stores')->where('id', $fixture['store'])->delete();
        DB::table('seller_profiles')->where('id', $fixture['profile'])->delete();
        DB::table('finance_assets')->where('id', $fixture['asset'])->delete();
        DB::table('finance_asset_categories')->where('id', $fixture['assetCategory'])->delete();
        DB::table('activity_log')->whereIn('causer_id', [$fixture['maker']->id, $fixture['checker']->id])->delete();
        DB::table('company_user_access')->where('company_id', $fixture['company'])->delete();
        DB::table('branches')->where('id', $fixture['branch'])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
        $fixture['maker']->delete();
        $fixture['checker']->delete();
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
