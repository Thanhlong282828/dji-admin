<?php

namespace Modules\Inventory\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class InventoryAdjustmentService
{
    private const REASONS = ['STOCK_COUNT', 'DAMAGE', 'LOSS', 'EXPIRED', 'FOUND'];

    public function __construct(
        private ConnectionInterface $db,
        private InventoryService $inventory,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
    ) {}

    /** @param array<string,mixed> $data */
    public function createStockCount(int $companyId, User $actor, array $data): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('inventory_stock_counts')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $locationId = $this->positive($data, 'inventory_location_id');
            $location = $this->location($companyId, $locationId);
            $lines = $data['lines'] ?? null;
            if (! is_array($lines) || $lines === []) {
                throw ValidationException::withMessages(['lines' => 'INVENTORY_STOCK_COUNT_LINES_REQUIRED']);
            }
            $snapshots = [];
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    throw ValidationException::withMessages(['lines' => 'INVENTORY_STOCK_COUNT_LINE_INVALID']);
                }
                $variantId = $this->positive($line, 'product_variant_id');
                if (isset($snapshots[$variantId])) {
                    throw ValidationException::withMessages(['lines' => 'INVENTORY_STOCK_COUNT_VARIANT_DUPLICATE']);
                }
                $counted = $this->nonNegative($line, 'counted_qty');
                $balance = $this->balance($companyId, $locationId, $variantId, true);
                $system = $this->id($balance, 'on_hand');
                $snapshots[$variantId] = ['system' => $system, 'counted' => $counted];
            }
            $id = (int) $this->db->table('inventory_stock_counts')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->id($location, 'branch_id'), 'inventory_location_id' => $locationId,
                'reference' => $this->text($data, 'reference'), 'status' => 'draft', 'count_date' => $this->text($data, 'count_date'),
                'idempotency_key' => $key, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($snapshots as $variantId => $snapshot) {
                $this->db->table('inventory_stock_count_lines')->insert([
                    'stock_count_id' => $id, 'product_variant_id' => $variantId, 'system_qty' => $snapshot['system'], 'counted_qty' => $snapshot['counted'],
                    'variance_qty' => $snapshot['counted'] - $snapshot['system'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit($actor, 'stock-count-created', $companyId, 'stock_count_id', $id);

            return $id;
        }, 3);
    }

    public function submitStockCount(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $count = $this->stockCount($companyId, $id, true);
            if ($this->value($count, 'status') === 'submitted') {
                return $id;
            }
            if ($this->value($count, 'status') !== 'draft' || $this->id($count, 'created_by') !== (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'INVENTORY_STOCK_COUNT_TRANSITION_INVALID']);
            }
            $this->db->table('inventory_stock_counts')->where('id', $id)->update(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'stock-count-submitted', $companyId, 'stock_count_id', $id);

            return $id;
        }, 3);
    }

    public function approveStockCount(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $count = $this->stockCount($companyId, $id, true);
            if ($this->value($count, 'status') === 'approved') {
                return $id;
            }
            if ($this->value($count, 'status') !== 'submitted' || $this->id($count, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['approval' => 'INVENTORY_STOCK_COUNT_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('inventory_stock_counts')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'stock-count-approved', $companyId, 'stock_count_id', $id);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function createAdjustment(int $companyId, User $actor, array $data): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('inventory_adjustments')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $reason = strtoupper($this->text($data, 'reason'));
            if (! in_array($reason, self::REASONS, true)) {
                throw ValidationException::withMessages(['reason' => 'INVENTORY_ADJUSTMENT_REASON_INVALID']);
            }
            $stockCountId = $this->positive($data, 'stock_count_id');
            $count = $this->stockCount($companyId, $stockCountId, true);
            if ($this->value($count, 'status') !== 'approved') {
                throw ValidationException::withMessages(['stock_count_id' => 'INVENTORY_STOCK_COUNT_NOT_APPROVED']);
            }
            $lines = $this->db->table('inventory_stock_count_lines')->where('stock_count_id', $stockCountId)->where('variance_qty', '<>', 0)->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['stock_count_id' => 'INVENTORY_VARIANCE_NOT_AVAILABLE']);
            }
            $currency = null;
            $snapshots = [];
            foreach ($lines as $line) {
                $balance = $this->balance($companyId, $this->id($count, 'inventory_location_id'), $this->id($line, 'product_variant_id'), true);
                $lineCurrency = $this->value($balance, 'currency');
                $currency ??= $lineCurrency;
                if ($currency !== $lineCurrency) {
                    throw ValidationException::withMessages(['currency' => 'INVENTORY_ADJUSTMENT_CURRENCY_MISMATCH']);
                }
                $delta = $this->signed($line, 'variance_qty');
                if ($reason === 'FOUND' && $delta < 0 || in_array($reason, ['DAMAGE', 'LOSS', 'EXPIRED'], true) && $delta > 0) {
                    throw ValidationException::withMessages(['reason' => 'INVENTORY_ADJUSTMENT_REASON_VARIANCE_MISMATCH']);
                }
                $cost = $this->id($balance, 'average_unit_cost_minor');
                $snapshots[] = ['variant' => $this->id($line, 'product_variant_id'), 'delta' => $delta, 'cost' => $cost, 'value' => $delta * $cost];
            }
            $id = (int) $this->db->table('inventory_adjustments')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->id($count, 'branch_id'), 'inventory_location_id' => $this->id($count, 'inventory_location_id'),
                'stock_count_id' => $stockCountId, 'reference' => $this->text($data, 'reference'), 'reason' => $reason, 'status' => 'draft',
                'posting_date' => $this->text($data, 'posting_date'), 'currency' => $currency, 'idempotency_key' => $key,
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($snapshots as $snapshot) {
                $this->db->table('inventory_adjustment_lines')->insert([
                    'adjustment_id' => $id, 'product_variant_id' => $snapshot['variant'], 'quantity_delta' => $snapshot['delta'],
                    'unit_cost_minor' => $snapshot['cost'], 'value_delta_minor' => $snapshot['value'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit($actor, 'adjustment-created', $companyId, 'adjustment_id', $id);

            return $id;
        }, 3);
    }

    public function approveAdjustment(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $adjustment = $this->adjustment($companyId, $id, true);
            if ($this->value($adjustment, 'status') === 'approved') {
                return $id;
            }
            if ($this->value($adjustment, 'status') !== 'draft' || $this->id($adjustment, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['approval' => 'INVENTORY_ADJUSTMENT_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('inventory_adjustments')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'adjustment-approved', $companyId, 'adjustment_id', $id);

            return $id;
        }, 3);
    }

    public function postAdjustment(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();
        $this->assertCompanyAccess($companyId, $actor);

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $adjustment = $this->adjustment($companyId, $id, true);
            if ($this->value($adjustment, 'status') === 'posted') {
                return $id;
            }
            if ($this->value($adjustment, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'INVENTORY_ADJUSTMENT_TRANSITION_INVALID']);
            }
            $lines = $this->db->table('inventory_adjustment_lines')->where('adjustment_id', $id)->orderBy('id')->lockForUpdate()->get();
            $loss = 0;
            $gain = 0;
            foreach ($lines as $line) {
                $delta = $this->signed($line, 'quantity_delta');
                $key = 'inventory-adjustment-line-'.$this->id($line, 'id');
                $this->inventory->adjust($this->id($line, 'product_variant_id'), $this->id($adjustment, 'inventory_location_id'), $delta > 0 ? 'increase' : 'decrease', abs($delta), [
                    'source_type' => 'inventory_adjustment_line', 'source_id' => $this->id($line, 'id'), 'reference' => $this->value($adjustment, 'reference'),
                    'idempotency_key' => $key, 'actor_id' => $actor->id, 'reason' => $this->value($adjustment, 'reason'), 'unit_cost_minor' => $this->id($line, 'unit_cost_minor'),
                ]);
                $movementId = $this->db->table('inventory_movements')->where('idempotency_key', $key)->value('id');
                if (! is_numeric($movementId)) {
                    throw ValidationException::withMessages(['movement' => 'INVENTORY_ADJUSTMENT_MOVEMENT_MISSING']);
                }
                $this->db->table('inventory_adjustment_lines')->where('id', $this->id($line, 'id'))->update(['inventory_movement_id' => (int) $movementId, 'updated_at' => now()]);
                $value = abs($this->signed($line, 'value_delta_minor'));
                $delta > 0 ? $gain += $value : $loss += $value;
            }
            $accounting = $this->accountingLines($companyId, $adjustment, $loss, $gain);
            $journalId = $this->accounting->postInventoryAdjustment($companyId, $this->id($adjustment, 'branch_id'), $id, $this->value($adjustment, 'posting_date'), $this->value($adjustment, 'currency'), $accounting['lines'], (int) $actor->id, $accounting['policy_id'], $accounting['policy_version']);
            $this->db->table('inventory_adjustments')->where('id', $id)->update([
                'status' => 'posted', 'accounting_policy_snapshot' => json_encode($accounting['policies'], JSON_THROW_ON_ERROR), 'accounting_journal_id' => $journalId,
                'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($actor, 'adjustment-posted', $companyId, 'adjustment_id', $id, ['journal_id' => $journalId]);

            return $id;
        }, 3);
    }

    /** @return array{lines:list<array{account_code:string,debit:int,credit:int,description:string}>,policy_id:int,policy_version:int,policies:list<array{id:int,version:int,event:string}>} */
    private function accountingLines(int $companyId, object $adjustment, int $loss, int $gain): array
    {
        $lines = [];
        $snapshots = [];
        $primary = null;
        foreach ([['amount' => $loss, 'event' => 'INVENTORY_ADJUSTMENT_LOSS', 'debit' => 'INVENTORY_LOSS', 'credit' => 'INVENTORY_ASSET'], ['amount' => $gain, 'event' => 'INVENTORY_ADJUSTMENT_GAIN', 'debit' => 'INVENTORY_ASSET', 'credit' => 'INVENTORY_GAIN']] as $entry) {
            if ($entry['amount'] < 1) {
                continue;
            }
            $policy = $this->policies->resolve($companyId, $entry['event'], $this->value($adjustment, 'reason'), $this->value($adjustment, 'posting_date'), $this->value($adjustment, 'currency'));
            $debit = $this->policyAccount($policy['lines'], $entry['debit'], 'debit');
            $credit = $this->policyAccount($policy['lines'], $entry['credit'], 'credit');
            $lines[] = ['account_code' => $debit, 'debit' => $entry['amount'], 'credit' => 0, 'description' => $entry['event']];
            $lines[] = ['account_code' => $credit, 'debit' => 0, 'credit' => $entry['amount'], 'description' => $entry['event']];
            $snapshots[] = ['id' => $policy['policy_id'], 'version' => $policy['version'], 'event' => $entry['event']];
            $primary ??= $policy;
        }
        if ($primary === null) {
            throw ValidationException::withMessages(['accounting' => 'INVENTORY_ADJUSTMENT_VALUE_REQUIRED']);
        }

        return ['lines' => $lines, 'policy_id' => $primary['policy_id'], 'policy_version' => $primary['version'], 'policies' => $snapshots];
    }

    /** @param list<array{entry_side:string,line_role:string,account_code:string}> $lines */
    private function policyAccount(array $lines, string $role, string $side): string
    {
        foreach ($lines as $line) {
            if ($line['line_role'] === $role && $line['entry_side'] === $side) {
                return $line['account_code'];
            }
        }
        throw ValidationException::withMessages(['accounting_policy' => 'INVENTORY_ADJUSTMENT_POLICY_INVALID']);
    }

    private function location(int $companyId, int $locationId): object
    {
        return $this->db->table('inventory_locations as location')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->join('branches as branch', 'branch.id', '=', 'location.branch_id')->where(['location.id' => $locationId, 'seller.company_id' => $companyId, 'branch.company_id' => $companyId])->firstOrFail(['location.id', 'location.branch_id']);
    }

    private function balance(int $companyId, int $locationId, int $variantId, bool $lock): object
    {
        $query = $this->db->table('inventory_balances as balance')->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')->join('products as product', 'product.id', '=', 'variant.product_id')->where(['balance.inventory_location_id' => $locationId, 'balance.product_variant_id' => $variantId, 'seller.company_id' => $companyId])->whereColumn('product.store_id', 'store.id');

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail(['balance.*', 'product.currency']);
    }

    private function stockCount(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('inventory_stock_counts')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function adjustment(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('inventory_adjustments')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function assertSchema(): void
    {
        if (! Schema::hasTable('inventory_stock_counts') || ! Schema::hasTable('inventory_adjustments')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }
    }

    private function assertCompanyAccess(int $companyId, User $actor): void
    {
        abort_unless($actor->hasRole('Super Admin') || $this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 404);
    }

    /** @param array<string,mixed> $extra */
    private function audit(User $actor, string $event, int $companyId, string $key, int $id, array $extra = []): void
    {
        activity('inventory-governance')->causedBy($actor)->event($event)->withProperties(['company_id' => $companyId, $key => $id, ...$extra])->log($event);
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        $number = is_numeric($value) ? (int) $value : 0;

        return $number > 0 ? $number : throw ValidationException::withMessages([$key => 'Giá trị phải lớn hơn 0.']);
    }

    /** @param array<string,mixed> $data */
    private function nonNegative(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        $number = is_numeric($value) ? (int) $value : -1;

        return $number >= 0 ? $number : throw ValidationException::withMessages([$key => 'Giá trị không được âm.']);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$key => 'Giá trị bắt buộc.']);
    }

    private function id(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid inventory integer: '.$field);
    }

    private function signed(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid inventory signed integer: '.$field);
    }

    private function value(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid inventory text: '.$field);
    }
}
