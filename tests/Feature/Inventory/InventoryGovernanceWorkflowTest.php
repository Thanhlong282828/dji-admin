<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Inventory\Application\Services\InventoryAdjustmentService;
use Tests\TestCase;

final class InventoryGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_count_adjustment_movement_and_balanced_policy_journal_are_traceable(): void
    {
        [$maker, $checker, $poster, $company, $branch, $location, $variant] = $this->runtime();
        $service = app(InventoryAdjustmentService::class);
        $this->configurePolicies($company, $maker, $checker);
        $firstStockCount = null;

        foreach (['DAMAGE' => -1, 'LOSS' => -1, 'EXPIRED' => -1, 'FOUND' => 1] as $reason => $delta) {
            $system = (int) DB::table('inventory_balances')->where(['inventory_location_id' => $location, 'product_variant_id' => $variant])->value('on_hand');
            $key = strtolower($reason);
            $count = $service->createStockCount($company, $maker, [
                'inventory_location_id' => $location, 'reference' => 'COUNT-'.$reason, 'count_date' => '2026-09-29',
                'idempotency_key' => 'count-'.$key, 'lines' => [['product_variant_id' => $variant, 'counted_qty' => $system + $delta]],
            ]);
            $firstStockCount ??= $count;
            self::assertSame($count, $service->submitStockCount($company, $count, $maker));
            self::assertSame($count, $service->approveStockCount($company, $count, $checker));
            self::assertSame($delta, (int) DB::table('inventory_stock_count_lines')->where('stock_count_id', $count)->value('variance_qty'));

            $adjustment = $service->createAdjustment($company, $maker, [
                'stock_count_id' => $count, 'reference' => 'ADJ-'.$reason, 'reason' => $reason,
                'posting_date' => '2026-09-29', 'idempotency_key' => 'adjustment-'.$key,
            ]);
            self::assertSame($adjustment, $service->approveAdjustment($company, $adjustment, $checker));
            self::assertSame($adjustment, $service->postAdjustment($company, $adjustment, $poster));

            $document = DB::table('inventory_adjustments')->where('id', $adjustment)->firstOrFail();
            self::assertSame('posted', $document->status);
            self::assertNotNull($document->accounting_policy_snapshot);
            self::assertNotNull($document->accounting_journal_id);
            $line = DB::table('inventory_adjustment_lines')->where('adjustment_id', $adjustment)->firstOrFail();
            self::assertNotNull($line->inventory_movement_id);
            self::assertSame('inventory_adjustment_line', DB::table('inventory_movements')->where('id', $line->inventory_movement_id)->value('source_type'));

            $journal = DB::table('payroll_accounting_journals')->where('id', $document->accounting_journal_id)->firstOrFail();
            self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
            $codes = DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->orderByDesc('debit_minor')->pluck('account_code_snapshot')->all();
            self::assertSame($delta < 0 ? ['5199', '1200'] : ['1200', '4199'], $codes);
        }

        self::assertSame(10, (int) DB::table('inventory_balances')->where(['inventory_location_id' => $location, 'product_variant_id' => $variant])->value('on_hand'));
        self::assertNotNull($firstStockCount);
        self::assertSame($company, (int) DB::table('inventory_stock_counts')->where('id', $firstStockCount)->value('company_id'));
        self::assertSame($branch, (int) DB::table('inventory_stock_counts')->where('id', $firstStockCount)->value('branch_id'));
    }

    /** @return array{User,User,User,int,int,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $poster = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'INV-GOV', 'name' => 'Inventory Governance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker, $poster] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Inventory', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Store', 'slug' => 'inventory-governance', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'INV-P', 'name' => 'Inventory Product', 'slug' => 'inventory-product', 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'INV-SKU', 'name' => 'Default', 'price_minor' => 1000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'WH', 'name' => 'Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 12, 'reserved' => 0, 'inventory_value_minor' => 1200, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $poster, $company, $branch, $location, $variant];
    }

    private function configurePolicies(int $company, User $maker, User $checker): void
    {
        $accounts = [
            'loss' => $this->account($company, $maker, '5199', 'Inventory Loss', 'expense', 'debit'),
            'inventory' => $this->account($company, $maker, '1200', 'Inventory', 'asset', 'debit'),
            'gain' => $this->account($company, $maker, '4199', 'Inventory Gain', 'revenue', 'credit'),
        ];
        $service = app(AccountingPostingPolicyService::class);
        foreach (['DAMAGE', 'LOSS', 'EXPIRED', 'FOUND'] as $reason) {
            $gain = $reason === 'FOUND';
            $policy = $service->create($company, $maker, [
                'policy_key' => 'INV-'.$reason, 'source_type' => 'inventory_adjustment',
                'business_event' => $gain ? 'INVENTORY_ADJUSTMENT_GAIN' : 'INVENTORY_ADJUSTMENT_LOSS',
                'classification' => $reason, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01',
                'lines' => $gain
                    ? [['entry_side' => 'debit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $accounts['inventory']], ['entry_side' => 'credit', 'line_role' => 'INVENTORY_GAIN', 'account_id' => $accounts['gain']]]
                    : [['entry_side' => 'debit', 'line_role' => 'INVENTORY_LOSS', 'account_id' => $accounts['loss']], ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $accounts['inventory']]],
            ]);
            $service->submit($company, $policy, $maker);
            $service->approve($company, $policy, $checker);
        }
    }

    private function account(int $company, User $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
