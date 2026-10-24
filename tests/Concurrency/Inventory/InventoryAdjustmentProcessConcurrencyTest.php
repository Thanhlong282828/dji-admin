<?php

namespace Tests\Concurrency\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class InventoryAdjustmentProcessConcurrencyTest extends TestCase
{
    public function test_competing_posts_create_one_movement_and_one_journal(): void
    {
        $fixture = $this->fixture('POST');
        try {
            $payload = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment'], 'actor_id' => $fixture['checker']->id];
            $other = array_replace($payload, ['actor_id' => $fixture['reviewer']->id]);
            $race = (new MysqlProcessRaceRunner)->run('inventory_adjustment_post', $payload, $other);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('inventory_movements')->where(['source_type' => 'inventory_adjustment_line', 'source_id' => $fixture['line']])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['company_id' => $fixture['company'], 'source_type' => 'inventory_adjustment', 'source_id' => $fixture['adjustment']])->count());
            self::assertSame(8, (int) DB::table('inventory_balances')->where(['product_variant_id' => $fixture['variant'], 'inventory_location_id' => $fixture['location']])->value('on_hand'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_same_idempotency_stock_count_race_creates_one_document(): void
    {
        $fixture = $this->fixture('IDEMPOTENCY');
        try {
            $key = 'stock-count-'.$fixture['token'];
            $payload = ['company_id' => $fixture['company'], 'actor_id' => $fixture['maker']->id, 'inventory_location_id' => $fixture['location'], 'product_variant_id' => $fixture['variant'], 'reference' => 'COUNT-'.$fixture['token'], 'count_date' => '2026-09-29', 'counted_qty' => 8, 'idempotency_key' => $key];
            $race = (new MysqlProcessRaceRunner)->run('inventory_stock_count_create', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('inventory_stock_counts')->where(['company_id' => $fixture['company'], 'idempotency_key' => $key])->count());
            $count = (int) DB::table('inventory_stock_counts')->where('idempotency_key', $key)->value('id');
            self::assertSame(1, DB::table('inventory_stock_count_lines')->where('stock_count_id', $count)->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{token:string,maker:User,checker:User,reviewer:User,company:int,branch:int,profile:int,store:int,product:int,variant:int,location:int,policy:int,adjustment:int,line:int} */
    private function fixture(string $label): array
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "inventory-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "inventory-checker-{$token}@example.test"]);
        $reviewer = User::factory()->create(['email' => "inventory-reviewer-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'IAR-'.$token, 'name' => 'Inventory Adjustment '.$label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker, $reviewer] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Inventory Race', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Inventory Race', 'slug' => 'inventory-race-'.$token, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'P-'.$token, 'name' => 'Counted Product', 'slug' => 'counted-'.$token, 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'SKU-'.$token, 'name' => 'Default', 'price_minor' => 1000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'WH-'.$token, 'name' => 'Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $loss = $this->ledger($company, $maker, '5199', 'Inventory Loss', 'expense', 'debit');
        $inventory = $this->ledger($company, $maker, '1200', 'Inventory Asset', 'asset', 'debit');
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, ['policy_key' => 'INV-LOSS-'.$token, 'source_type' => 'inventory_adjustment', 'business_event' => 'INVENTORY_ADJUSTMENT_LOSS', 'classification' => 'STOCK_COUNT', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'INVENTORY_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory]]]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);
        $adjustment = (int) DB::table('inventory_adjustments')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'inventory_location_id' => $location, 'reference' => 'ADJ-'.$token, 'reason' => 'STOCK_COUNT', 'status' => 'approved', 'posting_date' => '2026-09-29', 'currency' => 'VND', 'idempotency_key' => 'adjustment-'.$token, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $line = (int) DB::table('inventory_adjustment_lines')->insertGetId(['adjustment_id' => $adjustment, 'product_variant_id' => $variant, 'quantity_delta' => -2, 'unit_cost_minor' => 100, 'value_delta_minor' => -200, 'created_at' => now(), 'updated_at' => now()]);

        return compact('token', 'maker', 'checker', 'reviewer', 'company', 'branch', 'profile', 'store', 'product', 'variant', 'location', 'policy', 'adjustment', 'line');
    }

    private function ledger(int $company, User $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{maker:User,checker:User,reviewer:User,company:int,branch:int,profile:int,store:int,product:int,variant:int,location:int,policy:int} $fixture */
    private function cleanup(array $fixture): void
    {
        $journals = DB::table('payroll_accounting_journals')->where('company_id', $fixture['company'])->pluck('id');
        DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
        DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
        DB::table('inventory_adjustment_lines')->whereIn('adjustment_id', DB::table('inventory_adjustments')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('inventory_adjustments')->where('company_id', $fixture['company'])->delete();
        DB::table('inventory_stock_count_lines')->whereIn('stock_count_id', DB::table('inventory_stock_counts')->where('company_id', $fixture['company'])->select('id'))->delete();
        DB::table('inventory_stock_counts')->where('company_id', $fixture['company'])->delete();
        DB::table('inventory_movements')->where('inventory_location_id', $fixture['location'])->delete();
        DB::table('inventory_balances')->where('inventory_location_id', $fixture['location'])->delete();
        DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
        DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $fixture['policy'])->delete();
        DB::table('accounting_posting_policies')->where('id', $fixture['policy'])->delete();
        DB::table('accounting_periods')->where('company_id', $fixture['company'])->delete();
        DB::table('accounting_accounts')->where('company_id', $fixture['company'])->delete();
        DB::table('inventory_locations')->where('id', $fixture['location'])->delete();
        DB::table('product_variants')->where('id', $fixture['variant'])->delete();
        DB::table('products')->where('id', $fixture['product'])->delete();
        DB::table('stores')->where('id', $fixture['store'])->delete();
        DB::table('seller_profiles')->where('id', $fixture['profile'])->delete();
        DB::table('company_user_access')->where('company_id', $fixture['company'])->delete();
        DB::table('branches')->where('id', $fixture['branch'])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
        $fixture['maker']->delete();
        $fixture['checker']->delete();
        $fixture['reviewer']->delete();
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
