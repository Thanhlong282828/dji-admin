<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class InventoryReturnFxReversalService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{valuation_id:int,functional_currency:string,released_minor:int,cumulative_quantity:int,cumulative_released:int} */
    public function prepare(int $companyId, int $receiptLineId, int $returnQuantity): array
    {
        $valuation = $this->db->table('inventory_receipt_fx_valuations')->where([
            'company_id' => $companyId,
            'goods_receipt_line_id' => $receiptLineId,
        ])->lockForUpdate()->first();
        if (! $valuation instanceof stdClass) {
            throw ValidationException::withMessages(['valuation' => 'FOREIGN_GOODS_RETURN_ORIGINAL_FX_VALUATION_REQUIRED']);
        }
        $receiptQuantity = $this->integer($this->db->table('procurement_goods_receipt_lines')->where('id', $receiptLineId)->lockForUpdate()->value('received_quantity'));
        $returned = $this->db->table('inventory_return_fx_reversals')->where('inventory_receipt_fx_valuation_id', $valuation->id)->lockForUpdate()
            ->selectRaw('COALESCE(SUM(return_quantity),0) quantity, COALESCE(SUM(released_functional_basis_minor),0) released')->first();
        $priorQuantity = $this->integer($returned->quantity ?? 0);
        $priorReleased = $this->integer($returned->released ?? 0);
        if ($returnQuantity < 1 || $priorQuantity + $returnQuantity > $receiptQuantity) {
            throw ValidationException::withMessages(['quantity' => 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE']);
        }
        $total = $this->integer($valuation->functional_amount_minor ?? null);
        $released = $priorQuantity + $returnQuantity === $receiptQuantity
            ? $total - $priorReleased
            : (int) bcadd(bcdiv(bcmul((string) $total, (string) $returnQuantity, 0), (string) $receiptQuantity, 18), '0.5', 0);

        return [
            'valuation_id' => $this->positive($valuation->id ?? null),
            'functional_currency' => $this->text($valuation->functional_currency ?? null),
            'released_minor' => $released,
            'cumulative_quantity' => $priorQuantity + $returnQuantity,
            'cumulative_released' => $priorReleased + $released,
        ];
    }

    /** @param array{valuation_id:int,functional_currency:string,released_minor:int,cumulative_quantity:int,cumulative_released:int} $basis */
    public function record(int $companyId, int $branchId, int $returnId, int $returnLineId, int $movementId, int $policyId, int $policyVersion, int $journalId, int $actorId, int $quantity, string $transactionCurrency, array $basis): void
    {
        $payload = [
            'company_id' => $companyId, 'branch_id' => $branchId, 'goods_return_id' => $returnId,
            'goods_return_line_id' => $returnLineId, 'inventory_receipt_fx_valuation_id' => $basis['valuation_id'],
            'inventory_movement_id' => $movementId, 'return_quantity' => $quantity,
            'cumulative_return_quantity' => $basis['cumulative_quantity'], 'transaction_currency' => $transactionCurrency,
            'functional_currency' => $basis['functional_currency'], 'released_functional_basis_minor' => $basis['released_minor'],
            'cumulative_released_basis_minor' => $basis['cumulative_released'], 'accounting_policy_id' => $policyId,
            'accounting_policy_version' => $policyVersion, 'accounting_journal_id' => $journalId, 'version' => 1,
        ];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('inventory_return_fx_reversals')->where('goods_return_line_id', $returnLineId)->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            if ($existing->checksum !== $checksum) {
                throw ValidationException::withMessages(['valuation' => 'INVENTORY_RETURN_FX_EVIDENCE_IMMUTABLE']);
            }

            return;
        }
        $this->db->table('inventory_return_fx_reversals')->insert($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
    }

    private function positive(mixed $value): int
    {
        $number = $this->integer($value);

        return $number > 0 ? $number : throw new \RuntimeException('Invalid positive return FX evidence.');
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0
            ? (int) $value
            : throw new \RuntimeException('Invalid return FX integer evidence.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid return FX text evidence.');
    }
}
