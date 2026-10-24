<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;

final readonly class SupplierCreditService
{
    public function __construct(
        private ConnectionInterface $db,
        private SourceAccountingPostingService $accounting,
        private AccountingPostingPolicyResolver $policies,
        private GoodsReturnService $goodsReturns,
        private ProcurementExceptionCaseService $exceptions,
        private SupplierCreditFxEvidenceService $fxEvidence,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return $this->createTransaction($companyId, $actor, $data);
    }

    /** @param array<string,mixed> $data */
    private function createTransaction(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data['idempotency_key'] ?? null, 'idempotency_key');
            $existing = $this->db->table('supplier_credit_notes')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->first();
            if (is_object($existing)) {
                $line = $this->db->table('supplier_credit_note_lines')->where('supplier_credit_note_id', data_get($existing, 'id'))->first();
                $expected = [
                    'credit_number' => $this->text($data['credit_number'] ?? null, 'credit_number'),
                    'credit_date' => $this->text($data['credit_date'] ?? null, 'credit_date'),
                    'reason' => $this->text($data['reason'] ?? null, 'reason'),
                ];
                foreach ($expected as $field => $value) {
                    if ($this->value($existing, $field) !== $value) {
                        throw ValidationException::withMessages(['idempotency_key' => 'SUPPLIER_CREDIT_IDEMPOTENCY_CONFLICT']);
                    }
                }
                if (! is_object($line)
                    || $this->int($line, 'goods_return_line_id') !== $this->positive($data['goods_return_line_id'] ?? null, 'goods_return_line_id')
                    || $this->int($line, 'vendor_bill_receipt_allocation_id') !== $this->positive($data['vendor_bill_receipt_allocation_id'] ?? null, 'vendor_bill_receipt_allocation_id')
                    || (isset($data['quantity']) && $this->int($line, 'quantity') !== $this->positive($data['quantity'], 'quantity'))) {
                    throw ValidationException::withMessages(['idempotency_key' => 'SUPPLIER_CREDIT_IDEMPOTENCY_CONFLICT']);
                }

                return $this->int($existing, 'id');
            }
            $returnLineId = $this->positive($data['goods_return_line_id'] ?? null, 'goods_return_line_id');
            $allocationId = $this->positive($data['vendor_bill_receipt_allocation_id'] ?? null, 'vendor_bill_receipt_allocation_id');
            $evidence = $this->evidence($companyId, $returnLineId, $allocationId, true);
            if ($this->value($evidence, 'return_status') !== 'approved') {
                throw ValidationException::withMessages(['goods_return' => 'SUPPLIER_CREDIT_REQUIRES_APPROVED_GOODS_RETURN']);
            }
            $quantity = isset($data['quantity']) ? $this->positive($data['quantity'], 'quantity') : min($this->int($evidence, 'returned_quantity'), $this->int($evidence, 'allocated_quantity'));
            if ($quantity !== $this->int($evidence, 'returned_quantity')) {
                throw ValidationException::withMessages(['quantity' => 'SUPPLIER_CREDIT_MUST_COVER_GOODS_RETURN']);
            }
            $alreadyCreditedQuantity = $this->creditedQuantity($returnLineId, $allocationId, null);
            $safeQuantity = min($this->int($evidence, 'returned_quantity'), $this->int($evidence, 'allocated_quantity')) - $alreadyCreditedQuantity;
            if ($quantity > $safeQuantity) {
                throw ValidationException::withMessages(['quantity' => 'SUPPLIER_CREDIT_EXCEEDS_ALLOCATED_RETURN']);
            }
            $value = $quantity * $this->int($evidence, 'unit_cost_minor');
            $outstanding = $this->outstanding($this->int($evidence, 'vendor_bill_id'), null, now()->toDateTimeString());
            $apReduction = min($value, max(0, $outstanding));
            $vendorReceivable = $value - $apReduction;
            $id = (int) $this->db->table('supplier_credit_notes')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $this->int($evidence, 'branch_id'),
                'vendor_id' => $this->int($evidence, 'vendor_id'),
                'vendor_bill_id' => $this->int($evidence, 'vendor_bill_id'),
                'goods_return_id' => $this->int($evidence, 'goods_return_id'),
                'purchase_order_id' => $this->int($evidence, 'purchase_order_id'),
                'credit_number' => $this->text($data['credit_number'] ?? null, 'credit_number'),
                'credit_date' => $this->text($data['credit_date'] ?? null, 'credit_date'),
                'currency' => $this->value($evidence, 'currency'),
                'reason' => $this->text($data['reason'] ?? null, 'reason'),
                'status' => 'draft',
                'total_minor' => $value,
                'ap_reduction_minor' => $apReduction,
                'vendor_receivable_minor' => $vendorReceivable,
                'idempotency_key' => $key,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->db->table('supplier_credit_note_lines')->insert([
                'supplier_credit_note_id' => $id,
                'goods_return_line_id' => $returnLineId,
                'vendor_bill_line_id' => $this->int($evidence, 'vendor_bill_line_id'),
                'vendor_bill_receipt_allocation_id' => $allocationId,
                'goods_receipt_line_id' => $this->int($evidence, 'goods_receipt_line_id'),
                'purchase_order_line_id' => $this->int($evidence, 'purchase_order_line_id'),
                'quantity' => $quantity,
                'unit_cost_minor' => $this->int($evidence, 'unit_cost_minor'),
                'credit_minor' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $creditId, User $actor): int
    {
        return $this->transition($companyId, $creditId, $actor, 'draft', 'submitted', ['submitted_by' => $actor->id, 'submitted_at' => now()]);
    }

    public function approve(int $companyId, int $creditId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $creditId, $actor): int {
            $credit = $this->credit($companyId, $creditId, true);
            if ($this->value($credit, 'status') === 'approved') {
                return $creditId;
            }
            if ($this->value($credit, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_CREDIT_APPROVAL_STATE_INVALID']);
            }
            if ($this->int($credit, 'created_by') === (int) $actor->id) {
                throw ValidationException::withMessages(['actor' => 'SUPPLIER_CREDIT_MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('supplier_credit_notes')->where('id', $creditId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $creditId;
        }, 3);
    }

    public function post(int $companyId, int $creditId, User $actor): int
    {
        return $this->postTransaction($companyId, $creditId, $actor);
    }

    private function postTransaction(int $companyId, int $creditId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $creditId, $actor): int {
            $credit = $this->credit($companyId, $creditId, true);
            if ($this->value($credit, 'status') === 'posted') {
                return $creditId;
            }
            if ($this->value($credit, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_CREDIT_POSTING_STATE_INVALID']);
            }
            $lines = $this->db->table('supplier_credit_note_lines')->where('supplier_credit_note_id', $creditId)->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'SUPPLIER_CREDIT_LINES_REQUIRED']);
            }
            foreach ($lines as $line) {
                $evidence = $this->evidence($companyId, $this->int($line, 'goods_return_line_id'), $this->int($line, 'vendor_bill_receipt_allocation_id'), true);
                $otherQuantity = $this->creditedQuantity($this->int($line, 'goods_return_line_id'), $this->int($line, 'vendor_bill_receipt_allocation_id'), $creditId);
                if ($this->int($line, 'quantity') + $otherQuantity > min($this->int($evidence, 'returned_quantity'), $this->int($evidence, 'allocated_quantity'))) {
                    throw ValidationException::withMessages(['quantity' => 'SUPPLIER_CREDIT_EXCEEDS_ALLOCATED_RETURN']);
                }
                if ($this->int($line, 'quantity') !== $this->int($evidence, 'returned_quantity')) {
                    throw ValidationException::withMessages(['quantity' => 'SUPPLIER_CREDIT_MUST_COVER_GOODS_RETURN']);
                }
            }
            $amount = $this->int($credit, 'total_minor');
            $functionalCurrency = $this->functionalCurrency($companyId);
            $foreign = strtoupper($this->value($credit, 'currency')) !== $functionalCurrency;
            $preparedFx = null;
            if ($foreign) {
                $this->goodsReturns->postForSupplierCredit($companyId, $this->int($credit, 'goods_return_id'), $creditId, $actor);
                $preparedFx = $this->fxEvidence->prepare($companyId, $credit);
            }
            $outstanding = $this->outstanding($this->int($credit, 'vendor_bill_id'), $creditId, $this->value($credit, 'credit_date').' 23:59:59');
            $apReduction = $preparedFx === null ? min($amount, max(0, $outstanding)) : $preparedFx['ap_carrying_release_minor'];
            $vendorReceivable = $preparedFx === null ? $amount - $apReduction : $preparedFx['supplier_receivable_functional_minor'];
            $apReductionForeign = $preparedFx === null ? $apReduction : $preparedFx['ap_foreign_minor'];
            $vendorReceivableForeign = $preparedFx === null ? $vendorReceivable : $preparedFx['supplier_receivable_foreign_minor'];
            $ppv = $preparedFx['purchase_price_variance_minor'] ?? 0;
            $apPolicy = $ppv === 0 && $apReduction > 0
                ? $this->apReductionPolicy($companyId, $this->value($credit, 'credit_date'), $functionalCurrency)
                : null;
            $paidPolicy = $ppv === 0 && $vendorReceivable > 0
                ? $this->paidPortionPolicy($companyId, $this->value($credit, 'credit_date'), $functionalCurrency)
                : null;
            $mixedPolicy = $ppv === 0 && $apReduction > 0 && $vendorReceivable > 0
                ? $this->mixedAdjustmentPolicy($companyId, $this->value($credit, 'credit_date'), $functionalCurrency)
                : null;
            $foreignPolicy = $preparedFx !== null && $ppv !== 0
                ? $this->foreignPpvPolicy($companyId, $this->value($credit, 'credit_date'), $functionalCurrency, $apReduction, $vendorReceivable, $ppv)
                : null;
            if ($foreignPolicy !== null) {
                $apPolicy = null;
                $paidPolicy = null;
                $mixedPolicy = null;
            }
            if (! $foreign) {
                $this->goodsReturns->postForSupplierCredit($companyId, $this->int($credit, 'goods_return_id'), $creditId, $actor);
            }
            $accountingLines = [];
            if ($foreignPolicy !== null) {
                if ($apReduction > 0) {
                    $accountingLines[] = ['account_code' => $this->policyAccount($foreignPolicy, 'AP', 'debit'), 'debit' => $apReduction, 'credit' => 0, 'description' => 'Supplier credit releases AP functional carrying basis'];
                }
                if ($vendorReceivable > 0) {
                    $accountingLines[] = ['account_code' => $this->policyAccount($foreignPolicy, 'RECEIVABLE', 'debit'), 'debit' => $vendorReceivable, 'credit' => 0, 'description' => 'Supplier refund receivable functional carrying basis'];
                }
                if ($ppv > 0) {
                    $accountingLines[] = ['account_code' => $this->policyAccount($foreignPolicy, 'PURCHASE_PRICE_VARIANCE_LOSS', 'debit'), 'debit' => $ppv, 'credit' => 0, 'description' => 'Purchase price variance on foreign supplier credit'];
                }
                $accountingLines[] = ['account_code' => $this->policyAccount($foreignPolicy, 'GRNI', 'credit'), 'debit' => 0, 'credit' => $preparedFx['inventory_return_basis_minor'], 'description' => 'Supplier credit clears returned goods GRNI'];
                if ($ppv < 0) {
                    $accountingLines[] = ['account_code' => $this->policyAccount($foreignPolicy, 'PURCHASE_PRICE_VARIANCE_GAIN', 'credit'), 'debit' => 0, 'credit' => abs($ppv), 'description' => 'Purchase price variance on foreign supplier credit'];
                }
            } elseif ($mixedPolicy !== null) {
                $accountingLines = [
                    ['account_code' => $this->policyAccount($mixedPolicy, 'AP', 'debit'), 'debit' => $apReduction, 'credit' => 0, 'description' => 'Supplier credit reduces Accounts Payable'],
                    ['account_code' => $this->policyAccount($mixedPolicy, 'RECEIVABLE', 'debit'), 'debit' => $vendorReceivable, 'credit' => 0, 'description' => 'Vendor refund receivable'],
                    ['account_code' => $this->policyAccount($mixedPolicy, 'GRNI', 'credit'), 'debit' => 0, 'credit' => $preparedFx['inventory_return_basis_minor'] ?? $amount, 'description' => 'Supplier credit clears returned goods GRNI'],
                ];
            }
            if ($foreignPolicy === null && $mixedPolicy === null && $apReduction > 0) {
                if ($apPolicy === null) {
                    throw new \LogicException('AP reduction policy preflight was not resolved.');
                }
                $accountingLines[] = ['account_code' => $this->policyAccount($apPolicy, 'AP', 'debit'), 'debit' => $apReduction, 'credit' => 0, 'description' => 'Supplier credit reduces Accounts Payable'];
                $accountingLines[] = ['account_code' => $this->policyAccount($apPolicy, 'GRNI', 'credit'), 'debit' => 0, 'credit' => $preparedFx['inventory_return_basis_minor'] ?? $apReduction, 'description' => 'Supplier credit clears returned goods GRNI'];
            }
            if ($foreignPolicy === null && $mixedPolicy === null && $vendorReceivable > 0) {
                if ($paidPolicy === null) {
                    throw new \LogicException('Paid-portion policy preflight was not resolved.');
                }
                $accountingLines[] = ['account_code' => $this->policyAccount($paidPolicy, 'RECEIVABLE', 'debit'), 'debit' => $vendorReceivable, 'credit' => 0, 'description' => 'Vendor refund receivable'];
                $accountingLines[] = ['account_code' => $this->policyAccount($paidPolicy, 'GRNI', 'credit'), 'debit' => 0, 'credit' => $preparedFx['inventory_return_basis_minor'] ?? $vendorReceivable, 'description' => 'Supplier credit clears returned goods GRNI'];
            }
            $policy = $foreignPolicy ?? $mixedPolicy ?? $apPolicy ?? $paidPolicy ?? throw new \LogicException('Supplier Credit policy preflight was not resolved.');
            $journalId = $this->accounting->postPolicyBacked($companyId, $this->int($credit, 'branch_id'), 'supplier_credit', $creditId, 'supplier_credit_note', $this->value($credit, 'credit_date'), $functionalCurrency, $accountingLines, (int) $actor->id, $policy['policy_id'], $policy['version']);
            if ($preparedFx !== null) {
                $this->fxEvidence->record($companyId, $credit, $preparedFx, $policy['policy_id'], $policy['version'], $journalId, (int) $actor->id);
            }
            if ($vendorReceivableForeign > 0) {
                $this->db->table('vendor_refund_receivables')->insertOrIgnore([
                    'company_id' => $companyId, 'branch_id' => $this->int($credit, 'branch_id'), 'vendor_id' => $this->int($credit, 'vendor_id'),
                    'supplier_credit_note_id' => $creditId, 'vendor_bill_id' => $this->int($credit, 'vendor_bill_id'), 'goods_return_id' => $this->int($credit, 'goods_return_id'),
                    'currency' => $this->value($credit, 'currency'), 'original_minor' => $vendorReceivableForeign, 'refunded_minor' => 0, 'status' => 'open',
                    'effective_date' => $this->value($credit, 'credit_date'), 'accounting_journal_id' => $journalId, 'idempotency_key' => 'supplier-credit:'.$creditId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->db->table('supplier_credit_notes')->where('id', $creditId)->update(['status' => 'posted', 'ap_reduction_minor' => $apReductionForeign, 'vendor_receivable_minor' => $vendorReceivableForeign, 'posted_by' => $actor->id, 'posted_at' => now(), 'accounting_journal_id' => $journalId, 'updated_at' => now()]);
            $this->exceptions->resolveForReturn($companyId, $this->int($credit, 'goods_return_id'), 'supplier-credit:'.$creditId);

            return $creditId;
        }, 3);
    }

    /** @param array{lines:list<array{line_role:string,entry_side:string,account_code:string}>} $policy */
    private function policyAccount(array $policy, string $role, string $side): string
    {
        foreach ($policy['lines'] as $line) {
            if ($line['line_role'] === $role) {
                if ($line['entry_side'] !== $side) {
                    throw ValidationException::withMessages(['accounting_policy' => 'SUPPLIER_CREDIT_PAID_PORTION_POLICY_INVALID']);
                }

                return $line['account_code'];
            }
        }
        throw ValidationException::withMessages(['accounting_policy' => 'SUPPLIER_CREDIT_PAID_PORTION_POLICY_INVALID']);
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function paidPortionPolicy(int $companyId, string $date, string $currency): array
    {
        try {
            return $this->policies->resolve($companyId, 'SUPPLIER_CREDIT_PAID_PORTION', 'vendor_refund', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'SUPPLIER_CREDIT_PAID_PORTION_POLICY_MISSING'
                : 'SUPPLIER_CREDIT_PAID_PORTION_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function apReductionPolicy(int $companyId, string $date, string $currency): array
    {
        try {
            return $this->policies->resolve($companyId, 'SUPPLIER_CREDIT_AP_ADJUSTMENT', 'supplier_credit', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'SUPPLIER_CREDIT_AP_POLICY_MISSING'
                : 'SUPPLIER_CREDIT_AP_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function mixedAdjustmentPolicy(int $companyId, string $date, string $currency): array
    {
        try {
            return $this->policies->resolve($companyId, 'SUPPLIER_CREDIT_MIXED_ADJUSTMENT', 'supplier_credit', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'SUPPLIER_CREDIT_MIXED_POLICY_MISSING'
                : 'SUPPLIER_CREDIT_MIXED_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    /** @return array{policy_id:int,version:int,lines:list<array{line_role:string,entry_side:string,account_code:string}>} */
    private function foreignPpvPolicy(int $companyId, string $date, string $currency, int $apReduction, int $vendorReceivable, int $ppv): array
    {
        $exposure = $apReduction > 0 && $vendorReceivable > 0
            ? 'MIXED'
            : ($apReduction > 0 ? 'AP' : 'RECEIVABLE');
        $event = 'FOREIGN_SUPPLIER_CREDIT_'.$exposure.'_PPV_'.($ppv > 0 ? 'LOSS' : 'GAIN');
        try {
            return $this->policies->resolve($companyId, $event, 'supplier_credit', $date, $currency);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $code = $message === 'ACCOUNTING_POSTING_POLICY_MISSING'
                ? 'FOREIGN_SUPPLIER_CREDIT_PPV_POLICY_REQUIRED'
                : 'FOREIGN_SUPPLIER_CREDIT_PPV_POLICY_INVALID';
            throw ValidationException::withMessages(['accounting_policy' => $code]);
        }
    }

    private function functionalCurrency(int $companyId): string
    {
        $currency = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code');

        return is_string($currency) && preg_match('/^[A-Z]{3}$/', strtoupper($currency)) === 1
            ? strtoupper($currency)
            : throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
    }

    private function evidence(int $companyId, int $returnLineId, int $allocationId, bool $lock): object
    {
        $query = $this->db->table('procurement_goods_return_lines as return_line')
            ->join('procurement_goods_returns as goods_return', 'goods_return.id', '=', 'return_line.goods_return_id')
            ->join('vendor_bill_receipt_allocations as allocation', 'allocation.goods_receipt_line_id', '=', 'return_line.goods_receipt_line_id')
            ->join('vendor_bill_lines as bill_line', 'bill_line.id', '=', 'allocation.vendor_bill_line_id')
            ->join('vendor_bills as bill', 'bill.id', '=', 'bill_line.vendor_bill_id')
            ->where([
                'return_line.id' => $returnLineId,
                'allocation.id' => $allocationId,
                'goods_return.company_id' => $companyId,
                'bill.company_id' => $companyId,
            ])->whereColumn('goods_return.vendor_id', 'bill.vendor_id')
            ->whereColumn('goods_return.purchase_order_id', 'bill.purchase_order_id')
            ->whereColumn('return_line.purchase_order_line_id', 'bill_line.purchase_order_line_id')
            ->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected', 'reversed'])
            ->selectRaw('goods_return.id goods_return_id, goods_return.status return_status, goods_return.branch_id, goods_return.vendor_id, goods_return.purchase_order_id, goods_return.currency, return_line.goods_receipt_line_id, return_line.purchase_order_line_id, return_line.quantity returned_quantity, return_line.unit_cost_minor, allocation.quantity allocated_quantity, allocation.allocated_minor, bill.id vendor_bill_id, bill_line.id vendor_bill_line_id');

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function creditedQuantity(int $returnLineId, int $allocationId, ?int $exceptCreditId): int
    {
        return (int) $this->db->table('supplier_credit_note_lines as line')->join('supplier_credit_notes as credit', 'credit.id', '=', 'line.supplier_credit_note_id')
            ->where(['line.goods_return_line_id' => $returnLineId, 'line.vendor_bill_receipt_allocation_id' => $allocationId])
            ->whereNotIn('credit.status', ['cancelled', 'rejected'])
            ->when($exceptCreditId, fn ($query, int $id) => $query->where('credit.id', '<>', $id))->lockForUpdate()->sum('line.quantity');
    }

    private function outstanding(int $billId, ?int $exceptCreditId, string $asOf): int
    {
        $bill = $this->db->table('vendor_bills')->where('id', $billId)->lockForUpdate()->firstOrFail(['total_minor']);
        $paid = $this->db->table('ap_payment_allocations as allocation')->leftJoin('ap_payment_allocation_reversals as reversal', function (JoinClause $join) use ($asOf): void {
            $join->on('reversal.ap_payment_allocation_id', '=', 'allocation.id')->where('reversal.effective_at', '<=', $asOf);
        })->where('allocation.vendor_bill_id', $billId)->sum($this->db->raw('allocation.allocated_minor-COALESCE(reversal.reversed_minor,0)'));
        $credited = $this->db->table('supplier_credit_notes')->where(['vendor_bill_id' => $billId, 'status' => 'posted'])->where('credit_date', '<=', substr($asOf, 0, 10))
            ->when($exceptCreditId, fn ($query, int $id) => $query->where('id', '<>', $id))->sum('ap_reduction_minor');

        return $this->int($bill, 'total_minor') - (int) $paid - (int) $credited;
    }

    /** @param array<string,mixed> $evidence */
    private function transition(int $companyId, int $creditId, User $actor, string $from, string $to, array $evidence): int
    {
        return $this->db->transaction(function () use ($companyId, $creditId, $actor, $from, $to, $evidence): int {
            $credit = $this->credit($companyId, $creditId, true);
            if ($this->value($credit, 'status') === $to) {
                return $creditId;
            }
            if ($this->value($credit, 'status') !== $from || ($from === 'draft' && $this->int($credit, 'created_by') !== (int) $actor->id)) {
                throw ValidationException::withMessages(['status' => 'SUPPLIER_CREDIT_TRANSITION_INVALID']);
            }
            $this->db->table('supplier_credit_notes')->where('id', $creditId)->update([...$evidence, 'status' => $to, 'updated_at' => now()]);

            return $creditId;
        }, 3);
    }

    private function credit(int $companyId, int $creditId, bool $lock): object
    {
        $query = $this->db->table('supplier_credit_notes')->where(['id' => $creditId, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function positive(mixed $value, string $field): int
    {
        $number = is_numeric($value) ? (int) $value : 0;
        if ($number < 1) {
            throw ValidationException::withMessages([$field => 'Giá trị phải lớn hơn 0.']);
        }

        return $number;
    }

    private function int(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid Supplier Credit integer: '.$field);
    }

    private function value(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid Supplier Credit text: '.$field);
    }

    private function text(mixed $value, string $field): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$field => 'Giá trị bắt buộc.']);
    }
}
