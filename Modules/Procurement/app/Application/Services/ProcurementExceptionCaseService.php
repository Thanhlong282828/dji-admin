<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;

final readonly class ProcurementExceptionCaseService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $context */
    public function record(int $companyId, string $type, string $reason, array $context): int
    {
        $source = implode('|', [$companyId, $type, $this->identifier($context['goods_return_id'] ?? null), $this->identifier($context['vendor_bill_id'] ?? null)]);
        $fingerprint = hash('sha256', $source);
        $values = [
            'severity' => in_array($type, ['SUPPLIER_CREDIT_PAID_AP_REQUIRES_MODEL', 'GOODS_RETURN_VALUATION_EVIDENCE_INSUFFICIENT'], true) ? 'CRITICAL' : 'WARNING',
            'status' => 'open',
            'vendor_id' => $context['vendor_id'] ?? null,
            'purchase_order_id' => $context['purchase_order_id'] ?? null,
            'goods_receipt_id' => $context['goods_receipt_id'] ?? null,
            'goods_return_id' => $context['goods_return_id'] ?? null,
            'vendor_bill_id' => $context['vendor_bill_id'] ?? null,
            'reason' => mb_substr($reason, 0, 500),
            'evidence' => json_encode($context, JSON_THROW_ON_ERROR),
            'detected_at' => now(),
            'resolved_at' => null,
            'resolution_reference' => null,
            'updated_at' => now(),
        ];
        $existing = $this->db->table('procurement_exception_cases')->where(['company_id' => $companyId, 'fingerprint' => $fingerprint])->value('id');
        if (is_numeric($existing)) {
            $this->db->table('procurement_exception_cases')->where('id', $existing)->update($values);

            return (int) $existing;
        }

        return (int) $this->db->table('procurement_exception_cases')->insertGetId([
            'company_id' => $companyId,
            'exception_type' => $type,
            'fingerprint' => $fingerprint,
            ...$values,
            'created_at' => now(),
        ]);
    }

    public function resolveForReturn(int $companyId, int $goodsReturnId, string $reference): void
    {
        $this->db->table('procurement_exception_cases')->where([
            'company_id' => $companyId,
            'goods_return_id' => $goodsReturnId,
            'status' => 'open',
        ])->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution_reference' => $reference, 'updated_at' => now()]);
    }

    private function identifier(mixed $value): string
    {
        return is_int($value) || is_string($value) ? (string) $value : '0';
    }
}
