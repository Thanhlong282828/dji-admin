<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateResolver;
use Modules\Finance\Application\Services\FxRateSnapshotService;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class InventoryReceiptFxValuationService
{
    private const SOURCE_TYPE = 'inventory_receipt_valuation';

    public function __construct(
        private ConnectionInterface $db,
        private FxRateResolver $rates,
        private FxMoneyConverter $converter,
        private FxRateSnapshotService $snapshots,
    ) {}

    /** @return array<string,mixed> */
    public function prepare(int $companyId, stdClass $receipt, stdClass $line, string $transactionCurrency): array
    {
        $company = $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->first(['functional_currency_code']);
        $functional = $company instanceof stdClass && is_string($company->functional_currency_code)
            ? strtoupper($company->functional_currency_code)
            : '';
        if ($functional === '') {
            throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
        }

        $transaction = strtoupper($transactionCurrency);
        $foreign = $this->integer($line->line_value_minor ?? null);
        $quantity = $this->positive($line->received_quantity ?? null);
        $date = $this->text($receipt->receipt_date ?? null);
        $lineId = $this->positive($line->id ?? null);
        $identity = $transaction === $functional;
        $rate = $identity
            ? ['id' => null, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'source_reference' => 'SAME_CURRENCY_IDENTITY']
            : $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
        $functionalAmount = $identity ? $foreign : $this->converter->convertMinor($foreign, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
        $snapshotId = $identity ? null : $this->snapshots->capture($companyId, $rate, self::SOURCE_TYPE, $lineId);

        return [
            'transaction_currency' => $transaction,
            'foreign_amount_minor' => $foreign,
            'functional_currency' => $functional,
            'functional_amount_minor' => $functionalAmount,
            'functional_unit_cost_minor' => intdiv($functionalAmount + intdiv($quantity, 2), $quantity),
            'fx_rate_snapshot_id' => $snapshotId,
            'rate_decimal' => $this->text($rate['rate_decimal'] ?? null),
            'recognition_date' => $date,
            'rate_type' => $this->text($rate['rate_type'] ?? null),
            'source_reference' => $this->text($rate['source_reference'] ?? null),
        ];
    }

    /** @param array<string,mixed> $valuation */
    public function record(int $companyId, stdClass $receipt, stdClass $line, array $valuation, int $movementId, int $policyId, int $policyVersion, int $journalId, int $actorId): int
    {
        $lineId = $this->positive($line->id ?? null);
        $payload = [
            'company_id' => $companyId,
            'branch_id' => $this->positive($receipt->branch_id ?? null),
            'purchase_order_id' => $this->positive($receipt->purchase_order_id ?? null),
            'goods_receipt_id' => $this->positive($receipt->id ?? null),
            'goods_receipt_line_id' => $lineId,
            'product_variant_id' => $this->positive($line->product_variant_id ?? null),
            'inventory_movement_id' => $movementId,
            'transaction_currency' => $valuation['transaction_currency'],
            'foreign_amount_minor' => $valuation['foreign_amount_minor'],
            'functional_currency' => $valuation['functional_currency'],
            'functional_amount_minor' => $valuation['functional_amount_minor'],
            'functional_unit_cost_minor' => $valuation['functional_unit_cost_minor'],
            'fx_rate_snapshot_id' => $valuation['fx_rate_snapshot_id'],
            'rate_decimal' => $valuation['rate_decimal'],
            'recognition_date' => $valuation['recognition_date'],
            'rate_type' => $valuation['rate_type'],
            'source_reference' => $valuation['source_reference'],
            'accounting_policy_id' => $policyId,
            'accounting_policy_version' => $policyVersion,
            'accounting_journal_id' => $journalId,
            'version' => 1,
        ];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $lineId)->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            return $this->existing($existing, $checksum);
        }
        try {
            return (int) $this->db->table('inventory_receipt_fx_valuations')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        } catch (QueryException) {
            $winner = $this->db->table('inventory_receipt_fx_valuations')->where('goods_receipt_line_id', $lineId)->first();
            if (! $winner instanceof stdClass) {
                throw ValidationException::withMessages(['valuation' => 'INVENTORY_RECEIPT_FX_CONCURRENT_CREATE_FAILED']);
            }

            return $this->existing($winner, $checksum);
        }
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['valuation' => 'INVENTORY_RECEIPT_FX_IMMUTABLE']);
        }

        return $this->positive($row->id ?? null);
    }

    private function positive(mixed $value): int
    {
        $value = $this->integer($value);

        return $value > 0 ? $value : throw new \RuntimeException('Invalid positive inventory FX evidence.');
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0
            ? (int) $value
            : throw new \RuntimeException('Invalid inventory FX integer evidence.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid inventory FX text evidence.');
    }
}
