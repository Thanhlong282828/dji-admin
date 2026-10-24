<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\ApFxSettlementCalculator;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateResolver;
use Modules\Finance\Application\Services\FxRateSnapshotService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class SupplierPaymentFxSettlementService
{
    private const SOURCE_TYPE = 'supplier_payment_allocation_settlement';

    public function __construct(private ConnectionInterface $db, private FxRateResolver $rates, private FxMoneyConverter $converter, private FxRateSnapshotService $snapshots, private ApFxSettlementCalculator $calculator, private TreasuryService $treasury) {}

    /** @return array<string,int|string|null> */
    public function prepare(int $companyId, int $billId, int $amount, ?int $financialAccountId, string $date, int $actorId): array
    {
        $source = $this->db->table('vendor_bills as bill')->join('supplier_invoice_fx_recognitions as recognition', 'recognition.vendor_bill_id', '=', 'bill.id')->where(['bill.id' => $billId, 'bill.company_id' => $companyId, 'recognition.company_id' => $companyId])->lockForUpdate()->first(['bill.branch_id', 'bill.currency', 'recognition.*']);
        if (! $source instanceof stdClass) {
            throw ValidationException::withMessages(['settlement' => 'AP_FX_SETTLEMENT_SOURCE_INVALID']);
        }
        $transaction = $this->text($source->transaction_currency ?? null);
        $functional = $this->text($source->functional_currency ?? null);
        if ($this->text($source->currency ?? null) !== $transaction) {
            throw ValidationException::withMessages(['settlement' => 'AP_FX_SETTLEMENT_CURRENCY_MISMATCH']);
        }
        if ($financialAccountId === null && $transaction !== $functional) {
            throw ValidationException::withMessages(['financial_account_id' => 'AP_PAYMENT_TREASURY_ACCOUNT_REQUIRED']);
        }
        $account = $financialAccountId === null
            ? $this->treasury->canonicalPaymentAccountEvidence($companyId, $this->nullableInt($source->branch_id ?? null), $actorId, $date)
            : $this->treasury->accountEvidence($companyId, $financialAccountId, $this->nullableInt($source->branch_id ?? null), $date);
        $financialAccountId = $account['id'];
        if (strtoupper($account['currency']) !== $transaction) {
            throw ValidationException::withMessages(['financial_account_id' => 'AP_PAYMENT_TREASURY_CURRENCY_MISMATCH']);
        }
        $identity = $transaction === $functional;
        $rate = $identity ? ['id' => null, 'rate_decimal' => '1.000000000000', 'rate_date' => $date, 'rate_type' => 'identity', 'source_reference' => 'SAME_CURRENCY_IDENTITY'] : $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
        $settlement = $identity ? $amount : $this->converter->convertMinor($amount, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
        $previous = $this->db->table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $this->integer($source->id ?? null))->selectRaw('COALESCE(SUM(foreign_settled_minor),0) foreign_minor, COALESCE(SUM(released_carrying_minor),0) released_minor')->first();
        $calculated = $this->calculator->calculate($this->integer($source->transaction_amount_minor ?? null), $this->integer($source->functional_amount_minor ?? null), $this->integer($previous->foreign_minor ?? 0), $this->integer($previous->released_minor ?? 0), $amount, $settlement);

        return ['recognition_id' => $this->integer($source->id ?? null), 'branch_id' => $this->nullableInt($source->branch_id ?? null), 'transaction_currency' => $transaction, 'functional_currency' => $functional, 'foreign_settled_minor' => $amount, 'settlement_functional_minor' => $settlement, ...$calculated, 'snapshot_id' => null, 'rate_id' => $this->nullableInt($rate['id'] ?? null), 'rate_version' => $this->integer($rate['version'] ?? 1), 'rate_decimal' => $this->text($rate['rate_decimal'] ?? null), 'rate_date' => $this->text($rate['rate_date'] ?? null), 'rate_type' => $this->text($rate['rate_type'] ?? null), 'source_reference' => $this->text($rate['source_reference'] ?? null), 'treasury_account_id' => $financialAccountId];
    }

    /** @param array<string,int|string|null> $prepared
     * @return array<string,int|string|null>
     */
    public function captureSnapshot(int $companyId, int $allocationId, array $prepared): array
    {
        if ($prepared['rate_type'] === 'identity') {
            return $prepared;
        }
        $prepared['snapshot_id'] = $this->snapshots->capture($companyId, [
            'id' => $prepared['rate_id'],
            'version' => $prepared['rate_version'],
            'base_currency' => $prepared['transaction_currency'],
            'quote_currency' => $prepared['functional_currency'],
            'rate_decimal' => $prepared['rate_decimal'],
            'rate_date' => $prepared['rate_date'],
            'rate_type' => $prepared['rate_type'],
            'source_reference' => $prepared['source_reference'],
        ], self::SOURCE_TYPE, $allocationId);

        return $prepared;
    }

    /** @param array<string,int|string|null> $prepared */
    public function record(int $companyId, int $paymentId, int $allocationId, int $journalId, int $treasuryId, int $policyId, int $policyVersion, int $actorId, array $prepared): int
    {
        $payload = ['company_id' => $companyId, 'ap_payment_id' => $paymentId, 'ap_payment_allocation_id' => $allocationId, 'supplier_invoice_fx_recognition_id' => $prepared['recognition_id'], 'settlement_fx_snapshot_id' => $prepared['snapshot_id'], 'treasury_financial_account_id' => $prepared['treasury_account_id'], 'transaction_currency' => $prepared['transaction_currency'], 'functional_currency' => $prepared['functional_currency'], 'foreign_settled_minor' => $prepared['foreign_settled_minor'], 'settlement_functional_minor' => $prepared['settlement_functional_minor'], 'released_carrying_minor' => $prepared['released_carrying_minor'], 'realized_fx_minor' => $prepared['realized_fx_minor'], 'rate_decimal' => $prepared['rate_decimal'], 'rate_date' => $prepared['rate_date'], 'rate_type' => $prepared['rate_type'], 'source_reference' => $prepared['source_reference'], 'accounting_policy_id' => $policyId, 'accounting_policy_version' => $policyVersion, 'accounting_journal_id' => $journalId, 'treasury_transaction_id' => $treasuryId];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('supplier_payment_fx_settlements')->where('ap_payment_allocation_id', $allocationId)->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            return $this->existing($existing, $checksum);
        }
        try {
            return (int) $this->db->table('supplier_payment_fx_settlements')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        } catch (QueryException) {
            $winner = $this->db->table('supplier_payment_fx_settlements')->where('ap_payment_allocation_id', $allocationId)->first();
            if (! $winner instanceof stdClass) {
                throw ValidationException::withMessages(['settlement' => 'AP_FX_SETTLEMENT_CONCURRENT_CREATE_FAILED']);
            }

            return $this->existing($winner, $checksum);
        }
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['settlement' => 'AP_FX_SETTLEMENT_IMMUTABLE']);
        }

        return $this->integer($row->id ?? null);
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 ? (int) $value : throw new \RuntimeException('Invalid AP FX settlement integer.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : $this->integer($value);
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AP FX settlement text.');
    }
}
