<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\ArFxSettlementCalculator;
use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRateResolver;
use Modules\Finance\Application\Services\FxRateSnapshotService;
use Modules\Finance\Domain\Enums\FxRateType;
use stdClass;

final readonly class VendorRefundFxSettlementService
{
    private const SOURCE_TYPE = 'supplier_refund_receipt_settlement';

    public function __construct(private ConnectionInterface $db, private FxRateResolver $rates, private FxMoneyConverter $converter, private FxRateSnapshotService $snapshots, private ArFxSettlementCalculator $calculator) {}

    /** @return array{supplier_credit_fx_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_refunded_minor:int,functional_cash_minor:int,released_carrying_minor:int,realized_fx_minor:int,rate_id:int,rate_version:int,rate_decimal:string,rate_date:string,rate_type:string,source_reference:string,snapshot_id:null} */
    public function prepare(int $companyId, object $receipt, object $receivable): array
    {
        $credit = $this->db->table('supplier_credit_fx_evidence')->where([
            'company_id' => $companyId,
            'supplier_credit_note_id' => $this->integer($receivable->supplier_credit_note_id ?? null),
        ])->lockForUpdate()->first();
        if (! $credit instanceof stdClass || $this->integer($credit->supplier_receivable_functional_minor ?? 0) <= 0) {
            throw ValidationException::withMessages(['fx_evidence' => 'FOREIGN_VENDOR_REFUND_CARRYING_BASIS_REQUIRED']);
        }
        $transaction = $this->currency($receipt->currency ?? null);
        $functional = $this->currency($credit->functional_currency ?? null);
        if ($transaction !== $this->currency($credit->transaction_currency ?? null)) {
            throw ValidationException::withMessages(['fx_evidence' => 'FOREIGN_VENDOR_REFUND_CURRENCY_MISMATCH']);
        }
        $date = $this->text($receipt->refund_date ?? null);
        $rate = $this->rates->resolve($companyId, $transaction, $functional, $date, FxRateType::Spot->value);
        $foreign = $this->integer($receipt->amount_minor ?? null);
        $cash = $this->converter->convertMinor($foreign, $transaction, $functional, $this->text($rate['rate_decimal'] ?? null));
        $previous = $this->db->table('supplier_refund_fx_settlements')->where('vendor_refund_receivable_id', $this->integer(data_get($receivable, 'id')))
            ->selectRaw('COALESCE(SUM(foreign_refunded_minor),0) foreign_minor, COALESCE(SUM(released_carrying_minor),0) carrying_minor')->first();
        $calculated = $this->calculator->calculate(
            $this->integer($receivable->original_minor ?? null),
            $this->integer($credit->supplier_receivable_functional_minor ?? null),
            $this->integer($previous->foreign_minor ?? 0),
            $this->integer($previous->carrying_minor ?? 0),
            $foreign,
            $cash,
        );

        return [
            'supplier_credit_fx_evidence_id' => $this->integer($credit->id ?? null),
            'transaction_currency' => $transaction,
            'functional_currency' => $functional,
            'foreign_refunded_minor' => $foreign,
            'functional_cash_minor' => $cash,
            'released_carrying_minor' => $calculated['released_carrying_minor'],
            'realized_fx_minor' => $calculated['realized_fx_minor'],
            'rate_id' => $this->integer($rate['id'] ?? null),
            'rate_version' => $this->integer($rate['version'] ?? null),
            'rate_decimal' => $this->text($rate['rate_decimal'] ?? null),
            'rate_date' => $this->text($rate['rate_date'] ?? null),
            'rate_type' => $this->text($rate['rate_type'] ?? null),
            'source_reference' => $this->text($rate['source_reference'] ?? null),
            'snapshot_id' => null,
        ];
    }

    /** @param array{supplier_credit_fx_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_refunded_minor:int,functional_cash_minor:int,released_carrying_minor:int,realized_fx_minor:int,rate_id:int,rate_version:int,rate_decimal:string,rate_date:string,rate_type:string,source_reference:string,snapshot_id:null} $prepared
     * @return array{supplier_credit_fx_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_refunded_minor:int,functional_cash_minor:int,released_carrying_minor:int,realized_fx_minor:int,rate_id:int,rate_version:int,rate_decimal:string,rate_date:string,rate_type:string,source_reference:string,snapshot_id:int}
     */
    public function captureSnapshot(int $companyId, int $receiptId, array $prepared): array
    {
        $prepared['snapshot_id'] = $this->snapshots->capture($companyId, [
            'id' => $prepared['rate_id'], 'version' => $prepared['rate_version'],
            'base_currency' => $prepared['transaction_currency'], 'quote_currency' => $prepared['functional_currency'],
            'rate_decimal' => $prepared['rate_decimal'], 'rate_date' => $prepared['rate_date'],
            'rate_type' => $prepared['rate_type'], 'source_reference' => $prepared['source_reference'],
        ], self::SOURCE_TYPE, $receiptId);

        return $prepared;
    }

    /** @param array{supplier_credit_fx_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_refunded_minor:int,functional_cash_minor:int,released_carrying_minor:int,realized_fx_minor:int,rate_id:int,rate_version:int,rate_decimal:string,rate_date:string,rate_type:string,source_reference:string,snapshot_id:int} $prepared */
    public function record(int $companyId, int $receiptId, int $receivableId, int $policyId, int $policyVersion, int $journalId, int $treasuryId, int $actorId, array $prepared): int
    {
        $payload = [
            'company_id' => $companyId, 'supplier_refund_receipt_id' => $receiptId,
            'vendor_refund_receivable_id' => $receivableId, 'supplier_credit_fx_evidence_id' => $prepared['supplier_credit_fx_evidence_id'],
            'settlement_fx_snapshot_id' => $prepared['snapshot_id'], 'transaction_currency' => $prepared['transaction_currency'],
            'functional_currency' => $prepared['functional_currency'], 'foreign_refunded_minor' => $prepared['foreign_refunded_minor'],
            'functional_cash_minor' => $prepared['functional_cash_minor'], 'released_carrying_minor' => $prepared['released_carrying_minor'],
            'realized_fx_minor' => $prepared['realized_fx_minor'], 'rate_decimal' => $prepared['rate_decimal'],
            'rate_date' => $prepared['rate_date'], 'rate_type' => $prepared['rate_type'], 'source_reference' => $prepared['source_reference'],
            'accounting_policy_id' => $policyId, 'accounting_policy_version' => $policyVersion,
            'accounting_journal_id' => $journalId, 'treasury_transaction_id' => $treasuryId,
        ];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $receiptId)->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            return $this->existing($existing, $checksum);
        }
        try {
            return (int) $this->db->table('supplier_refund_fx_settlements')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        } catch (QueryException) {
            $winner = $this->db->table('supplier_refund_fx_settlements')->where('supplier_refund_receipt_id', $receiptId)->first();
            if (! $winner instanceof stdClass) {
                throw ValidationException::withMessages(['fx_evidence' => 'VENDOR_REFUND_FX_CONCURRENT_CREATE_FAILED']);
            }

            return $this->existing($winner, $checksum);
        }
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['fx_evidence' => 'VENDOR_REFUND_FX_EVIDENCE_IMMUTABLE']);
        }

        return $this->integer($row->id ?? null);
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 ? (int) $value : throw new \RuntimeException('Invalid Vendor Refund FX integer.');
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper($this->text($value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : throw new \RuntimeException('Invalid Vendor Refund FX currency.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid Vendor Refund FX text.');
    }
}
