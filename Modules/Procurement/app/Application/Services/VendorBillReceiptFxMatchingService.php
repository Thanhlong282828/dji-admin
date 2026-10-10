<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\DTOs\FxRecognitionResult;
use Modules\Finance\Application\Services\FxMoneyConverter;
use stdClass;

final readonly class VendorBillReceiptFxMatchingService
{
    public function __construct(private ConnectionInterface $db, private FxMoneyConverter $converter) {}

    /** @return array{released_grni_minor:int,ap_functional_minor:int,variance_minor:int,rows:list<array<string,int>>} */
    public function prepare(int $companyId, int $billId, FxRecognitionResult $recognition): array
    {
        $allocations = $this->db->table('vendor_bill_receipt_allocations as allocation')
            ->join('vendor_bill_lines as bill_line', 'bill_line.id', '=', 'allocation.vendor_bill_line_id')
            ->join('inventory_receipt_fx_valuations as valuation', 'valuation.goods_receipt_line_id', '=', 'allocation.goods_receipt_line_id')
            ->where(['bill_line.vendor_bill_id' => $billId, 'valuation.company_id' => $companyId])
            ->orderBy('allocation.id')->lockForUpdate()
            ->get(['allocation.id', 'allocation.quantity', 'allocation.allocated_minor', 'valuation.id as valuation_id', 'valuation.goods_receipt_line_id', 'valuation.foreign_amount_minor as receipt_foreign_minor', 'valuation.functional_amount_minor as receipt_functional_minor']);
        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages(['receipt_fx' => 'FOREIGN_INVENTORY_RECEIPT_FX_EVIDENCE_REQUIRED']);
        }

        $rows = [];
        $releasedTotal = 0;
        $apTotal = 0;
        foreach ($allocations as $index => $allocation) {
            $valuationId = $this->positive($allocation->valuation_id ?? null);
            $quantity = $this->positive($allocation->quantity ?? null);
            $foreign = $this->integer($allocation->allocated_minor ?? null);
            $receiptQuantity = $this->positive($this->db->table('procurement_goods_receipt_lines')->where('id', $this->positive($allocation->goods_receipt_line_id ?? null))->value('received_quantity'));
            $already = $this->db->table('vendor_bill_receipt_fx_matches')->where('inventory_receipt_fx_valuation_id', $valuationId)->lockForUpdate()->selectRaw('COALESCE(SUM(quantity),0) quantity, COALESCE(SUM(released_grni_functional_minor),0) released')->first();
            $alreadyQuantity = $this->integer($already->quantity ?? null);
            $alreadyReleased = $this->integer($already->released ?? null);
            if ($alreadyQuantity + $quantity > $receiptQuantity) {
                throw ValidationException::withMessages(['receipt_fx' => 'FOREIGN_INVENTORY_RECEIPT_FX_MATCH_EXCEEDS_RECEIPT']);
            }
            $receiptFunctional = $this->integer($allocation->receipt_functional_minor ?? null);
            $released = $alreadyQuantity + $quantity === $receiptQuantity
                ? $receiptFunctional - $alreadyReleased
                : $this->roundRatio($receiptFunctional, $quantity, $receiptQuantity);
            $ap = $index === $allocations->count() - 1
                ? $recognition->functionalAmountMinor - $apTotal
                : $this->converter->convertMinor($foreign, $recognition->transactionCurrency, $recognition->functionalCurrency, $recognition->rateDecimal);
            $rows[] = ['allocation_id' => $this->positive($allocation->id ?? null), 'valuation_id' => $valuationId, 'quantity' => $quantity, 'foreign_minor' => $foreign, 'released_minor' => $released, 'ap_minor' => $ap];
            $releasedTotal += $released;
            $apTotal += $ap;
        }
        if ($this->integer($allocations->sum('allocated_minor')) !== $recognition->transactionAmountMinor || $apTotal !== $recognition->functionalAmountMinor) {
            throw ValidationException::withMessages(['receipt_fx' => 'FOREIGN_INVENTORY_RECEIPT_FX_MATCH_INCOMPLETE']);
        }

        return ['released_grni_minor' => $releasedTotal, 'ap_functional_minor' => $apTotal, 'variance_minor' => $apTotal - $releasedTotal, 'rows' => $rows];
    }

    /** @param array{rows:list<array<string,int>>} $matching */
    public function record(int $companyId, int $billId, int $recognitionId, int $journalId, int $actorId, array $matching): void
    {
        foreach ($matching['rows'] as $row) {
            $payload = ['company_id' => $companyId, 'vendor_bill_id' => $billId, 'vendor_bill_receipt_allocation_id' => $row['allocation_id'], 'inventory_receipt_fx_valuation_id' => $row['valuation_id'], 'supplier_invoice_fx_recognition_id' => $recognitionId, 'quantity' => $row['quantity'], 'foreign_amount_minor' => $row['foreign_minor'], 'released_grni_functional_minor' => $row['released_minor'], 'ap_functional_minor' => $row['ap_minor'], 'purchase_price_variance_minor' => $row['ap_minor'] - $row['released_minor'], 'accounting_journal_id' => $journalId];
            $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $existing = $this->db->table('vendor_bill_receipt_fx_matches')->where('vendor_bill_receipt_allocation_id', $row['allocation_id'])->lockForUpdate()->first();
            if ($existing instanceof stdClass) {
                if (($existing->checksum ?? null) !== $checksum) {
                    throw ValidationException::withMessages(['receipt_fx' => 'FOREIGN_INVENTORY_RECEIPT_FX_MATCH_IMMUTABLE']);
                }

                continue;
            }
            $this->db->table('vendor_bill_receipt_fx_matches')->insert($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        }
    }

    private function roundRatio(int $amount, int $part, int $whole): int
    {
        $proportion = bcdiv(bcmul((string) $amount, (string) $part, 0), (string) $whole, 18);

        return (int) bcadd($proportion, '0.5', 0);
    }

    private function positive(mixed $value): int
    {
        $value = $this->integer($value);

        return $value > 0 ? $value : throw new \RuntimeException('Invalid positive receipt matching evidence.');
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 ? (int) $value : throw new \RuntimeException('Invalid receipt matching integer.');
    }
}
