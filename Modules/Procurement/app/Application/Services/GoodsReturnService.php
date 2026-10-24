<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class GoodsReturnService
{
    public function __construct(private InventoryService $inventory, private AccountingPostingPolicyResolver $policies, private SourceAccountingPostingService $accounting, private ProcurementExceptionCaseService $exceptions, private InventoryReturnFxReversalService $fxReversals) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $receiptLineId = $this->positive($data['goods_receipt_line_id'] ?? null, 'goods_receipt_line_id');
            $line = $this->receiptLine($companyId, $receiptLineId, true);
            $functionalCurrency = DB::table('companies')->where('id', $companyId)->value('functional_currency_code');
            $transactionCurrency = strtoupper($this->textField($line, 'currency'));
            if (! is_string($functionalCurrency) || $functionalCurrency === '') {
                throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
            }
            if ($transactionCurrency !== strtoupper($functionalCurrency) && ! DB::table('inventory_receipt_fx_valuations')->where([
                'company_id' => $companyId,
                'goods_receipt_line_id' => $receiptLineId,
                'transaction_currency' => $transactionCurrency,
                'functional_currency' => strtoupper($functionalCurrency),
            ])->exists()) {
                throw ValidationException::withMessages(['valuation' => 'FOREIGN_GOODS_RETURN_ORIGINAL_FX_VALUATION_REQUIRED']);
            }
            $quantity = $this->positive($data['quantity'] ?? null, 'quantity');
            $returned = $this->postedQuantity($receiptLineId);
            if ($quantity > $this->integerField($line, 'received_quantity') - $returned) {
                throw ValidationException::withMessages(['quantity' => 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE']);
            }
            $key = $this->text($data['idempotency_key'] ?? null, 'idempotency_key');
            $existing = DB::table('procurement_goods_returns')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $unitCost = $this->integerField($line, 'unit_cost_minor');
            $orderStatus = $this->textField($line, 'order_status');
            $id = (int) DB::table('procurement_goods_returns')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->integerField($line, 'branch_id'), 'vendor_id' => $this->integerField($line, 'vendor_id'),
                'purchase_order_id' => $this->integerField($line, 'purchase_order_id'), 'goods_receipt_id' => $this->integerField($line, 'goods_receipt_id'),
                'return_number' => $this->text($data['return_number'] ?? null, 'return_number'), 'return_date' => $this->text($data['return_date'] ?? null, 'return_date'),
                'currency' => $this->textField($line, 'currency'), 'reason' => $this->text($data['reason'] ?? null, 'reason'), 'status' => 'draft',
                'restore_commitment' => ! in_array($orderStatus, ['closed', 'cancelled'], true), 'total_minor' => $quantity * $unitCost,
                'idempotency_key' => $key, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('procurement_goods_return_lines')->insert([
                'goods_return_id' => $id, 'goods_receipt_line_id' => $receiptLineId, 'purchase_order_line_id' => $this->integerField($line, 'purchase_order_line_id'),
                'product_variant_id' => $this->integerField($line, 'product_variant_id'), 'inventory_location_id' => $this->integerField($line, 'inventory_location_id'),
                'quantity' => $quantity, 'unit_cost_minor' => $unitCost, 'line_value_minor' => $quantity * $unitCost, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $returnId, User $actor): int
    {
        return $this->transition($companyId, $returnId, $actor, 'draft', 'submitted', ['submitted_by' => $actor->id, 'submitted_at' => now()]);
    }

    public function approve(int $companyId, int $returnId, User $actor): int
    {
        return DB::transaction(function () use ($companyId, $returnId, $actor): int {
            $return = $this->return($companyId, $returnId, true);
            if ($this->textField($return, 'status') === 'approved') {
                return $returnId;
            }
            if ($this->textField($return, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'GOODS_RETURN_APPROVAL_STATE_INVALID']);
            }
            if ($this->integerField($return, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['actor' => 'GOODS_RETURN_MAKER_CHECKER_REQUIRED']);
            }
            DB::table('procurement_goods_returns')->where('id', $returnId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $returnId;
        }, 3);
    }

    public function post(int $companyId, int $returnId, User $actor): int
    {
        try {
            return $this->postInternal($companyId, $returnId, $actor, null);
        } catch (ValidationException $exception) {
            $code = $this->validationCode($exception, 'billing') ?? $this->validationCode($exception, 'valuation');
            if (is_string($code) && in_array($code, ['GOODS_RETURN_TO_VENDOR_BLOCKED_BY_BILLING_EVIDENCE', 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_INVENTORY_VALUATION'], true)) {
                $return = $this->return($companyId, $returnId, false);
                $this->exceptions->record($companyId, $code === 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_BILLING_EVIDENCE' ? 'GOODS_RETURN_BILLING_EVIDENCE_INSUFFICIENT' : 'GOODS_RETURN_VALUATION_EVIDENCE_INSUFFICIENT', $code, [
                    'goods_return_id' => $returnId,
                    'vendor_id' => $this->integerField($return, 'vendor_id'),
                    'purchase_order_id' => $this->integerField($return, 'purchase_order_id'),
                    'goods_receipt_id' => $this->integerField($return, 'goods_receipt_id'),
                ]);
            }
            throw $exception;
        }
    }

    public function postForSupplierCredit(int $companyId, int $returnId, int $supplierCreditId, User $actor): int
    {
        return $this->postInternal($companyId, $returnId, $actor, $supplierCreditId);
    }

    private function postInternal(int $companyId, int $returnId, User $actor, ?int $supplierCreditId): int
    {
        return DB::transaction(function () use ($companyId, $returnId, $actor, $supplierCreditId): int {
            $return = $this->return($companyId, $returnId, true);
            if ($this->textField($return, 'status') === 'posted') {
                return $returnId;
            }
            if ($this->textField($return, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'GOODS_RETURN_POSTING_STATE_INVALID']);
            }
            $lines = DB::table('procurement_goods_return_lines')->where('goods_return_id', $returnId)->lockForUpdate()->get();
            $costCenter = ['id' => null, 'code' => null, 'name' => null];
            $functionalTotal = 0;
            $journalCurrency = $this->textField($return, 'currency');
            $fxEvidence = [];
            foreach ($lines as $line) {
                $receiptLine = $this->receiptLine($companyId, $this->integer($line->goods_receipt_line_id), true);
                $costCenter = [
                    'id' => is_numeric($receiptLine->cost_center_id ?? null) ? (int) $receiptLine->cost_center_id : null,
                    'code' => $this->nullableText($receiptLine->cost_center_code_snapshot ?? null),
                    'name' => $this->nullableText($receiptLine->cost_center_name_snapshot ?? null),
                ];
                $postedOther = DB::table('procurement_goods_return_lines as grl')->join('procurement_goods_returns as gr', 'gr.id', '=', 'grl.goods_return_id')
                    ->where('grl.goods_receipt_line_id', $this->integer($line->goods_receipt_line_id))->where('gr.status', 'posted')->where('gr.id', '<>', $returnId)->lockForUpdate()->sum('grl.quantity');
                if ($this->integer($line->quantity) + (int) $postedOther > $this->integerField($receiptLine, 'received_quantity')) {
                    throw ValidationException::withMessages(['quantity' => 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE']);
                }
                $this->assertBillingSafe($this->integer($line->purchase_order_line_id), $this->integer($line->id), $supplierCreditId);
                $transactionCurrency = strtoupper($this->textField($return, 'currency'));
                $functionalCurrency = strtoupper($this->text(DB::table('companies')->where('id', $companyId)->value('functional_currency_code'), 'functional_currency'));
                $foreign = $transactionCurrency !== $functionalCurrency;
                $basis = $foreign ? $this->fxReversals->prepare($companyId, $this->integer($line->goods_receipt_line_id), $this->integer($line->quantity)) : null;
                if (! $foreign) {
                    $this->assertValuation($receiptLine);
                }
                $releasedValue = $basis === null ? $this->integer($line->line_value_minor) : $basis['released_minor'];
                $journalCurrency = $basis === null ? $journalCurrency : $basis['functional_currency'];
                $functionalTotal += $releasedValue;
                $balance = DB::table('inventory_balances')->where(['product_variant_id' => $this->integer($line->product_variant_id), 'inventory_location_id' => $this->integer($line->inventory_location_id)])->lockForUpdate()->first(['on_hand', 'reserved', 'inventory_value_minor']);
                if ($balance === null || $this->integer($balance->on_hand) - $this->integer($balance->reserved) < $this->integer($line->quantity)
                    || $this->integer($balance->inventory_value_minor) < $releasedValue) {
                    throw ValidationException::withMessages(['valuation' => 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_INVENTORY_VALUATION']);
                }
                $this->inventory->adjust($this->integer($line->product_variant_id), $this->integer($line->inventory_location_id), 'decrease', $this->integer($line->quantity), [
                    'source_type' => 'procurement_goods_return_line', 'source_id' => $this->integer($line->id), 'reference' => $this->textField($return, 'return_number'),
                    'idempotency_key' => 'procurement-goods-return-'.$this->integer($line->id), 'actor_id' => $actor->id, 'reason' => 'Return goods to vendor',
                    'unit_cost_minor' => intdiv($releasedValue + intdiv($this->integer($line->quantity), 2), $this->integer($line->quantity)),
                    'movement_value_minor' => -$releasedValue,
                ]);
                $movementId = DB::table('inventory_movements')->where('idempotency_key', 'procurement-goods-return-'.$this->integer($line->id))->value('id');
                if ($basis !== null) {
                    $fxEvidence[] = ['line_id' => $this->integer($line->id), 'movement_id' => $this->integer($movementId), 'quantity' => $this->integer($line->quantity), 'transaction_currency' => $transactionCurrency, 'basis' => $basis];
                }
                DB::table('procurement_goods_return_lines')->where('id', $this->integer($line->id))->update(['inventory_movement_id' => is_numeric($movementId) ? (int) $movementId : null, 'updated_at' => now()]);
                $corrected = DB::table('purchase_order_lines')->where('id', $this->integer($line->purchase_order_line_id))->where('received_quantity', '>=', $this->integer($line->quantity))->decrement('received_quantity', $this->integer($line->quantity), ['updated_at' => now()]);
                if ($corrected !== 1) {
                    throw ValidationException::withMessages(['quantity' => 'GOODS_RETURN_QUANTITY_EXCEEDS_RETURNABLE']);
                }
            }
            $date = $this->textField($return, 'return_date');
            $currency = $journalCurrency;
            $policy = $this->policies->resolve($companyId, 'GOODS_RETURN_TO_VENDOR', null, $date, $currency);
            $value = $functionalTotal;
            $accountingLines = [
                ['account_code' => $this->policyAccount($policy['lines'], 'GRNI', 'debit'), 'debit' => $value, 'credit' => 0, 'description' => 'Reverse goods received not invoiced'],
                [
                    'account_code' => $this->policyAccount($policy['lines'], 'INVENTORY_ASSET', 'credit'), 'debit' => 0, 'credit' => $value,
                    'description' => 'Inventory returned to vendor', 'cost_center_id' => $costCenter['id'],
                    'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'],
                ],
            ];
            $journalId = $this->accounting->postPolicyBacked($companyId, $this->integerField($return, 'branch_id'), 'goods_return', $returnId, 'goods_return_to_vendor', $date, $currency, $accountingLines, (int) $actor->id, $policy['policy_id'], $policy['version']);
            foreach ($fxEvidence as $evidence) {
                $this->fxReversals->record($companyId, $this->integerField($return, 'branch_id'), $returnId, $evidence['line_id'], $evidence['movement_id'], $policy['policy_id'], $policy['version'], $journalId, (int) $actor->id, $evidence['quantity'], $evidence['transaction_currency'], $evidence['basis']);
            }
            if ($this->booleanField($return, 'restore_commitment')) {
                DB::table('purchase_orders')->where('id', $this->integerField($return, 'purchase_order_id'))->whereNotIn('status', ['closed', 'cancelled'])->update(['status' => 'partially_received', 'updated_at' => now()]);
            }
            DB::table('procurement_goods_returns')->where('id', $returnId)->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now(), 'accounting_journal_id' => $journalId, 'updated_at' => now()]);

            return $returnId;
        }, 3);
    }

    /** @param array<string,mixed> $evidence */
    private function transition(int $companyId, int $returnId, User $actor, string $from, string $to, array $evidence): int
    {
        return DB::transaction(function () use ($companyId, $returnId, $actor, $from, $to, $evidence): int {
            $return = $this->return($companyId, $returnId, true);
            if ($this->textField($return, 'status') === $to) {
                return $returnId;
            }
            if ($this->textField($return, 'status') !== $from || ($from === 'draft' && $this->integerField($return, 'created_by') !== (int) $actor->id)) {
                throw ValidationException::withMessages(['status' => 'GOODS_RETURN_TRANSITION_INVALID']);
            }
            DB::table('procurement_goods_returns')->where('id', $returnId)->update([...$evidence, 'status' => $to, 'updated_at' => now()]);

            return $returnId;
        }, 3);
    }

    private function assertBillingSafe(int $orderLineId, int $returnLineId, ?int $supplierCreditId): void
    {
        $billed = DB::table('vendor_bill_lines as line')->join('vendor_bills as bill', 'bill.id', '=', 'line.vendor_bill_id')
            ->where('line.purchase_order_line_id', $orderLineId)->whereNotIn('bill.status', ['cancelled', 'rejected', 'reversed'])->exists();
        if (! $billed) {
            return;
        }
        $safeCredit = $supplierCreditId !== null && DB::table('supplier_credit_note_lines as credit_line')
            ->join('supplier_credit_notes as credit', 'credit.id', '=', 'credit_line.supplier_credit_note_id')
            ->join('vendor_bill_receipt_allocations as allocation', 'allocation.id', '=', 'credit_line.vendor_bill_receipt_allocation_id')
            ->where([
                'credit.id' => $supplierCreditId,
                'credit_line.goods_return_line_id' => $returnLineId,
                'credit.status' => 'approved',
            ])->whereColumn('allocation.goods_receipt_line_id', 'credit_line.goods_receipt_line_id')->exists();
        if (! $safeCredit) {
            throw ValidationException::withMessages(['billing' => 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_BILLING_EVIDENCE']);
        }
    }

    private function assertValuation(object $line): void
    {
        $movement = DB::table('inventory_movements')->where('id', $this->integerField($line, 'inventory_movement_id'))->first();
        if ($movement === null || $this->nullableText($movement->source_type ?? null) !== 'procurement_goods_receipt_line'
            || $this->integer($movement->source_id ?? null) !== $this->integerField($line, 'id')
            || $this->integer($movement->unit_cost_minor ?? null) !== $this->integerField($line, 'unit_cost_minor')
            || $this->integer($movement->movement_value_minor ?? null) !== $this->integerField($line, 'line_value_minor')) {
            throw ValidationException::withMessages(['valuation' => 'GOODS_RETURN_TO_VENDOR_BLOCKED_BY_INVENTORY_VALUATION']);
        }
    }

    /** @param list<array{entry_side:string,line_role:string,account_code:string}> $lines */
    private function policyAccount(array $lines, string $role, string $side): string
    {
        foreach ($lines as $line) {
            if ($line['line_role'] === $role && $line['entry_side'] === $side) {
                return $line['account_code'];
            }
        }

        throw ValidationException::withMessages(['accounting_policy' => 'GOODS_RETURN_ACCOUNTING_POLICY_INVALID']);
    }

    private function postedQuantity(int $receiptLineId): int
    {
        return (int) DB::table('procurement_goods_return_lines as line')->join('procurement_goods_returns as header', 'header.id', '=', 'line.goods_return_id')
            ->where('line.goods_receipt_line_id', $receiptLineId)->where('header.status', 'posted')->sum('line.quantity');
    }

    private function receiptLine(int $companyId, int $lineId, bool $lock): object
    {
        $query = DB::table('procurement_goods_receipt_lines as line')->join('procurement_goods_receipts as receipt', 'receipt.id', '=', 'line.goods_receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'receipt.purchase_order_id')->join('purchase_order_lines as pol', 'pol.id', '=', 'line.purchase_order_line_id')
            ->where(['line.id' => $lineId, 'receipt.company_id' => $companyId, 'receipt.status' => 'received'])
            ->select(['line.*', 'receipt.id as goods_receipt_id', 'receipt.branch_id', 'receipt.inventory_location_id', 'po.id as purchase_order_id', 'po.vendor_id', 'po.currency', 'po.status as order_status', 'pol.cost_center_id', 'pol.cost_center_code_snapshot', 'pol.cost_center_name_snapshot']);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function return(int $companyId, int $id, bool $lock): object
    {
        $query = DB::table('procurement_goods_returns')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function positive(mixed $value, string $field): int
    {
        $number = $this->integer($value);
        if ($number < 1) {
            throw ValidationException::withMessages([$field => 'Giá trị phải lớn hơn 0.']);
        }

        return $number;
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : throw new \RuntimeException('Invalid goods return integer.'));
    }

    private function integerField(object $row, string $field): int
    {
        return $this->integer(data_get($row, $field));
    }

    private function textField(object $row, string $field): string
    {
        return $this->text(data_get($row, $field), $field);
    }

    private function booleanField(object $row, string $field): bool
    {
        $value = data_get($row, $field);

        return $value === true || $value === 1 || $value === '1';
    }

    private function validationCode(ValidationException $exception, string $field): ?string
    {
        $value = data_get($exception->errors(), $field.'.0');

        return is_string($value) ? $value : null;
    }

    private function text(mixed $value, string $field): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$field => 'Giá trị bắt buộc.']);
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
