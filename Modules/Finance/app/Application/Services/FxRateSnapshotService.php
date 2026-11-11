<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class FxRateSnapshotService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $rate */
    public function capture(int $companyId, array $rate, string $sourceType, int $sourceId): int
    {
        $payload = ['company_id' => $companyId, 'fx_rate_id' => $rate['id'], 'rate_version' => $rate['version'], 'base_currency' => $rate['base_currency'], 'quote_currency' => $rate['quote_currency'], 'rate_decimal' => $rate['rate_decimal'], 'rate_date' => $rate['rate_date'], 'rate_type' => $rate['rate_type'], 'source_reference' => $rate['source_reference'], 'source_type' => $sourceType, 'source_id' => $sourceId];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $checksum = hash('sha256', $json);

        return $this->db->transaction(function () use ($companyId, $sourceType, $sourceId, $rate, $payload, $json, $checksum): int {
            $rateId = $rate['id'] ?? null;
            $canonicalRate = $this->db->table('finance_fx_rates')->where([
                'company_id' => $companyId,
                'id' => $rateId,
            ])->lockForUpdate()->first();
            if (! $canonicalRate instanceof stdClass) {
                throw ValidationException::withMessages(['snapshot' => 'FX_SNAPSHOT_RATE_NOT_FOUND']);
            }
            /** @var stdClass&object{id:int,version:int,base_currency:string,quote_currency:string,rate_decimal:string,rate_date:string,rate_type:string,source_reference:string} $canonicalRate */
            $canonical = [
                'id' => (int) $canonicalRate->id,
                'version' => (int) $canonicalRate->version,
                'base_currency' => (string) $canonicalRate->base_currency,
                'quote_currency' => (string) $canonicalRate->quote_currency,
                'rate_decimal' => (string) $canonicalRate->rate_decimal,
                'rate_date' => (string) $canonicalRate->rate_date,
                'rate_type' => (string) $canonicalRate->rate_type,
                'source_reference' => (string) $canonicalRate->source_reference,
            ];
            foreach ($canonical as $key => $value) {
                if (($rate[$key] ?? null) !== $value) {
                    throw ValidationException::withMessages(['snapshot' => 'FX_SNAPSHOT_CANONICAL_PAYLOAD_MISMATCH']);
                }
            }
            $query = $this->db->table('finance_fx_rate_snapshots')->where([
                'company_id' => $companyId, 'source_type' => $sourceType, 'source_id' => $sourceId,
                'rate_type' => $rate['rate_type'],
            ]);
            $existing = $query->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                return $this->sameSnapshot($existing, $checksum);
            }
            try {
                return (int) $this->db->table('finance_fx_rate_snapshots')->insertGetId($payload + ['checksum' => $checksum, 'snapshot' => $json, 'created_at' => now()]);
            } catch (QueryException) {
                $winner = $query->first();
                if (! $winner instanceof stdClass) {
                    throw ValidationException::withMessages(['snapshot' => 'FX_SNAPSHOT_CONCURRENT_CREATE_FAILED']);
                }

                return $this->sameSnapshot($winner, $checksum);
            }
        }, 3);
    }

    private function sameSnapshot(stdClass $snapshot, string $checksum): int
    {
        if (($snapshot->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['snapshot' => 'FX_SNAPSHOT_IMMUTABLE']);
        }

        $id = $snapshot->id ?? null;
        if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
            throw ValidationException::withMessages(['snapshot' => 'FX_SNAPSHOT_INVALID']);
        }

        return (int) $id;
    }
}
