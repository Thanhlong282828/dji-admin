<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\DTOs\FxRecognitionResult;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class CustomerInvoiceFxRecognitionService
{
    private const SOURCE_TYPE = 'customer_invoice_recognition';

    public function __construct(
        private ConnectionInterface $db,
        private FxRateResolver $rates,
        private FxMoneyConverter $converter,
        private FxRateSnapshotService $snapshots,
    ) {}

    public function recognize(int $companyId, int $invoiceId, int $actorId): FxRecognitionResult
    {
        return $this->db->transaction(function () use ($companyId, $invoiceId, $actorId): FxRecognitionResult {
            $invoice = $this->db->table('customer_invoices')->where(['id' => $invoiceId, 'company_id' => $companyId])->lockForUpdate()->first();
            if (! $invoice instanceof stdClass) {
                throw ValidationException::withMessages(['invoice' => 'AR_FX_INVOICE_NOT_FOUND']);
            }
            $company = $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->first(['functional_currency_code']);
            $functional = $company instanceof stdClass && is_string($company->functional_currency_code) ? strtoupper($company->functional_currency_code) : '';
            if ($functional === '') {
                throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
            }
            $transaction = strtoupper($this->text($invoice->currency ?? null));
            $amount = $this->nonNegativeInt($invoice->total_minor ?? null);
            $date = $this->text($invoice->invoice_date ?? null);
            $branchId = $this->nullableInt($invoice->branch_id ?? null);
            $identity = $transaction === $functional;
            $rate = $identity
                ? ['id' => null, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'source_reference' => 'SAME_CURRENCY_IDENTITY']
                : $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
            $functionalAmount = $identity ? $amount : $this->converter->convertMinor($amount, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
            $snapshotId = $identity ? null : $this->snapshots->capture($companyId, $rate, self::SOURCE_TYPE, $invoiceId);
            $payload = [
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_invoice_id' => $invoiceId,
                'transaction_currency' => $transaction, 'transaction_amount_minor' => $amount,
                'functional_currency' => $functional, 'functional_amount_minor' => $functionalAmount,
                'fx_rate_snapshot_id' => $snapshotId, 'rate_decimal' => $this->text($rate['rate_decimal'] ?? null),
                'rate_date' => $this->text($rate['rate_date'] ?? null), 'rate_type' => $this->text($rate['rate_type'] ?? null),
                'source_reference' => $this->text($rate['source_reference'] ?? null),
            ];
            $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $existing = $this->db->table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoiceId)->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                return $this->existing($existing, $checksum);
            }
            try {
                $id = (int) $this->db->table('customer_invoice_fx_recognitions')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
            } catch (QueryException) {
                $winner = $this->db->table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoiceId)->first();
                if (! $winner instanceof stdClass) {
                    throw ValidationException::withMessages(['recognition' => 'AR_FX_RECOGNITION_CONCURRENT_CREATE_FAILED']);
                }

                return $this->existing($winner, $checksum);
            }

            return new FxRecognitionResult($id, $transaction, $amount, $functional, $functionalAmount, $snapshotId, $payload['rate_decimal'], $date, $payload['rate_type'], $payload['source_reference'], $identity);
        }, 3);
    }

    private function existing(stdClass $row, string $checksum): FxRecognitionResult
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['recognition' => 'AR_FX_RECOGNITION_IMMUTABLE']);
        }

        return new FxRecognitionResult($this->nonNegativeInt($row->id ?? null), $this->text($row->transaction_currency ?? null), $this->nonNegativeInt($row->transaction_amount_minor ?? null), $this->text($row->functional_currency ?? null), $this->nonNegativeInt($row->functional_amount_minor ?? null), $this->nullableInt($row->fx_rate_snapshot_id ?? null), $this->text($row->rate_decimal ?? null), $this->text($row->rate_date ?? null), $this->text($row->rate_type ?? null), $this->text($row->source_reference ?? null), ($row->rate_type ?? null) === 'identity');
    }

    private function nonNegativeInt(mixed $value): int
    {
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0) {
            return (int) $value;
        }
        throw new \RuntimeException('Invalid AR FX integer.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : $this->nonNegativeInt($value);
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AR FX text.');
    }
}
