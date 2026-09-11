<?php

namespace Modules\Commerce\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use RuntimeException;

final readonly class CommerceRevenueRecognitionService
{
    public function __construct(private ConnectionInterface $db, private SourceAccountingPostingService $accounting) {}

    public function recognizeIfEligible(int $orderId, int $actorId): bool
    {
        $order = $this->db->table('orders as o')->join('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')
            ->where('o.id', $orderId)->lockForUpdate()->first(['o.id', 'o.order_number', 'o.payment_status', 'o.subtotal_minor', 'o.currency', 'o.fulfilled_at', 'f.company_id', 'f.branch_id', 'f.status as fulfillment_status', 'f.delivered_at']);
        if ($order === null || $this->text($order, 'payment_status') !== 'paid' || $this->text($order, 'fulfillment_status') !== 'delivered') {
            return false;
        }
        $reference = 'ORDER-'.$this->text($order, 'order_number');
        $inserted = $this->db->table('branch_management_revenues')->insertOrIgnore([
            'company_id' => $this->integer($order, 'company_id'),
            'branch_id' => $this->integer($order, 'branch_id'),
            'order_id' => $orderId,
            'reference' => $reference,
            'source_type' => 'commerce_order',
            'amount_minor' => $this->integer($order, 'subtotal_minor'),
            'currency_code' => $this->text($order, 'currency'),
            'recognized_on' => $this->date($order->delivered_at ?? null),
            'status' => 'recognized',
            'description' => 'Commerce revenue recognized from paid and delivered Order; shipping excluded.',
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $date = $this->date($order->delivered_at ?? null);
        $company = $this->integer($order, 'company_id');
        $branch = $this->integer($order, 'branch_id');
        $revenue = $this->integer($order, 'subtotal_minor');
        $currency = $this->text($order, 'currency');
        $this->accounting->post($company, $branch, 'marketplace_order', $orderId, 'marketplace_revenue', $date, $currency, [['account_code' => '1100', 'debit' => $revenue, 'credit' => 0, 'description' => 'Marketplace payment clearing'], ['account_code' => '4000', 'debit' => 0, 'credit' => $revenue, 'description' => 'Marketplace sales revenue']], $actorId);
        $cogs = abs((int) $this->db->table('inventory_movements')->where(['source_type' => 'commerce_order', 'source_id' => $orderId])->sum('movement_value_minor'));
        if ($cogs > 0) {
            $this->accounting->post($company, $branch, 'marketplace_order', $orderId, 'marketplace_cogs', $date, $currency, [['account_code' => '5000', 'debit' => $cogs, 'credit' => 0, 'description' => 'Marketplace cost of goods sold'], ['account_code' => '1200', 'debit' => 0, 'credit' => $cogs, 'description' => 'Marketplace inventory issue']], $actorId);
        }

        return $inserted === 1;
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new RuntimeException("Commerce revenue {$key} is invalid.");
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new RuntimeException("Commerce revenue {$key} is invalid.");
    }

    private function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        throw new RuntimeException('Fulfillment delivery date is invalid.');
    }
}
