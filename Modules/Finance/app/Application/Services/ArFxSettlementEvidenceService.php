<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class ArFxSettlementEvidenceService
{
    private const SOURCE_TYPE = 'customer_collection_allocation_settlement';

    public function __construct(private ConnectionInterface $db, private FxRateResolver $rates, private FxMoneyConverter $converter, private FxRateSnapshotService $snapshots, private ArFxSettlementCalculator $calculator) {}

    public function record(int $companyId, int $allocationId, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $allocationId, $actorId): int {
            $source = $this->db->table('customer_collection_allocations as allocation')
                ->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')
                ->join('customer_invoices as invoice', 'invoice.id', '=', 'allocation.customer_invoice_id')
                ->join('customer_invoice_fx_recognitions as recognition', 'recognition.customer_invoice_id', '=', 'invoice.id')
                ->where(['allocation.id' => $allocationId, 'collection.company_id' => $companyId, 'invoice.company_id' => $companyId, 'recognition.company_id' => $companyId])
                ->lockForUpdate()->first(['allocation.allocated_minor', 'collection.currency', 'collection.collection_date', 'invoice.id as invoice_id', 'recognition.*']);
            if (! $source instanceof stdClass) {
                throw ValidationException::withMessages(['settlement' => 'AR_FX_SETTLEMENT_SOURCE_INVALID']);
            }
            $transaction = $this->text($source->transaction_currency ?? null);
            $functional = $this->text($source->functional_currency ?? null);
            if ($this->text($source->currency ?? null) !== $transaction) {
                throw ValidationException::withMessages(['settlement' => 'AR_FX_SETTLEMENT_CURRENCY_MISMATCH']);
            }
            $foreign = $this->integer($source->allocated_minor ?? null);
            $date = $this->text($source->collection_date ?? null);
            $identity = $transaction === $functional;
            $rate = $identity ? ['id' => null, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'source_reference' => 'SAME_CURRENCY_IDENTITY'] : $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
            $settlementFunctional = $identity ? $foreign : $this->converter->convertMinor($foreign, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
            $previous = $this->db->table('customer_collection_allocation_fx_settlements')->where('customer_invoice_fx_recognition_id', $this->integer($source->id ?? null))->selectRaw('COALESCE(SUM(foreign_settled_minor),0) foreign_minor, COALESCE(SUM(released_carrying_minor),0) released_minor')->first();
            $calculated = $this->calculator->calculate($this->integer($source->transaction_amount_minor ?? null), $this->integer($source->functional_amount_minor ?? null), $this->integer($previous->foreign_minor ?? 0), $this->integer($previous->released_minor ?? 0), $foreign, $settlementFunctional);
            $snapshotId = $identity ? null : $this->snapshots->capture($companyId, $rate, self::SOURCE_TYPE, $allocationId);
            $payload = ['company_id' => $companyId, 'customer_collection_allocation_id' => $allocationId, 'customer_invoice_fx_recognition_id' => $this->integer($source->id ?? null), 'settlement_fx_snapshot_id' => $snapshotId, 'transaction_currency' => $transaction, 'functional_currency' => $functional, 'foreign_settled_minor' => $foreign, 'settlement_functional_minor' => $settlementFunctional, 'released_carrying_minor' => $calculated['released_carrying_minor'], 'realized_fx_minor' => $calculated['realized_fx_minor'], 'rate_decimal' => $this->text($rate['rate_decimal'] ?? null), 'rate_date' => $date, 'rate_type' => $this->text($rate['rate_type'] ?? null), 'source_reference' => $this->text($rate['source_reference'] ?? null)];
            $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $existing = $this->db->table('customer_collection_allocation_fx_settlements')->where('customer_collection_allocation_id', $allocationId)->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                return $this->existing($existing, $checksum);
            }
            try {
                return (int) $this->db->table('customer_collection_allocation_fx_settlements')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
            } catch (QueryException) {
                $winner = $this->db->table('customer_collection_allocation_fx_settlements')->where('customer_collection_allocation_id', $allocationId)->first();
                if (! $winner instanceof stdClass) {
                    throw ValidationException::withMessages(['settlement' => 'AR_FX_SETTLEMENT_CONCURRENT_CREATE_FAILED']);
                }

                return $this->existing($winner, $checksum);
            }
        }, 3);
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['settlement' => 'AR_FX_SETTLEMENT_IMMUTABLE']);
        }

        return $this->integer($row->id ?? null);
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 ? (int) $value : throw new \RuntimeException('Invalid AR FX settlement integer.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AR FX settlement text.');
    }
}
