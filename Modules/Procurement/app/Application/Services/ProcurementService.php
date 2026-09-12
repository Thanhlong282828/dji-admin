<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class ProcurementService
{
    public function __construct(private InventoryService $inventory, private SourceAccountingPostingService $accounting, private TreasuryService $treasury, private AccountingPostingPolicyResolver $postingPolicies) {}

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
            $id = (int) DB::table('purchase_orders')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->id($request, 'branch_id'), 'purchase_request_id' => $requestId, 'cost_center_id' => $costCenter['id'], 'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'], 'vendor_id' => $vendorId, 'reference' => $this->text($data, 'reference'), 'currency' => 'VND', 'order_date' => $this->date($data, 'order_date'), 'expected_date' => $this->nullable($data, 'expected_date'), 'payment_terms_days' => $this->integer($data, 'payment_terms_days', 0), 'status' => 'draft', 'subtotal_minor' => $subtotal, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
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
            $total = 0;
            $accountingLines = [];
            foreach (DB::table('procurement_goods_receipt_lines as grl')->join('purchase_order_lines as pol', 'pol.id', '=', 'grl.purchase_order_line_id')->where('grl.goods_receipt_id', $id)->lockForUpdate()->get(['grl.*', 'pol.account_code_snapshot', 'pol.cost_center_id', 'pol.cost_center_code_snapshot', 'pol.cost_center_name_snapshot']) as $line) {
                $quantity = $this->id($line, 'received_quantity');
                $unit = $this->id($line, 'unit_cost_minor');
                $this->inventory->adjust($this->id($line, 'product_variant_id'), $this->id($receipt, 'inventory_location_id'), 'increase', $quantity, ['source_type' => 'procurement_goods_receipt_line', 'source_id' => $this->id($line, 'id'), 'reference' => $this->property($receipt, 'reference'), 'idempotency_key' => 'procurement-gr-'.$this->id($line, 'id'), 'actor_id' => $actor->id, 'reason' => 'Purchase Order goods received', 'unit_cost_minor' => $unit]);
                $movementId = DB::table('inventory_movements')->where('idempotency_key', 'procurement-gr-'.$this->id($line, 'id'))->value('id');
                DB::table('procurement_goods_receipt_lines')->where('id', $this->id($line, 'id'))->update(['inventory_movement_id' => is_numeric($movementId) ? (int) $movementId : null, 'updated_at' => now()]);
                DB::table('purchase_order_lines')->where('id', $this->id($line, 'purchase_order_line_id'))->increment('received_quantity', $quantity, ['updated_at' => now()]);
                $lineValue = $quantity * $unit;
                $total += $lineValue;
                $accountingLines[] = ['account_code' => $this->property($line, 'account_code_snapshot'), 'debit' => $lineValue, 'credit' => 0, 'description' => 'Inventory received', 'cost_center_id' => $this->nullableId($line, 'cost_center_id'), 'cost_center_code_snapshot' => $this->nullableProperty($line, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $this->nullableProperty($line, 'cost_center_name_snapshot')];
            }
            DB::table('procurement_goods_receipts')->where('id', $id)->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now(), 'updated_at' => now()]);
            $orderId = $this->id($receipt, 'purchase_order_id');
            $remaining = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
            DB::table('purchase_orders')->where('id', $orderId)->update(['status' => $remaining ? 'partially_received' : 'received', 'updated_at' => now()]);
            $accountingLines[] = ['account_code' => '2150', 'debit' => 0, 'credit' => $total, 'description' => 'Goods received not invoiced'];
            $this->accounting->post($companyId, $this->id($receipt, 'branch_id'), 'goods_receipt', $id, 'inventory_accrual', $this->property($receipt, 'receipt_date'), 'VND', $accountingLines, $actor->id);

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
                if ($classification === 'inventory') {
                    $billed = $this->persistenceInteger($billedByLine->get($this->id($line, 'id')));
                    $billable = $this->id($line, 'received_quantity') - $billed;
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
                $amount = $quantity * $this->id($line, 'unit_cost_minor');
                $subtotal += $amount;
                $billLines[] = ['line' => $line, 'quantity' => $quantity, 'amount' => $amount, 'service_acceptance_line_id' => $serviceAcceptanceLineId];
            }
            if ($billLines === []) {
                throw ValidationException::withMessages(['quantity' => 'Purchase Order không còn số lượng đã nhận có thể lập Bill.']);
            }
            $costCenterIds = collect($billLines)->map(fn (array $item): ?int => $this->nullableId($item['line'], 'cost_center_id'))->unique()->values();
            $headerCostCenter = $costCenterIds->count() === 1 ? $costCenterIds->first() : null;
            $headerSource = $headerCostCenter === null ? null : $billLines[0]['line'];
            $id = (int) DB::table('vendor_bills')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->id($order, 'branch_id'), 'vendor_id' => $this->id($order, 'vendor_id'), 'purchase_order_id' => $orderId, 'cost_center_id' => $headerCostCenter, 'cost_center_code_snapshot' => $headerSource === null ? null : $this->nullableProperty($headerSource, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $headerSource === null ? null : $this->nullableProperty($headerSource, 'cost_center_name_snapshot'), 'invoice_number' => $invoice, 'invoice_date' => $this->date($data, 'invoice_date'), 'due_date' => $this->date($data, 'due_date'), 'currency' => 'VND', 'subtotal_minor' => $subtotal, 'tax_minor' => 0, 'total_minor' => $subtotal, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($billLines as $item) {
                $line = $item['line'];
                DB::table('vendor_bill_lines')->insert(['vendor_bill_id' => $id, 'purchase_order_line_id' => $this->id($line, 'id'), 'goods_receipt_line_id' => null, 'service_acceptance_line_id' => $item['service_acceptance_line_id'], 'cost_center_id' => $this->nullableId($line, 'cost_center_id'), 'cost_center_code_snapshot' => $this->nullableProperty($line, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $this->nullableProperty($line, 'cost_center_name_snapshot'), 'procurement_classification' => $this->property($line, 'procurement_classification'), 'account_id' => $this->id($line, 'account_id'), 'account_code_snapshot' => $this->property($line, 'account_code_snapshot'), 'account_name_snapshot' => $this->property($line, 'account_name_snapshot'), 'description' => $this->property($line, 'description'), 'quantity' => $item['quantity'], 'unit_cost_minor' => $this->id($line, 'unit_cost_minor'), 'line_total_minor' => $item['amount'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function approveBill(int $companyId, User $actor, array $data): int
    {
        $id = $this->positive($data, 'id');

        return DB::transaction(function () use ($companyId, $actor, $id): int {
            $bill = DB::table('vendor_bills')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
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
            $classification = $classifications->first();
            $policyId = null;
            $policyVersion = null;
            if ($classification === 'operating_expense') {
                $policy = $this->postingPolicies->resolve($companyId, 'SERVICE_VENDOR_BILL_POSTED', 'operating_expense', $this->property($bill, 'invoice_date'), $this->property($bill, 'currency'));
                $lines = array_map(fn (array $line): array => ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0, 'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => 'Service vendor bill '.$line['line_role']], $policy['lines']);
                $policyId = $policy['policy_id'];
                $policyVersion = $policy['version'];
            } else {
                $lines = [['account_code' => '2150', 'debit' => $amount, 'credit' => 0, 'description' => 'Clear received not invoiced'], ['account_code' => '2100', 'debit' => 0, 'credit' => $amount, 'description' => 'Vendor payable']];
            }
            $journalId = $this->accounting->post($companyId, $this->id($bill, 'branch_id'), 'vendor_bill', $id, $classification === 'operating_expense' ? 'service_vendor_bill' : 'vendor_bill', $this->property($bill, 'invoice_date'), $this->property($bill, 'currency'), $lines, $actor->id);
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
            $existing = DB::table('ap_payments as p')->join('ap_payment_allocations as a', 'a.payment_id', '=', 'p.id')->where(['p.company_id' => $companyId, 'p.reference' => $reference])->lockForUpdate()->first(['p.id', 'p.amount_minor', 'a.vendor_bill_id', 'a.allocated_minor']);
            if ($existing !== null) {
                if ($this->id($existing, 'vendor_bill_id') !== $billId || $this->id($existing, 'amount_minor') !== $this->positive($data, 'amount_minor') || $this->id($existing, 'allocated_minor') !== $this->positive($data, 'amount_minor')) {
                    throw ValidationException::withMessages(['reference' => 'Payment reference đã được dùng cho nghiệp vụ khác.']);
                }

                return $this->id($existing, 'id');
            }
            $bill = DB::table('vendor_bills')->where(['id' => $billId, 'company_id' => $companyId])->whereIn('status', ['approved', 'partially_paid'])->lockForUpdate()->firstOrFail();
            $amount = $this->positive($data, 'amount_minor');
            $allocated = (int) DB::table('ap_payment_allocations')->where('vendor_bill_id', $billId)->sum('allocated_minor');
            if ($amount > $this->id($bill, 'total_minor') - $allocated) {
                throw ValidationException::withMessages(['amount_minor' => 'Payment allocation vượt Bill outstanding.']);
            }
            $paymentId = (int) DB::table('ap_payments')->insertGetId(['company_id' => $companyId, 'vendor_id' => $this->id($bill, 'vendor_id'), 'payment_date' => $this->date($data, 'payment_date'), 'amount_minor' => $amount, 'currency' => $this->property($bill, 'currency'), 'method' => $this->text($data, 'method'), 'reference' => $reference, 'status' => 'approved', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ap_payment_allocations')->insert(['payment_id' => $paymentId, 'vendor_bill_id' => $billId, 'allocated_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            $outstanding = $this->id($bill, 'total_minor') - $allocated - $amount;
            DB::table('vendor_bills')->where('id', $billId)->update(['status' => $outstanding === 0 ? 'paid' : 'partially_paid', 'updated_at' => now()]);
            $journal = $this->accounting->post($companyId, $this->id($bill, 'branch_id'), 'ap_payment', $paymentId, 'vendor_payment', $this->date($data, 'payment_date'), $this->property($bill, 'currency'), [['account_code' => '2100', 'debit' => $amount, 'credit' => 0, 'description' => 'Settle vendor payable'], ['account_code' => '1100', 'debit' => 0, 'credit' => $amount, 'description' => 'Payment clearing']], $actor->id);
            $this->treasury->recordSource($companyId, $this->id($bill, 'branch_id'), 'ap_payment', $paymentId, $journal, $reference, $amount, $this->date($data, 'payment_date').' 12:00:00', $actor->id);

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
