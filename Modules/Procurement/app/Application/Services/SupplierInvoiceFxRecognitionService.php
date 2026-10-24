<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\DTOs\FxRecognitionResult;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateResolver;
use Modules\Finance\Application\Services\FxRateSnapshotService;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class SupplierInvoiceFxRecognitionService
{
    private const SOURCE_TYPE = 'supplier_invoice_recognition';

    public function __construct(
        private ConnectionInterface $db,
        private FxRateResolver $rates,
        private FxMoneyConverter $converter,
        private FxRateSnapshotService $snapshots,
    ) {}

    public function prepare(int $companyId, stdClass $bill): FxRecognitionResult
    {
        $company = $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->first(['functional_currency_code']);
        $functional = $company instanceof stdClass && is_string($company->functional_currency_code)
            ? strtoupper($company->functional_currency_code)
            : '';
        if ($functional === '') {
            throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
        }

        $transaction = strtoupper($this->text($bill->currency ?? null));
        $amount = $this->nonNegativeInt($bill->total_minor ?? null);
        $date = $this->text($bill->invoice_date ?? null);
        $billId = $this->nonNegativeInt($bill->id ?? null);
        $identity = $transaction === $functional;
        $rate = $identity
            ? ['id' => null, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'source_reference' => 'SAME_CURRENCY_IDENTITY']
            : $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
        $functionalAmount = $identity
            ? $amount
            : $this->converter->convertMinor($amount, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
        $snapshotId = $identity ? null : $this->snapshots->capture($companyId, $rate, self::SOURCE_TYPE, $billId);

        return new FxRecognitionResult(
            0,
            $transaction,
            $amount,
            $functional,
            $functionalAmount,
            $snapshotId,
            $this->text($rate['rate_decimal'] ?? null),
            $date,
            $this->text($rate['rate_type'] ?? null),
            $this->text($rate['source_reference'] ?? null),
            $identity,
        );
    }

    public function record(int $companyId, stdClass $bill, FxRecognitionResult $recognition, int $policyId, int $policyVersion, int $journalId, int $actorId): int
    {
        $billId = $this->nonNegativeInt($bill->id ?? null);
        $payload = [
            'company_id' => $companyId,
            'branch_id' => $this->nullableInt($bill->branch_id ?? null),
            'vendor_bill_id' => $billId,
            'transaction_currency' => $recognition->transactionCurrency,
            'transaction_amount_minor' => $recognition->transactionAmountMinor,
            'functional_currency' => $recognition->functionalCurrency,
            'functional_amount_minor' => $recognition->functionalAmountMinor,
            'fx_rate_snapshot_id' => $recognition->snapshotId,
            'rate_decimal' => $recognition->rateDecimal,
            'rate_date' => $recognition->rateDate,
            'rate_type' => $recognition->rateType,
            'source_reference' => $recognition->sourceReference,
            'accounting_policy_id' => $policyId,
            'accounting_policy_version' => $policyVersion,
            'accounting_journal_id' => $journalId,
        ];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $billId)->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            return $this->existing($existing, $checksum);
        }
        try {
            return (int) $this->db->table('supplier_invoice_fx_recognitions')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        } catch (QueryException) {
            $winner = $this->db->table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $billId)->first();
            if (! $winner instanceof stdClass) {
                throw ValidationException::withMessages(['recognition' => 'AP_FX_RECOGNITION_CONCURRENT_CREATE_FAILED']);
            }

            return $this->existing($winner, $checksum);
        }
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['recognition' => 'AP_FX_RECOGNITION_IMMUTABLE']);
        }

        return $this->nonNegativeInt($row->id ?? null);
    }

    private function nonNegativeInt(mixed $value): int
    {
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0) {
            return (int) $value;
        }
        throw new \RuntimeException('Invalid AP FX integer.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : $this->nonNegativeInt($value);
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AP FX text.');
    }
}
