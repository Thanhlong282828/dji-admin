<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Inventory\Domain\Services\InventoryService;
use RuntimeException;

final readonly class SaleReversalService
{
    public function __construct(private ConnectionInterface $db, private InventoryService $inventory, private SourceAccountingPostingService $accounting) {}

    public function reverse(User $actor, int $companyId, string $source, int $sourceId, string $reason): int
    {
        if (! in_array($source, ['marketplace', 'pos'], true) || trim($reason) === '') {
            throw ValidationException::withMessages(['reversal' => 'Nguồn và lý do reversal không hợp lệ.']);
        }

        return $this->db->transaction(function () use ($actor, $companyId, $source, $sourceId, $reason): int {
            $existing = $this->db->table('commerce_sale_reversals')->where(['company_id' => $companyId, 'source_type' => $source, 'source_id' => $sourceId])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $sale = $source === 'pos' ? $this->pos($companyId, $sourceId) : $this->marketplace($companyId, $sourceId);
            $reference = 'REV-'.strtoupper($source).'-'.$sourceId;
            $reversalId = (int) $this->db->table('commerce_sale_reversals')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->integer($sale, 'branch_id'), 'source_type' => $source, 'source_id' => $sourceId, 'original_revenue_id' => $this->integer($sale, 'revenue_id'), 'reference' => $reference, 'amount_minor' => $this->integer($sale, 'amount_minor'), 'currency_code' => $this->text($sale, 'currency_code'), 'status' => 'completed', 'inventory_restocked' => false, 'reason' => trim($reason), 'created_by' => $actor->id, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($source === 'pos') {
                $this->restockPos($sourceId, $reversalId, $reference, $actor);
            } else {
                $this->restockMarketplace($sourceId, $reversalId, $reference, $actor);
            }
            $this->db->table('commerce_sale_reversals')->where('id', $reversalId)->update(['inventory_restocked' => true, 'updated_at' => now()]);
            $date = now()->toDateString();
            $amount = $this->integer($sale, 'amount_minor');
            $branch = $this->integer($sale, 'branch_id');
            $currency = $this->text($sale, 'currency_code');
            $this->accounting->post($companyId, $branch, 'sale_reversal', $reversalId, $source.'_revenue_reversal', $date, $currency, [['account_code' => '4000', 'debit' => $amount, 'credit' => 0, 'description' => 'Reverse sales revenue'], ['account_code' => '1100', 'debit' => 0, 'credit' => $amount, 'description' => 'Reverse payment clearing']], $actor->id);
            $cogs = (int) $this->db->table('inventory_movements')->where(['source_type' => 'sale_reversal', 'source_id' => $reversalId])->sum('movement_value_minor');
            if ($cogs > 0) {
                $this->accounting->post($companyId, $branch, 'sale_reversal', $reversalId, $source.'_cogs_reversal', $date, $currency, [['account_code' => '1200', 'debit' => $cogs, 'credit' => 0, 'description' => 'Restore inventory value'], ['account_code' => '5000', 'debit' => 0, 'credit' => $cogs, 'description' => 'Reverse cost of goods sold']], $actor->id);
            }
            activity('commerce.reversal')->causedBy($actor)->withProperties(['reversal_id' => $reversalId, 'source' => $source, 'source_id' => $sourceId, 'branch_id' => $this->integer($sale, 'branch_id'), 'amount_minor' => $this->integer($sale, 'amount_minor')])->log('sale.reversed');

            return $reversalId;
        }, 3);
    }

    private function pos(int $companyId, int $receiptId): object
    {
        return $this->db->table('branch_pos_receipts as receipt')->join('branch_management_revenues as revenue', 'revenue.id', '=', 'receipt.revenue_id')->where(['receipt.id' => $receiptId, 'receipt.company_id' => $companyId, 'receipt.status' => 'paid', 'revenue.status' => 'recognized', 'revenue.source_type' => 'branch_pos'])->lockForUpdate()->firstOrFail(['receipt.branch_id', 'receipt.revenue_id', 'revenue.amount_minor', 'revenue.currency_code']);
    }

    private function marketplace(int $companyId, int $orderId): object
    {
        return $this->db->table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->join('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')->join('branch_management_revenues as revenue', static fn (JoinClause $join): JoinClause => $join->on('revenue.order_id', '=', 'o.id')->where('revenue.source_type', 'commerce_order')->where('revenue.status', 'recognized'))->where(['o.id' => $orderId, 'sp.company_id' => $companyId, 'o.payment_status' => 'paid', 'f.status' => 'delivered'])->lockForUpdate()->firstOrFail(['f.branch_id', 'revenue.id as revenue_id', 'revenue.amount_minor', 'revenue.currency_code']);
    }

    private function restockPos(int $receiptId, int $reversalId, string $reference, User $actor): void
    {
        $lines = $this->db->table('branch_pos_receipts as r')->join('customer_visit_products as line', 'line.customer_visit_id', '=', 'r.customer_visit_id')->where('r.id', $receiptId)->get(['r.customer_visit_id', 'line.id', 'line.product_variant_id', 'line.inventory_location_id', 'line.quantity']);
        foreach ($lines as $line) {
            $visitId = $this->integer($line, 'customer_visit_id');
            $unitCost = $this->originalUnitCost('branch_pos_visit', $visitId, 'pos-visit:'.$visitId.':line:'.$this->integer($line, 'id'));
            $this->inventory->adjust($this->integer($line, 'product_variant_id'), $this->integer($line, 'inventory_location_id'), 'increase', $this->integer($line, 'quantity'), ['source_type' => 'sale_reversal', 'source_id' => $reversalId, 'reference' => $reference, 'idempotency_key' => 'sale-reversal-'.$reversalId.'-pos-line-'.$this->integer($line, 'id'), 'actor_id' => $actor->id, 'reason' => 'POS full sale reversal restock', 'unit_cost_minor' => $unitCost]);
        }
    }

    private function restockMarketplace(int $orderId, int $reversalId, string $reference, User $actor): void
    {
        $reservations = $this->db->table('inventory_reservations as r')->join('inventory_balances as b', 'b.id', '=', 'r.inventory_balance_id')->where(['r.order_id' => $orderId, 'r.status' => 'consumed'])->get(['r.id', 'r.product_variant_id', 'r.inventory_balance_id', 'r.quantity', 'b.inventory_location_id']);
        foreach ($reservations as $reservation) {
            $unitCost = $this->originalUnitCost('commerce_order', $orderId, 'order:'.$orderId.':reservation:'.$this->integer($reservation, 'id'));
            $this->inventory->adjust($this->integer($reservation, 'product_variant_id'), $this->integer($reservation, 'inventory_location_id'), 'increase', $this->integer($reservation, 'quantity'), ['source_type' => 'sale_reversal', 'source_id' => $reversalId, 'reference' => $reference, 'idempotency_key' => 'sale-reversal-'.$reversalId.'-reservation-'.$this->integer($reservation, 'id'), 'actor_id' => $actor->id, 'reason' => 'Marketplace full sale reversal restock', 'unit_cost_minor' => $unitCost]);
        }
    }

    private function originalUnitCost(string $sourceType, int $sourceId, string $idempotencyKey): int
    {
        $value = $this->db->table('inventory_movements')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'idempotency_key' => $idempotencyKey])->value('unit_cost_minor');

        return is_numeric($value) ? (int) $value : 0;
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException("Sale reversal {$key} invalid.");
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }
        throw new RuntimeException("Sale reversal {$key} invalid.");
    }
}
