<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Enums\SupportedCurrency;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Inventory\Domain\Services\InventoryService;
use Modules\Tax\Application\Services\TaxEvidenceService;
use Modules\Tax\Application\Services\TaxPolicyResolver;

final readonly class ProcurementService
{
    public function __construct(private InventoryService $inventory, private SourceAccountingPostingService $accounting, private TreasuryService $treasury, private AccountingPostingPolicyResolver $postingPolicies, private TaxPolicyResolver $taxPolicies, private TaxEvidenceService $taxEvidence, private SupplierInvoiceFxRecognitionService $fxRecognition, private SupplierPaymentFxSettlementService $fxSettlement, private InventoryReceiptFxValuationService $receiptFxValuation, private VendorBillReceiptFxMatchingService $receiptFxMatching) {}

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data): int
    {
        return match ($action) {
            'vendor.create' => $this->vendor($companyId, $actor, $data),
            'request.create' => $this->request($companyId, $actor, $data),
            'request.transition' => $this->requestTransition($companyId, $actor, $data),
            'order.create' => $this->order($companyId, $actor, $data),
            'order.transition' => $this->orderTransition($companyId, $actor, $data),
            'receipt.create' => $this->receipt($companyId, $actor, $data),
            'receipt.receive' => $this->receive($companyId, $actor, $data),
            'bill.create' => $this->bill($companyId, $actor, $data),
            'bill.approve' => $this->approveBill($companyId, $actor, $data),
            'payment.allocate' => $this->payment($companyId, $actor, $data),
            default => throw ValidationException::withMessages(['action' => 'Procurement action không được hỗ trợ.']),
        };
    }

    /** @param array<string,mixed> $data */
    private function vendor(int $companyId, User $actor, array $data): int
    {
        return (int) DB::table('procurement_vendors')->insertGetId(['company_id' => $companyId, 'code' => $this->text($data, 'code'), 'name' => $this->text($data, 'name'), 'tax_code' => $this->nullable($data, 'tax_code'), 'email' => $this->nullable($data, 'email'), 'phone' => $this->nullable($data, 'phone'), 'address' => $this->nullable($data, 'address'), 'payment_terms_days' => $this->integer($data, 'payment_terms_days', 0), 'status' => 'active', 'note' => $this->nullable($data, 'note'), 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $data */
    private function request(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $branch = $this->branch($companyId, $this->integer($data, 'branch_id'));
            $costCenter = $this->costCenter($companyId, $this->nullableInteger($data, 'cost_center_id'));
            $quantity = $this->positive($data, 'quantity');
            $unitCost = $this->integer($data, 'estimated_unit_cost_minor', 0);
            $classification = $this->classification($data);
            $account = $this->account($companyId, $actor->id, $this->nullableInteger($data, 'account_id'), $classification);
            $sourceKey = $this->nullable($data, 'source_idempotency_key');
            if ($sourceKey !== null) {
                $existing = DB::table('purchase_requests')->where(['company_id' => $companyId, 'source_idempotency_key' => $sourceKey])->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
            }
            $id = (int) DB::table('purchase_requests')->insertGetId(['company_id' => $companyId, 'source_type' => $this->nullable($data, 'source_type'), 'source_id' => $this->nullableInteger($data, 'source_id'), 'source_reference' => $this->nullable($data, 'source_reference'), 'source_idempotency_key' => $sourceKey, 'branch_id' => $branch, 'requester_id' => $actor->id, 'cost_center_id' => $costCenter['id'], 'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'], 'reference' => $this->text($data, 'reference'), 'requested_date' => $this->date($data, 'requested_date'), 'needed_date' => $this->nullable($data, 'needed_date'), 'reason' => $this->text($data, 'reason'), 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_request_lines')->insert(['purchase_request_id' => $id, 'product_variant_id' => $this->nullableInteger($data, 'product_variant_id'), 'description' => $this->text($data, 'description'), 'procurement_classification' => $classification, 'account_id' => $account['id'], 'requested_quantity' => $quantity, 'estimated_unit_cost_minor' => $unitCost, 'estimated_total_minor' => $quantity * $unitCost, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    private function requestTransition(int $companyId, User $actor, array $data): int
    {
        $id = $this->positive($data, 'id');
        $to = $this->text($data, 'to');

        return DB::transaction(function () use ($companyId, $actor, $id, $to, $data): int {
            $row = DB::table('purchase_requests')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $from = $this->property($row, 'status');
            $allowed = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected', 'cancelled']];
            if (! in_array($to, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Purchase Request transition không hợp lệ.']);
            }
            if ($to === 'approved' && $this->id($row, 'requester_id') === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'Requester không được tự approve Purchase Request.']);
            }
            $reason = $this->nullable($data, 'decision_reason');
            if ($to === 'rejected' && $reason === null) {
                throw ValidationException::withMessages(['decision_reason' => 'Lý do reject là bắt buộc.']);
            }
            DB::table('purchase_requests')->where('id', $id)->update(['status' => $to, 'version' => $this->id($row, 'version') + 1, 'reviewed_by' => in_array($to, ['approved', 'rejected'], true) ? $actor->id : null, 'reviewed_at' => in_array($to, ['approved', 'rejected'], true) ? now() : null, 'decision_reason' => $reason, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function order(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $requestId = $this->positive($data, 'purchase_request_id');
            $request = DB::table('purchase_requests')->where(['id' => $requestId, 'company_id' => $companyId, 'status' => 'approved'])->lockForUpdate()->firstOrFail();
            $vendorId = $this->positive($data, 'vendor_id');
            DB::table('procurement_vendors')->where(['id' => $vendorId, 'company_id' => $companyId, 'status' => 'active'])->firstOrFail();
            $lines = DB::table('purchase_request_lines')->where('purchase_request_id', $requestId)->get();
            $costCenter = ['id' => $this->nullableId($request, 'cost_center_id'), 'code' => $this->nullableProperty($request, 'cost_center_code_snapshot'), 'name' => $this->nullableProperty($request, 'cost_center_name_snapshot')];
            $subtotal = 0;
            foreach ($lines as $line) {
                $subtotal += $this->id($line, 'estimated_total_minor');
            }
            $currency = SupportedCurrency::from(strtoupper($this->nullable($data, 'currency') ?? 'VND'))->value;
            $id = (int) DB::table('purchase_orders')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->id($request, 'branch_id'), 'purchase_request_id' => $requestId, 'cost_center_id' => $costCenter['id'], 'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'], 'vendor_id' => $vendorId, 'reference' => $this->text($data, 'reference'), 'currency' => $currency, 'order_date' => $this->date($data, 'order_date'), 'expected_date' => $this->nullable($data, 'expected_date'), 'payment_terms_days' => $this->integer($data, 'payment_terms_days', 0), 'status' => 'draft', 'subtotal_minor' => $subtotal, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) {
                $account = DB::table('accounting_accounts')->where(['id' => $this->id($line, 'account_id'), 'company_id' => $companyId])->firstOrFail(['id', 'code', 'name']);
                DB::table('purchase_order_lines')->insert(['purchase_order_id' => $id, 'purchase_request_line_id' => $this->id($line, 'id'), 'cost_center_id' => $costCenter['id'], 'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'], 'procurement_classification' => $this->property($line, 'procurement_classification'), 'account_id' => $this->id($account, 'id'), 'account_code_snapshot' => $this->property($account, 'code'), 'account_name_snapshot' => $this->property($account, 'name'), 'product_variant_id' => $this->nullableId($line, 'product_variant_id'), 'description' => $this->property($line, 'description'), 'ordered_quantity' => $this->id($line, 'requested_quantity'), 'received_quantity' => 0, 'unit_cost_minor' => $this->id($line, 'estimated_unit_cost_minor'), 'line_total_minor' => $this->id($line, 'estimated_total_minor'), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function orderTransition(int $companyId, User $actor, array $data): int
    {
        $id = $this->positive($data, 'id');
        $to = $this->text($data, 'to');

        return DB::transaction(function () use ($companyId, $actor, $id, $to): int {
            $row = DB::table('purchase_orders')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $from = $this->property($row, 'status');
            $allowed = ['draft' => ['approved', 'cancelled'], 'approved' => ['sent', 'cancelled'], 'sent' => ['cancelled'], 'received' => ['closed']];
            if (! in_array($to, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Purchase Order transition không hợp lệ.']);
            }
            DB::table('purchase_orders')->where('id', $id)->update(['status' => $to, 'approved_by' => $to === 'approved' ? $actor->id : $row->approved_by, 'approved_at' => $to === 'approved' ? now() : $row->approved_at, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function receipt(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $orderId = $this->positive($data, 'purchase_order_id');
            $order = DB::table('purchase_orders')->where('company_id', $companyId)->where('id', $orderId)->whereIn('status', ['approved', 'sent', 'partially_received'])->lockForUpdate()->firstOrFail();
            $locationId = $this->positive($data, 'inventory_location_id');
            DB::table('inventory_locations')->where(['id' => $locationId, 'branch_id' => $this->id($order, 'branch_id')])->firstOrFail();
            $orderLineId = $this->positive($data, 'purchase_order_line_id');
            $line = DB::table('purchase_order_lines')->where(['id' => $orderLineId, 'purchase_order_id' => $orderId])->lockForUpdate()->firstOrFail();
            if ($this->nullableProperty($line, 'procurement_classification') !== 'inventory') {
                throw ValidationException::withMessages(['purchase_order_line_id' => 'Goods Receipt hiện chỉ hỗ trợ Procurement line phân loại inventory.']);
            }
            $variant = $this->nullableId($line, 'product_variant_id');
            if ($variant === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'Goods Receipt chỉ nhận line có SKU tồn kho.']);
            }
            $quantity = $this->positive($data, 'quantity');
            if ($this->id($line, 'received_quantity') + $quantity > $this->id($line, 'ordered_quantity')) {
                throw ValidationException::withMessages(['quantity' => 'Số lượng nhận vượt Purchase Order.']);
            }
            $id = (int) DB::table('procurement_goods_receipts')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->id($order, 'branch_id'), 'purchase_order_id' => $orderId, 'inventory_location_id' => $locationId, 'reference' => $this->text($data, 'reference'), 'receipt_date' => $this->date($data, 'receipt_date'), 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $unit = $this->id($line, 'unit_cost_minor');
            DB::table('procurement_goods_receipt_lines')->insert(['goods_receipt_id' => $id, 'purchase_order_line_id' => $orderLineId, 'product_variant_id' => $variant, 'received_quantity' => $quantity, 'unit_cost_minor' => $unit, 'line_value_minor' => $quantity * $unit, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function receive(int $companyId, User $actor, array $data): int
    {
        $id = $this->positive($data, 'id');

        return DB::transaction(function () use ($companyId, $actor, $id): int {
            $receipt = DB::table('procurement_goods_receipts')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($this->property($receipt, 'status') === 'received') {
                return $id;
            }
            if ($this->property($receipt, 'status') !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Chỉ Goods Receipt draft được nhận.']);
            }
            $orderCurrency = DB::table('purchase_orders')->where('id', $this->id($receipt, 'purchase_order_id'))->value('currency');
            if (! is_string($orderCurrency)) {
                throw ValidationException::withMessages(['currency' => 'PURCHASE_ORDER_CURRENCY_INVALID']);
            }
            $orderCurrency = strtoupper($orderCurrency);
            $configuredFunctional = DB::table('companies')->where('id', $companyId)->value('functional_currency_code');
            $functionalCurrency = is_string($configuredFunctional) && $configuredFunctional !== ''
                ? strtoupper($configuredFunctional)
                : null;
            if ($functionalCurrency === null && $orderCurrency !== 'VND') {
                throw ValidationException::withMessages(['functional_currency' => 'FUNCTIONAL_CURRENCY_NOT_CONFIGURED']);
            }
            $useFxEvidence = $functionalCurrency !== null;
            $functionalCurrency ??= 'VND';
            $total = 0;
            $valuations = [];
            $dimension = ['cost_center_id' => null, 'cost_center_code_snapshot' => null, 'cost_center_name_snapshot' => null];
            foreach (DB::table('procurement_goods_receipt_lines as grl')->join('purchase_order_lines as pol', 'pol.id', '=', 'grl.purchase_order_line_id')->where('grl.goods_receipt_id', $id)->lockForUpdate()->get(['grl.*', 'pol.account_code_snapshot', 'pol.cost_center_id', 'pol.cost_center_code_snapshot', 'pol.cost_center_name_snapshot']) as $line) {
                $quantity = $this->id($line, 'received_quantity');
                $valuation = $useFxEvidence ? $this->receiptFxValuation->prepare($companyId, $receipt, $line, $orderCurrency) : null;
                $unit = $valuation === null ? $this->id($line, 'unit_cost_minor') : $this->arrayInteger($valuation, 'functional_unit_cost_minor');
                $lineValue = $valuation === null ? $quantity * $unit : $this->arrayInteger($valuation, 'functional_amount_minor');
                $this->inventory->adjust($this->id($line, 'product_variant_id'), $this->id($receipt, 'inventory_location_id'), 'increase', $quantity, ['source_type' => 'procurement_goods_receipt_line', 'source_id' => $this->id($line, 'id'), 'reference' => $this->property($receipt, 'reference'), 'idempotency_key' => 'procurement-gr-'.$this->id($line, 'id'), 'actor_id' => $actor->id, 'reason' => 'Purchase Order goods received', 'unit_cost_minor' => $unit, 'movement_value_minor' => $lineValue]);
                $movementId = DB::table('inventory_movements')->where('idempotency_key', 'procurement-gr-'.$this->id($line, 'id'))->value('id');
                DB::table('procurement_goods_receipt_lines')->where('id', $this->id($line, 'id'))->update(['inventory_movement_id' => is_numeric($movementId) ? (int) $movementId : null, 'updated_at' => now()]);
                DB::table('purchase_order_lines')->where('id', $this->id($line, 'purchase_order_line_id'))->increment('received_quantity', $quantity, ['updated_at' => now()]);
                $total += $lineValue;
                if ($valuation !== null && is_numeric($movementId)) {
                    $valuations[] = ['line' => $line, 'valuation' => $valuation, 'movement_id' => (int) $movementId];
                }
                $dimension = ['cost_center_id' => $this->nullableId($line, 'cost_center_id'), 'cost_center_code_snapshot' => $this->nullableProperty($line, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $this->nullableProperty($line, 'cost_center_name_snapshot')];
            }
            DB::table('procurement_goods_receipts')->where('id', $id)->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now(), 'updated_at' => now()]);
            $orderId = $this->id($receipt, 'purchase_order_id');
            $remaining = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
            DB::table('purchase_orders')->where('id', $orderId)->update(['status' => $remaining ? 'partially_received' : 'received', 'updated_at' => now()]);
            $date = $this->property($receipt, 'receipt_date');
            if ($valuations !== []) {
                $functionalCurrency = $this->arrayText($valuations[0]['valuation'], 'functional_currency');
            }
            $policy = $this->postingPolicies->resolve($companyId, 'INVENTORY_RECEIPT', 'inventory', $date, $functionalCurrency);
            $accountingLines = array_map(fn (array $policyLine): array => [
                'account_code' => $policyLine['account_code'],
                'debit' => $policyLine['entry_side'] === 'debit' ? $total : 0,
                'credit' => $policyLine['entry_side'] === 'credit' ? $total : 0,
                'description' => 'Goods receipt '.$policyLine['line_role'],
                'cost_center_id' => $policyLine['requires_cost_center'] ? $dimension['cost_center_id'] : null,
                'cost_center_code_snapshot' => $policyLine['requires_cost_center'] ? $dimension['cost_center_code_snapshot'] : null,
                'cost_center_name_snapshot' => $policyLine['requires_cost_center'] ? $dimension['cost_center_name_snapshot'] : null,
            ], $policy['lines']);
            $journalId = $this->accounting->postPolicyBacked($companyId, $this->id($receipt, 'branch_id'), 'goods_receipt', $id, 'inventory_accrual', $date, $functionalCurrency, $accountingLines, $actor->id, $policy['policy_id'], $policy['version']);
            foreach ($valuations as $item) {
                $this->receiptFxValuation->record($companyId, $receipt, $item['line'], $item['valuation'], $item['movement_id'], $policy['policy_id'], $policy['version'], $journalId, (int) $actor->id);
            }

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function bill(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $orderId = $this->positive($data, 'purchase_order_id');
            $order = DB::table('purchase_orders')->where(['id' => $orderId, 'company_id' => $companyId])->whereIn('status', ['approved', 'sent', 'partially_received', 'received', 'closed'])->lockForUpdate()->firstOrFail();
            $invoice = $this->text($data, 'invoice_number');
            $existing = DB::table('vendor_bills')->where(['company_id' => $companyId, 'vendor_id' => $this->id($order, 'vendor_id'), 'invoice_number' => $invoice])->first();
            if ($existing !== null) {
                if ($this->id($existing, 'purchase_order_id') !== $orderId) {
                    throw ValidationException::withMessages(['invoice_number' => 'Vendor invoice đã thuộc Purchase Order khác.']);
                }

                return $this->id($existing, 'id');
            }
            $requestedLine = $this->nullableInteger($data, 'purchase_order_line_id');
            $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)
                ->when($requestedLine, fn ($query) => $query->where('id', $requestedLine))->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['purchase_order_line_id' => 'PO line không hợp lệ.']);
            }
            $billedByLine = DB::table('vendor_bill_lines as vbl')->join('vendor_bills as vb', 'vb.id', '=', 'vbl.vendor_bill_id')
                ->whereIn('vbl.purchase_order_line_id', $lines->pluck('id')->all())->whereNotIn('vb.status', ['cancelled', 'rejected'])
                ->selectRaw('vbl.purchase_order_line_id, SUM(vbl.quantity) billed')->groupBy('vbl.purchase_order_line_id')->pluck('billed', 'purchase_order_line_id');
            $billLines = [];
            $subtotal = 0;
            foreach ($lines as $line) {
                $classification = $this->nullableProperty($line, 'procurement_classification');
                $serviceAcceptanceLineId = null;
                $goodsReceiptLine = null;
                if ($classification === 'inventory') {
                    $billed = $this->persistenceInteger($billedByLine->get($this->id($line, 'id')));
                    $billable = $this->id($line, 'received_quantity') - $billed;
                    $receiptLineId = $this->nullableInteger($data, 'goods_receipt_line_id');
                    if ($receiptLineId !== null) {
                        $goodsReceiptLine = DB::table('procurement_goods_receipt_lines as receipt_line')
                            ->join('procurement_goods_receipts as receipt', 'receipt.id', '=', 'receipt_line.goods_receipt_id')
                            ->where([
                                'receipt_line.id' => $receiptLineId,
                                'receipt_line.purchase_order_line_id' => $this->id($line, 'id'),
                                'receipt.company_id' => $companyId,
                                'receipt.purchase_order_id' => $orderId,
                                'receipt.status' => 'received',
                            ])->lockForUpdate()->first(['receipt_line.id', 'receipt_line.received_quantity', 'receipt_line.unit_cost_minor']);
                        if ($goodsReceiptLine === null) {
                            throw ValidationException::withMessages(['goods_receipt_line_id' => 'GOODS_BILL_RECEIPT_ALLOCATION_INVALID']);
                        }
                    }
                } elseif ($classification === 'operating_expense') {
                    if ($requestedLine === null) {
                        throw ValidationException::withMessages(['purchase_order_line_id' => 'Service Vendor Bill phải chọn đúng một PO line.']);
                    }
                    $serviceAcceptanceLineId = $this->positive($data, 'service_acceptance_line_id');
                    $acceptanceLine = DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')
                        ->where(['sal.id' => $serviceAcceptanceLineId, 'sal.purchase_order_line_id' => $this->id($line, 'id'), 'sa.company_id' => $companyId, 'sa.purchase_order_id' => $orderId, 'sa.status' => 'approved'])
                        ->lockForUpdate()->firstOrFail(['sal.accepted_quantity']);
                    $billed = (int) DB::table('vendor_bill_lines as vbl')->join('vendor_bills as vb', 'vb.id', '=', 'vbl.vendor_bill_id')
                        ->where('vbl.service_acceptance_line_id', $serviceAcceptanceLineId)->whereNotIn('vb.status', ['cancelled', 'rejected'])->sum('vbl.quantity');
                    $billable = $this->id($acceptanceLine, 'accepted_quantity') - $billed;
                } else {
                    throw ValidationException::withMessages(['purchase_order_line_id' => 'Procurement classification chưa hỗ trợ Vendor Bill.']);
                }
                $quantity = $requestedLine === null ? $billable : $this->positive($data, 'quantity');
                if ($quantity > $billable) {
                    throw ValidationException::withMessages(['quantity' => 'Số lượng invoice vượt số lượng inventory đã nhận nhưng chưa billed.']);
                }
                if ($quantity === 0) {
                    continue;
                }
                if ($goodsReceiptLine !== null) {
                    $allocated = (int) DB::table('vendor_bill_receipt_allocations as allocation')
                        ->join('vendor_bill_lines as bill_line', 'bill_line.id', '=', 'allocation.vendor_bill_line_id')
                        ->join('vendor_bills as bill', 'bill.id', '=', 'bill_line.vendor_bill_id')
                        ->where('allocation.goods_receipt_line_id', $this->id($goodsReceiptLine, 'id'))
                        ->whereNotIn('bill.status', ['cancelled', 'rejected', 'reversed'])
                        ->lockForUpdate()->sum('allocation.quantity');
                    if ($quantity > $this->id($goodsReceiptLine, 'received_quantity') - $allocated
                        || $this->id($goodsReceiptLine, 'unit_cost_minor') !== $this->id($line, 'unit_cost_minor')) {
                        throw ValidationException::withMessages(['quantity' => 'GOODS_BILL_RECEIPT_ALLOCATION_EXCEEDS_AVAILABLE']);
                    }
                }
                $amount = $quantity * $this->id($line, 'unit_cost_minor');
                $subtotal += $amount;
                $billLines[] = ['line' => $line, 'quantity' => $quantity, 'amount' => $amount, 'service_acceptance_line_id' => $serviceAcceptanceLineId, 'goods_receipt_line' => $goodsReceiptLine];
            }
            if ($billLines === []) {
                throw ValidationException::withMessages(['quantity' => 'Purchase Order không còn số lượng đã nhận có thể lập Bill.']);
            }
            $costCenterIds = collect($billLines)->map(fn (array $item): ?int => $this->nullableId($item['line'], 'cost_center_id'))->unique()->values();
            $headerCostCenter = $costCenterIds->count() === 1 ? $costCenterIds->first() : null;
            $headerSource = $headerCostCenter === null ? null : $billLines[0]['line'];
            $invoiceDate = $this->date($data, 'invoice_date');
            $taxPolicy = null;
            $tax = 0;
            $taxCategoryCode = $this->nullable($data, 'tax_category_code');
            $currency = strtoupper($this->property($order, 'currency'));
            if ($taxCategoryCode !== null) {
                if ($currency !== 'VND') {
                    throw ValidationException::withMessages(['tax_category_code' => 'TAX_FX_DEFERRED']);
                }
                $taxPolicy = $this->taxPolicies->resolve($companyId, 'vendor_bill', $invoiceDate, strtoupper($taxCategoryCode), $currency);
                $tax = $this->taxPolicies->calculate($subtotal, $this->arrayInteger($taxPolicy, 'rate_basis_points'));
            }
            $id = (int) DB::table('vendor_bills')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->id($order, 'branch_id'), 'vendor_id' => $this->id($order, 'vendor_id'), 'purchase_order_id' => $orderId, 'cost_center_id' => $headerCostCenter, 'cost_center_code_snapshot' => $headerSource === null ? null : $this->nullableProperty($headerSource, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $headerSource === null ? null : $this->nullableProperty($headerSource, 'cost_center_name_snapshot'), 'invoice_number' => $invoice, 'invoice_date' => $invoiceDate, 'due_date' => $this->date($data, 'due_date'), 'currency' => $currency, 'subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $subtotal + $tax, 'tax_status' => $taxPolicy === null ? 'configuration_required' : 'configured', 'tax_category_id' => $taxPolicy['category_id'] ?? null, 'tax_policy_id' => $taxPolicy['policy_id'] ?? null, 'tax_policy_version' => $taxPolicy['version'] ?? null, 'tax_rate_basis_points' => $taxPolicy['rate_basis_points'] ?? null, 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($billLines as $item) {
                $line = $item['line'];
                $receiptLine = $item['goods_receipt_line'];
                $billLineId = (int) DB::table('vendor_bill_lines')->insertGetId(['vendor_bill_id' => $id, 'purchase_order_line_id' => $this->id($line, 'id'), 'goods_receipt_line_id' => is_object($receiptLine) ? $this->id($receiptLine, 'id') : null, 'service_acceptance_line_id' => $item['service_acceptance_line_id'], 'cost_center_id' => $this->nullableId($line, 'cost_center_id'), 'cost_center_code_snapshot' => $this->nullableProperty($line, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $this->nullableProperty($line, 'cost_center_name_snapshot'), 'procurement_classification' => $this->property($line, 'procurement_classification'), 'account_id' => $this->id($line, 'account_id'), 'account_code_snapshot' => $this->property($line, 'account_code_snapshot'), 'account_name_snapshot' => $this->property($line, 'account_name_snapshot'), 'description' => $this->property($line, 'description'), 'quantity' => $item['quantity'], 'unit_cost_minor' => $this->id($line, 'unit_cost_minor'), 'line_total_minor' => $item['amount'], 'created_at' => now(), 'updated_at' => now()]);
                if (is_object($receiptLine)) {
                    DB::table('vendor_bill_receipt_allocations')->insert([
                        'vendor_bill_line_id' => $billLineId,
                        'goods_receipt_line_id' => $this->id($receiptLine, 'id'),
                        'quantity' => $item['quantity'],
                        'unit_cost_minor' => $this->id($line, 'unit_cost_minor'),
                        'allocated_minor' => $item['amount'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function approveBill(int $companyId, User $actor, array $data): int
    {
        $id = $this->positive($data, 'id');

        return DB::transaction(function () use ($companyId, $actor, $id): int {
            $bill = DB::table('vendor_bills as bill')->leftJoin('tax_categories as category', 'category.id', '=', 'bill.tax_category_id')->where(['bill.id' => $id, 'bill.company_id' => $companyId])->lockForUpdate()->firstOrFail(['bill.*', 'category.code as tax_category_code']);
            if ($this->property($bill, 'status') === 'approved') {
                return $id;
            }
            if ($this->property($bill, 'status') !== 'draft' || $this->id($bill, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'Maker không được tự approve Vendor Bill.']);
            }
            $amount = $this->id($bill, 'total_minor');
            $classifications = DB::table('vendor_bill_lines')->where('vendor_bill_id', $id)->distinct()->pluck('procurement_classification');
            if ($classifications->count() !== 1) {
                throw ValidationException::withMessages(['lines' => 'Vendor Bill không được trộn Goods và Service.']);
            }
            $classificationValue = $classifications->first();
            if (! is_string($classificationValue) || $classificationValue === '') {
                throw ValidationException::withMessages(['lines' => 'Vendor Bill classification không hợp lệ.']);
            }
            $classification = $classificationValue;
            $event = $classification === 'operating_expense' ? 'SERVICE_VENDOR_BILL_POSTED' : 'VENDOR_BILL_POSTED';
            $recognition = $this->fxRecognition->prepare($companyId, $bill);
            $receiptMatching = null;
            $taxPolicyId = $this->nullableId($bill, 'tax_policy_id');
            if ($taxPolicyId !== null) {
                $taxPolicy = $this->taxPolicies->resolve($companyId, 'vendor_bill', $this->property($bill, 'invoice_date'), $this->property($bill, 'tax_category_code'), $this->property($bill, 'currency'));
                if ($taxPolicy['policy_id'] !== $taxPolicyId || $taxPolicy['version'] !== $this->id($bill, 'tax_policy_version') || $taxPolicy['rate_basis_points'] !== $this->id($bill, 'tax_rate_basis_points')) {
                    throw ValidationException::withMessages(['tax_policy' => 'TAX_POLICY_SNAPSHOT_MISMATCH']);
                }
                $policy = ['policy_id' => $taxPolicy['accounting_policy_id'], 'version' => $taxPolicy['accounting_policy_version']];
                $lines = $this->taxPolicies->postingLines($taxPolicy, $amount - $this->id($bill, 'tax_minor'), $this->id($bill, 'tax_minor'), 'Vendor bill');
            } elseif ($classification === 'inventory' && ! $recognition->identity) {
                $receiptMatching = $this->receiptFxMatching->prepare($companyId, $id, $recognition);
                $variance = $receiptMatching['variance_minor'];
                $event = $variance > 0
                    ? 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_LOSS'
                    : ($variance < 0 ? 'INVENTORY_VENDOR_BILL_PRICE_VARIANCE_GAIN' : 'INVENTORY_VENDOR_BILL_POSTED');
                $policy = $this->postingPolicies->resolve($companyId, $event, $classification, $this->property($bill, 'invoice_date'), $recognition->functionalCurrency);
                $lines = $this->inventoryBillFxLines($policy['lines'], $receiptMatching);
            } else {
                $policy = $this->postingPolicies->resolve($companyId, $event, $classification, $this->property($bill, 'invoice_date'), $recognition->functionalCurrency);
                $lines = array_map(fn (array $line): array => ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $recognition->functionalAmountMinor : 0, 'credit' => $line['entry_side'] === 'credit' ? $recognition->functionalAmountMinor : 0, 'description' => 'Vendor bill '.$line['line_role']], $policy['lines']);
            }
            $policyId = $this->arrayInteger($policy, 'policy_id');
            $policyVersion = $this->arrayInteger($policy, 'version');
            $journalId = $this->accounting->postPolicyBacked($companyId, $this->id($bill, 'branch_id'), 'vendor_bill', $id, $classification === 'operating_expense' ? 'service_vendor_bill' : 'vendor_bill', $this->property($bill, 'invoice_date'), $recognition->functionalCurrency, $lines, $actor->id, $policyId, $policyVersion);
            if ($taxPolicyId !== null) {
                $this->taxEvidence->record($companyId, $this->id($bill, 'branch_id'), 'vendor_bill', $id, $this->property($bill, 'invoice_date'), $this->property($bill, 'currency'), $amount - $this->id($bill, 'tax_minor'), $this->id($bill, 'tax_minor'), $journalId, $taxPolicy);
            }
            $recognitionId = $this->fxRecognition->record($companyId, $bill, $recognition, $policyId, $policyVersion, $journalId, $actor->id);
            if ($receiptMatching !== null) {
                $this->receiptFxMatching->record($companyId, $id, $recognitionId, $journalId, (int) $actor->id, $receiptMatching);
            }
            DB::table('vendor_bills')->where('id', $id)->update(['status' => 'approved', 'accounting_posting_policy_id' => $policyId, 'accounting_posting_policy_version' => $policyVersion, 'accounting_journal_id' => $journalId, 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function payment(int $companyId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($companyId, $actor, $data): int {
            $billId = $this->positive($data, 'vendor_bill_id');
            $reference = $this->text($data, 'reference');
            $existing = DB::table('ap_payments as p')
                ->join('ap_payment_allocations as a', 'a.payment_id', '=', 'p.id')
                ->leftJoin('supplier_payment_fx_settlements as settlement', 'settlement.ap_payment_allocation_id', '=', 'a.id')
                ->where(['p.company_id' => $companyId, 'p.reference' => $reference])
                ->lockForUpdate()
                ->first(['p.id', 'p.amount_minor', 'p.payment_date', 'p.method', 'a.vendor_bill_id', 'a.allocated_minor', 'settlement.treasury_financial_account_id']);
            if ($existing !== null) {
                $requestedAccount = $this->nullableInteger($data, 'financial_account_id');
                if ($this->id($existing, 'vendor_bill_id') !== $billId
                    || $this->id($existing, 'amount_minor') !== $this->positive($data, 'amount_minor')
                    || $this->id($existing, 'allocated_minor') !== $this->positive($data, 'amount_minor')
                    || $this->property($existing, 'payment_date') !== $this->date($data, 'payment_date')
                    || $this->property($existing, 'method') !== $this->text($data, 'method')
                    || ($requestedAccount !== null && $this->nullableId($existing, 'treasury_financial_account_id') !== $requestedAccount)) {
                    throw ValidationException::withMessages(['reference' => 'AP_PAYMENT_IDEMPOTENCY_CONFLICT']);
                }

                return $this->id($existing, 'id');
            }
            $bill = DB::table('vendor_bills')->where(['id' => $billId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $amount = $this->positive($data, 'amount_minor');
            $paymentDate = $this->date($data, 'payment_date');
            $financialAccountId = $this->nullableInteger($data, 'financial_account_id');
            $allocated = (int) DB::table('ap_payment_allocations')->where('vendor_bill_id', $billId)->sum('allocated_minor');
            $credited = (int) DB::table('supplier_credit_notes')->where(['vendor_bill_id' => $billId, 'status' => 'posted'])->sum('ap_reduction_minor');
            if (! in_array($this->property($bill, 'status'), ['approved', 'partially_paid'], true) || $amount > $this->id($bill, 'total_minor') - $allocated - $credited) {
                throw ValidationException::withMessages(['amount_minor' => 'Payment allocation vượt Bill outstanding.']);
            }
            $settlement = $this->fxSettlement->prepare($companyId, $billId, $amount, $financialAccountId, $paymentDate, (int) $actor->id);
            $paymentId = (int) DB::table('ap_payments')->insertGetId(['company_id' => $companyId, 'vendor_id' => $this->id($bill, 'vendor_id'), 'payment_date' => $this->date($data, 'payment_date'), 'amount_minor' => $amount, 'currency' => $this->property($bill, 'currency'), 'method' => $this->text($data, 'method'), 'reference' => $reference, 'status' => 'approved', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $allocationId = (int) DB::table('ap_payment_allocations')->insertGetId(['payment_id' => $paymentId, 'vendor_bill_id' => $billId, 'allocated_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            $settlement = $this->fxSettlement->captureSnapshot($companyId, $allocationId, $settlement);
            $outstanding = $this->id($bill, 'total_minor') - $credited - $allocated - $amount;
            DB::table('vendor_bills')->where('id', $billId)->update(['status' => $outstanding === 0 ? 'paid' : 'partially_paid', 'updated_at' => now()]);
            $realized = (int) $settlement['realized_fx_minor'];
            $event = $realized > 0 ? 'VENDOR_PAYMENT_FX_GAIN' : ($realized < 0 ? 'VENDOR_PAYMENT_FX_LOSS' : 'VENDOR_PAYMENT');
            $policy = $this->postingPolicies->resolve($companyId, $event, null, $paymentDate, (string) $settlement['functional_currency']);
            $lines = array_map(function (array $line) use ($settlement, $realized): array {
                $value = match ($line['line_role']) {
                    'AP' => (int) $settlement['released_carrying_minor'], 'CASH' => (int) $settlement['settlement_functional_minor'], 'FX_GAIN', 'FX_LOSS' => abs($realized), default => throw ValidationException::withMessages(['accounting_policy' => 'VENDOR_PAYMENT_FX_POLICY_ROLE_INVALID'])
                };

                return ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $value : 0, 'credit' => $line['entry_side'] === 'credit' ? $value : 0, 'description' => 'Vendor payment '.$line['line_role']];
            }, $policy['lines']);
            $journal = $this->accounting->postPolicyBacked($companyId, $this->id($bill, 'branch_id'), 'ap_payment', $paymentId, 'vendor_payment', $paymentDate, (string) $settlement['functional_currency'], $lines, $actor->id, $policy['policy_id'], $policy['version']);
            $treasuryId = $this->treasury->recordSourceToAccount($companyId, $this->id($bill, 'branch_id'), (int) $settlement['treasury_account_id'], 'ap_payment', $paymentId, $journal, $reference, $amount, $paymentDate.' 12:00:00', $actor->id);
            $this->fxSettlement->record($companyId, $paymentId, $allocationId, $journal, $treasuryId, $policy['policy_id'], $policy['version'], $actor->id, $settlement);

            return $paymentId;
        }, 3);
    }

    private function branch(int $companyId, int $branchId): int
    {
        DB::table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->whereNull('deleted_at')->firstOrFail();

        return $branchId;
    }

    /** @return array{id:?int,code:?string,name:?string} */
    private function costCenter(int $companyId, ?int $costCenterId): array
    {
        if ($costCenterId === null) {
            return ['id' => null, 'code' => null, 'name' => null];
        }
        $center = DB::table('cost_centers')->where(['id' => $costCenterId, 'company_id' => $companyId])->firstOrFail(['id', 'code', 'name']);

        return ['id' => $this->id($center, 'id'), 'code' => $this->property($center, 'code'), 'name' => $this->property($center, 'name')];
    }

    /** @param array<string,mixed> $data */
    private function classification(array $data): string
    {
        $value = $this->nullable($data, 'procurement_classification');
        if ($value === null && $this->nullableInteger($data, 'product_variant_id') !== null) {
            return 'inventory';
        }
        if ($value === null) {
            return 'operating_expense';
        }
        if (! in_array($value, ['inventory', 'asset', 'operating_expense'], true)) {
            throw ValidationException::withMessages(['procurement_classification' => 'Phân loại Procurement phải là inventory, asset hoặc operating_expense.']);
        }

        return $value;
    }

    /** @return array{id:int,code:string,name:string} */
    private function account(int $companyId, int $actorId, ?int $accountId, string $classification): array
    {
        $defaultCode = match ($classification) {
            'inventory' => '1200',
            'asset' => '1300',
            'operating_expense' => '5100',
            default => null,
        };
        if ($accountId === null && $defaultCode !== null) {
            $evidence = $this->accounting->accountEvidence($companyId, $defaultCode, 'VND', $actorId);
            $accountId = $evidence['id'];
        }
        $account = $accountId === null ? null : DB::table('accounting_accounts')->where(['company_id' => $companyId, 'id' => $accountId])->first();
        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => 'Tài khoản Budget/Accounting thuộc Company là bắt buộc.']);
        }
        $code = $this->property($account, 'code');
        $type = $this->property($account, 'account_type');
        $valid = match ($classification) {
            'inventory' => $code === '1200',
            'asset' => $code === '1300',
            'operating_expense' => $type === 'expense',
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['account_id' => 'Tài khoản không phù hợp Procurement classification.']);
        }

        return ['id' => $this->id($account, 'id'), 'code' => $code, 'name' => $this->property($account, 'name')];
    }

    private function nullableProperty(object $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : throw ValidationException::withMessages([$key => 'Trường bắt buộc.']);
    }

    /** @param array<string,mixed> $data */
    private function nullable(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string,mixed> $data */
    private function date(array $data, string $key): string
    {
        $value = $this->text($data, $key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : throw ValidationException::withMessages([$key => 'Ngày không hợp lệ.']);
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key, ?int $default = null): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw ValidationException::withMessages([$key => 'Số nguyên không hợp lệ.']));
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $this->integer($data, $key);

        return $value > 0 ? $value : throw ValidationException::withMessages([$key => 'Giá trị phải dương.']);
    }

    /** @param array<string,mixed> $data */
    private function nullableInteger(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? null : $this->integer($data, $key);
    }

    /** @param array<string,mixed> $values */
    private function arrayInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : throw new \RuntimeException('Invalid integer value for '.$key.'.');
    }

    /** @param array<string,mixed> $values */
    private function arrayText(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException("Missing text array value {$key}.");
    }

    /**
     * @param  list<array{entry_side:string,line_role:string,account_code:string}>  $policyLines
     * @param  array{released_grni_minor:int,ap_functional_minor:int,variance_minor:int,rows:list<array<string,int>>}  $matching
     * @return list<array{account_code:string,debit:int,credit:int,description:string}>
     */
    private function inventoryBillFxLines(array $policyLines, array $matching): array
    {
        $variance = $matching['variance_minor'];
        $expected = [
            'GRNI' => ['side' => 'debit', 'amount' => $matching['released_grni_minor']],
            'AP' => ['side' => 'credit', 'amount' => $matching['ap_functional_minor']],
        ];
        if ($variance > 0) {
            $expected['PURCHASE_PRICE_VARIANCE_LOSS'] = ['side' => 'debit', 'amount' => $variance];
        } elseif ($variance < 0) {
            $expected['PURCHASE_PRICE_VARIANCE_GAIN'] = ['side' => 'credit', 'amount' => abs($variance)];
        }

        $lines = [];
        foreach ($policyLines as $line) {
            $role = $line['line_role'];
            $configuration = $expected[$role] ?? null;
            if ($configuration === null || $line['entry_side'] !== $configuration['side']) {
                throw ValidationException::withMessages(['accounting_policy' => 'INVENTORY_VENDOR_BILL_FX_POLICY_ROLE_INVALID']);
            }
            $lines[] = [
                'account_code' => $line['account_code'],
                'debit' => $line['entry_side'] === 'debit' ? $configuration['amount'] : 0,
                'credit' => $line['entry_side'] === 'credit' ? $configuration['amount'] : 0,
                'description' => 'Foreign inventory vendor bill '.$role,
            ];
            unset($expected[$role]);
        }
        if ($expected !== []) {
            throw ValidationException::withMessages(['accounting_policy' => 'INVENTORY_VENDOR_BILL_FX_POLICY_ROLE_MISSING']);
        }

        return $lines;
    }

    private function id(object $row, string $key): int
    {
        $value = get_object_vars($row)[$key] ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid persistence integer: '.$key));
    }

    private function persistenceInteger(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid persistence aggregate integer.'));
    }

    private function nullableId(object $row, string $key): ?int
    {
        $value = get_object_vars($row)[$key] ?? null;

        return $value === null ? null : $this->id($row, $key);
    }

    private function property(object $row, string $key): string
    {
        $value = get_object_vars($row)[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid persistence text: '.$key);
    }
}
