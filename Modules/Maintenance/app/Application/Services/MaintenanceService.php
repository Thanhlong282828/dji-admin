<?php

namespace Modules\Maintenance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class MaintenanceService
{
    public function __construct(
        private ConnectionInterface $db,
        private InventoryService $inventory,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
    ) {}

    /** @param array<string,mixed> $data */
    public function createRequest(array $data, int $actorId): int
    {
        $company = $this->positive($data['company_id'] ?? null);
        $asset = $this->asset($company, $this->positive($data['asset_id'] ?? null));
        $this->branch($company, $data['branch_id'] ?? null, $asset);
        $id = (int) $this->db->table('maintenance_requests')->insertGetId(['company_id' => $company, 'branch_id' => ($data['branch_id'] ?? null) ?: $this->nullableInteger($asset, 'branch_id'), 'asset_id' => $this->integer($asset, 'id'), 'code' => $data['code'], 'title' => $data['title'], 'description' => $data['description'], 'priority' => $data['priority'], 'failure_category' => $data['failure_category'] ?? null, 'requested_date' => $data['requested_date'] ?? null, 'reported_by' => $actorId, 'reported_at' => now(), 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('request_created', $company, $id, $actorId);

        return $id;
    }

    public function submitRequest(int $company, int $request, int $actor): void
    {
        $this->requestTransition($company, $request, $actor, 'DRAFT', 'SUBMITTED', 'request_submitted');
    }

    public function approveRequest(int $company, int $request, int $actor, bool $approve): void
    {
        $this->db->transaction(function () use ($company, $request, $actor, $approve): void {
            $row = $this->request($company, $request, true);
            $status = $this->string($row, 'status');
            $target = $approve ? 'APPROVED' : 'REJECTED';
            if ($status === $target) {
                return;
            }

            abort_unless($status === 'SUBMITTED', 409);
            abort_if($this->integer($row, 'reported_by') === $actor, 403, 'Người báo cáo không được tự duyệt.');
            $this->db->table('maintenance_requests')->where('id', $request)->update(['status' => $target, 'approved_by' => $actor, 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit('request_'.strtolower($target), $company, $request, $actor);
        }

        );
    }

    public function convertRequest(int $company, int $request, int $actor): int
    {
        return $this->db->transaction(function () use ($company, $request, $actor): int {
            $row = $this->request($company, $request, true);
            $existing = $this->db->table('maintenance_work_orders')->where('maintenance_request_id', $request)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }

            abort_unless($this->string($row, 'status') === 'APPROVED', 409);
            $id = (int) $this->db->table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => $this->nullableInteger($row, 'branch_id'), 'asset_id' => $this->integer($row, 'asset_id'), 'maintenance_request_id' => $request, 'code' => 'MWO-'.$company.'-'.$request, 'title' => $this->string($row, 'title'), 'description' => $this->string($row, 'description'), 'maintenance_type' => 'CORRECTIVE', 'priority' => $this->string($row, 'priority'), 'status' => 'DRAFT', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('maintenance_requests')->where('id', $request)->update(['status' => 'CONVERTED', 'updated_at' => now()]);
            $this->audit('request_converted', $company, $request, $actor, ['work_order_id' => $id]);

            return $id;
        }

        );
    }

    /** @param array<string,mixed> $data */
    public function createWorkOrder(array $data, int $actor): int
    {
        $company = $this->positive($data['company_id'] ?? null);
        $asset = $this->asset($company, $this->positive($data['asset_id'] ?? null));
        $this->branch($company, $data['branch_id'] ?? null, $asset);
        $this->employee($company, $data['assigned_employee_id'] ?? null);
        $id = (int) $this->db->table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => ($data['branch_id'] ?? null) ?: $this->nullableInteger($asset, 'branch_id'), 'asset_id' => $this->integer($asset, 'id'), 'code' => $data['code'], 'title' => $data['title'], 'description' => $data['description'] ?? null, 'maintenance_type' => $data['maintenance_type'], 'priority' => $data['priority'], 'status' => 'DRAFT', 'assigned_employee_id' => ($data['assigned_employee_id'] ?? null) ?: null, 'scheduled_start' => $data['scheduled_start'] ?? null, 'scheduled_end' => $data['scheduled_end'] ?? null, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('work_order_created', $company, $id, $actor);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function transitionWorkOrder(int $company, int $order, string $target, int $actor, array $data = []): void
    {
        $this->db->transaction(function () use ($company, $order, $target, $actor, $data): void {
            $row = $this->workOrder($company, $order, true);
            $current = $this->string($row, 'status');
            abort_if($current === 'CLOSED', 409, 'Work Order đã đóng và không thể thay đổi.');
            if ($current === $target && $target === 'COMPLETED') {
                return;
            }

            $allowed = ['DRAFT' => ['PLANNED', 'CANCELLED'], 'PLANNED' => ['IN_PROGRESS', 'ON_HOLD', 'CANCELLED'], 'IN_PROGRESS' => ['ON_HOLD', 'COMPLETED'], 'ON_HOLD' => ['IN_PROGRESS', 'CANCELLED'], 'COMPLETED' => [], 'CANCELLED' => []];
            abort_unless(in_array($target, $allowed[$current] ?? [], true), 409, 'Chuyển trạng thái Work Order không hợp lệ.');
            if ($target === 'IN_PROGRESS') {
                abort_if($this->nullableInteger($row, 'assigned_employee_id') === null, 409, 'Work Order chưa có kỹ thuật viên.');
            }

            if ($target === 'COMPLETED') {
                $completionNote = $data['completion_note'] ?? null;
                abort_if(! is_string($completionNote) || trim($completionNote) === '', 422, 'Cần ghi chú hoàn thành.');
                abort_if($this->db->table('maintenance_part_requirements')->where(['work_order_id' => $order, 'status' => 'REQUIRED'])->exists(), 409, 'Còn spare part chưa xử lý.');
            }

            $updates = ['status' => $target, 'updated_at' => now()];
            if ($target === 'IN_PROGRESS' && $this->nullableString($row, 'actual_start') === null) {
                $updates['actual_start'] = now();
            }

            if ($target === 'COMPLETED') {
                $updates['actual_end'] = now();
                $updates['completion_note'] = $completionNote;
                $updates['downtime_minutes'] = $data['downtime_minutes'] ?? null;
                $planId = $this->nullableInteger($row, 'preventive_plan_id');
                if ($planId !== null) {
                    $this->advancePlan($planId);
                }

            }

            $this->db->table('maintenance_work_orders')->where('id', $order)->update($updates);
            $this->audit('work_order_'.strtolower($target), $company, $order, $actor);
        }

        );
    }

    public function assign(int $company, int $order, int $employee, int $actor): void
    {
        $this->employee($company, $employee);
        $workOrder = $this->workOrder($company, $order);
        abort_if($this->string($workOrder, 'status') === 'CLOSED', 409, 'Work Order đã đóng và không thể thay đổi.');
        $this->db->table('maintenance_work_orders')->where('id', $order)->update(['assigned_employee_id' => $employee, 'updated_at' => now()]);
        $this->audit('technician_assigned', $company, $order, $actor, ['employee_id' => $employee]);
    }

    /** @param array<string,mixed> $data */
    public function createPlan(array $data, int $actor): int
    {
        $company = $this->positive($data['company_id'] ?? null);
        $asset = $this->asset($company, $this->positive($data['asset_id'] ?? null));

        return (int) $this->db->table('preventive_maintenance_plans')->insertGetId(['company_id' => $company, 'asset_id' => $this->integer($asset, 'id'), 'code' => $data['code'], 'name' => $data['name'], 'frequency_type' => $data['frequency_type'], 'frequency_value' => $data['frequency_value'], 'next_due_date' => $data['next_due_date'], 'is_active' => true, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function generatePreventive(int $company, int $planId, int $actor): int
    {
        return $this->db->transaction(function () use ($company, $planId, $actor): int {
            $plan = $this->db->table('preventive_maintenance_plans')->where(['id' => $planId, 'company_id' => $company, 'is_active' => true])->lockForUpdate()->first();
            abort_unless($plan !== null, 404);
            $due = $this->string($plan, 'next_due_date');
            $existing = $this->db->table('maintenance_work_orders')->where(['preventive_plan_id' => $planId, 'preventive_due_date' => $due])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }

            $asset = $this->asset($company, $this->integer($plan, 'asset_id'));

            return (int) $this->db->table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => $this->nullableInteger($asset, 'branch_id'), 'asset_id' => $this->integer($asset, 'id'), 'preventive_plan_id' => $planId, 'preventive_due_date' => $due, 'code' => 'PM-'.$planId.'-'.str_replace('-', '', $due), 'title' => $this->string($plan, 'name'), 'maintenance_type' => 'PREVENTIVE', 'priority' => 'NORMAL', 'status' => 'DRAFT', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        }

        );
    }

    /** @param array<string,mixed> $data */
    public function requirePart(int $company, int $order, array $data, int $actor): int
    {
        $workOrder = $this->workOrder($company, $order);
        abort_if($this->string($workOrder, 'status') === 'CLOSED', 409, 'Work Order đã đóng và không thể thay đổi.');
        $variant = $this->positive($data['product_variant_id'] ?? null);
        $location = $this->positive($data['inventory_location_id'] ?? null);
        $belongsToCompany = $this->db->table('inventory_locations as l')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->join('products as p', 'p.store_id', '=', 's.id')
            ->join('product_variants as v', 'v.product_id', '=', 'p.id')
            ->where(['l.id' => $location, 'v.id' => $variant, 'sp.company_id' => $company])
            ->whereNull('p.deleted_at')
            ->exists();
        abort_unless($belongsToCompany, 404);

        return (int) $this->db->table('maintenance_part_requirements')->insertGetId(['work_order_id' => $order, 'product_variant_id' => $variant, 'inventory_location_id' => $location, 'quantity' => $data['quantity'], 'status' => 'REQUIRED', 'idempotency_key' => $data['idempotency_key'], 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function issuePart(int $company, int $order, int $part, int $actor): void
    {
        $this->db->transaction(function () use ($company, $order, $part, $actor): void {
            $workOrder = $this->workOrder($company, $order);
            abort_if($this->string($workOrder, 'status') === 'CLOSED', 409, 'Work Order đã đóng và không thể thay đổi.');
            $row = $this->db->table('maintenance_part_requirements')->where(['id' => $part, 'work_order_id' => $order])->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            if ($this->string($row, 'status') === 'ISSUED') {
                return;
            }

            $inventoryEvidence = $this->inventoryEvidence($company, $this->integer($row, 'product_variant_id'), $this->integer($row, 'inventory_location_id'));
            $workOrderBranch = $this->nullableInteger($workOrder, 'branch_id');
            abort_if($workOrderBranch !== null && $workOrderBranch !== $inventoryEvidence['branch_id'], 422, 'Kho xuất phụ tùng không thuộc chi nhánh Work Order.');
            $this->inventory->adjust($this->integer($row, 'product_variant_id'), $this->integer($row, 'inventory_location_id'), 'decrease', $this->integer($row, 'quantity'), ['source_type' => 'maintenance_work_order', 'source_id' => $order, 'reference' => 'MWO-PART-'.$part, 'idempotency_key' => $this->string($row, 'idempotency_key'), 'actor_id' => $actor, 'reason' => 'Maintenance spare part issue']);
            $movement = $this->db->table('inventory_movements')->where('idempotency_key', $this->string($row, 'idempotency_key'))->first();
            abort_unless($movement !== null, 500);
            $amount = abs($this->integer($movement, 'movement_value_minor'));
            abort_if($amount < 1, 422, 'Spare part phải có giá trị tồn kho trước khi xuất.');
            $postingDate = substr($this->string($movement, 'occurred_at'), 0, 10);
            $currency = $inventoryEvidence['currency'];
            $policy = $this->policies->resolve($company, 'MAINTENANCE_PART_CONSUMPTION', null, $postingDate, $currency);
            $description = 'Maintenance spare part consumption · MWO '.$order.' · part '.$part;
            $journal = $this->accounting->postPolicyBacked(
                $company,
                $this->nullableInteger($workOrder, 'branch_id'),
                'maintenance_part_requirement',
                $part,
                'maintenance_part_consumption',
                $postingDate,
                $currency,
                [
                    ['account_code' => $this->policyAccount($policy['lines'], 'EXPENSE', 'debit'), 'debit' => $amount, 'credit' => 0, 'description' => $description],
                    ['account_code' => $this->policyAccount($policy['lines'], 'INVENTORY_ASSET', 'credit'), 'debit' => 0, 'credit' => $amount, 'description' => $description],
                ],
                $actor,
                $policy['policy_id'],
                $policy['version'],
            );
            $this->db->table('maintenance_part_requirements')->where('id', $part)->update(['status' => 'ISSUED', 'inventory_movement_id' => $this->integer($movement, 'id'), 'actual_cost_minor' => $amount, 'updated_at' => now()]);
            $this->audit('part_issued', $company, $order, $actor, ['part_id' => $part, 'inventory_movement_id' => $this->integer($movement, 'id'), 'accounting_journal_id' => $journal, 'accounting_policy_id' => $policy['policy_id'], 'accounting_policy_version' => $policy['version']]);
        }

        );
    }

    /** @param array<string,mixed> $data */
    public function returnPart(int $company, int $order, int $part, int $actor, array $data): int
    {
        return $this->db->transaction(function () use ($company, $order, $part, $actor, $data): int {
            $workOrder = $this->workOrder($company, $order);
            abort_if($this->string($workOrder, 'status') === 'CLOSED', 409, 'Work Order đã đóng và không thể thay đổi.');
            $requirement = $this->db->table('maintenance_part_requirements')->where(['id' => $part, 'work_order_id' => $order])->lockForUpdate()->first();
            abort_unless($requirement !== null, 404);
            abort_unless($this->string($requirement, 'status') === 'ISSUED', 409, 'Chỉ phụ tùng đã xuất mới được trả kho.');

            $quantity = $this->positive($data['quantity'] ?? null);
            $reference = is_string($data['return_reference'] ?? null) ? trim($data['return_reference']) : '';
            $key = is_string($data['idempotency_key'] ?? null) ? trim($data['idempotency_key']) : '';
            $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
            $postingDate = is_string($data['return_date'] ?? null) ? $data['return_date'] : '';
            $existing = $this->db->table('maintenance_part_returns')->where('company_id', $company)->where(static fn (Builder $query): Builder => $query->where('return_reference', $reference)->orWhere('idempotency_key', $key))->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->integer($existing, 'maintenance_part_requirement_id') !== $part || $this->integer($existing, 'quantity') !== $quantity || $this->string($existing, 'return_reference') !== $reference || $this->string($existing, 'idempotency_key') !== $key) {
                    throw ValidationException::withMessages(['idempotency_key' => 'MAINTENANCE_PART_RETURN_CONFLICT']);
                }

                return $this->integer($existing, 'id');
            }

            $issueMovementId = $this->nullableInteger($requirement, 'inventory_movement_id');
            abort_if($issueMovementId === null, 409, 'Thiếu Inventory Movement của lần xuất gốc.');
            $issueMovement = $this->db->table('inventory_movements')->where('id', $issueMovementId)->first();
            abort_unless($issueMovement !== null, 409, 'Inventory Movement gốc không tồn tại.');
            $issuedQuantity = $this->integer($requirement, 'quantity');
            $issuedValue = $this->integer($requirement, 'actual_cost_minor');
            abort_if($issuedValue < 1, 422, 'Thiếu cost basis của lần xuất gốc.');
            $returned = $this->db->table('maintenance_part_returns')->where(['maintenance_part_requirement_id' => $part, 'status' => 'POSTED'])->selectRaw('COALESCE(SUM(quantity),0) quantity, COALESCE(SUM(returned_value_minor),0) value')->first();
            $returnedQuantity = $returned === null ? 0 : $this->integer($returned, 'quantity');
            $returnedValue = $returned === null ? 0 : $this->integer($returned, 'value');
            abort_if($returnedQuantity + $quantity > $issuedQuantity, 422, 'Số lượng trả vượt số lượng phụ tùng đã xuất.');

            $cumulativeQuantity = $returnedQuantity + $quantity;
            $cumulativeValue = $cumulativeQuantity === $issuedQuantity ? $issuedValue : intdiv(($issuedValue * $cumulativeQuantity) + intdiv($issuedQuantity, 2), $issuedQuantity);
            $returnValue = $cumulativeValue - $returnedValue;
            abort_if($returnValue < 1, 422, 'Giá trị trả kho theo cost basis gốc không hợp lệ.');
            $inventoryEvidence = $this->inventoryEvidence($company, $this->integer($requirement, 'product_variant_id'), $this->integer($requirement, 'inventory_location_id'));
            $workOrderBranch = $this->nullableInteger($workOrder, 'branch_id');
            abort_if($workOrderBranch !== null && $workOrderBranch !== $inventoryEvidence['branch_id'], 422, 'Kho trả phụ tùng không thuộc chi nhánh Work Order.');
            $policy = $this->policies->resolve($company, 'MAINTENANCE_PART_RETURN', null, $postingDate, $inventoryEvidence['currency']);
            $returnId = (int) $this->db->table('maintenance_part_returns')->insertGetId([
                'company_id' => $company, 'branch_id' => $workOrderBranch, 'work_order_id' => $order, 'maintenance_part_requirement_id' => $part,
                'original_issue_movement_id' => $issueMovementId, 'inventory_location_id' => $this->integer($requirement, 'inventory_location_id'),
                'quantity' => $quantity, 'returned_value_minor' => $returnValue, 'currency' => $inventoryEvidence['currency'], 'return_reference' => $reference,
                'idempotency_key' => $key, 'return_type' => 'UNUSED_PHYSICAL_RETURN', 'reason' => $reason, 'status' => 'POSTED',
                'accounting_policy_id' => $policy['policy_id'], 'accounting_policy_version' => $policy['version'], 'created_by' => $actor, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $unitCost = intdiv($returnValue + intdiv($quantity, 2), $quantity);
            $this->inventory->adjust($this->integer($requirement, 'product_variant_id'), $this->integer($requirement, 'inventory_location_id'), 'increase', $quantity, [
                'source_type' => 'maintenance_part_return', 'source_id' => $returnId, 'reference' => $reference, 'idempotency_key' => $key,
                'actor_id' => $actor, 'reason' => 'Unused maintenance spare part physical return', 'unit_cost_minor' => $unitCost, 'movement_value_minor' => $returnValue,
            ]);
            $movement = $this->db->table('inventory_movements')->where('idempotency_key', $key)->first();
            abort_unless($movement !== null && $this->integer($movement, 'movement_value_minor') === $returnValue, 500, 'Inventory return movement không khớp cost basis gốc.');
            $description = 'Unused maintenance spare part return · MWO '.$order.' · return '.$returnId;
            $journal = $this->accounting->postPolicyBacked($company, $workOrderBranch, 'maintenance_part_return', $returnId, 'maintenance_part_return', $postingDate, $inventoryEvidence['currency'], [
                ['account_code' => $this->policyAccount($policy['lines'], 'INVENTORY_ASSET', 'debit'), 'debit' => $returnValue, 'credit' => 0, 'description' => $description],
                ['account_code' => $this->policyAccount($policy['lines'], 'EXPENSE', 'credit'), 'debit' => 0, 'credit' => $returnValue, 'description' => $description],
            ], $actor, $policy['policy_id'], $policy['version']);
            $this->db->table('maintenance_part_returns')->where('id', $returnId)->update(['inventory_movement_id' => $this->integer($movement, 'id'), 'accounting_journal_id' => $journal, 'updated_at' => now()]);
            $this->audit('part_returned', $company, $order, $actor, ['part_id' => $part, 'part_return_id' => $returnId, 'original_issue_movement_id' => $issueMovementId, 'inventory_movement_id' => $this->integer($movement, 'id'), 'accounting_journal_id' => $journal, 'accounting_policy_id' => $policy['policy_id'], 'accounting_policy_version' => $policy['version']]);

            return $returnId;
        }, 3);
    }

    /** @return array{currency:string,branch_id:int} */
    private function inventoryEvidence(int $company, int $variant, int $location): array
    {
        $evidence = $this->db->table('inventory_locations as l')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->join('products as p', 'p.store_id', '=', 's.id')
            ->join('product_variants as v', 'v.product_id', '=', 'p.id')
            ->where(['l.id' => $location, 'v.id' => $variant, 'sp.company_id' => $company])
            ->whereNull('p.deleted_at')
            ->first(['p.currency', 'l.branch_id']);
        abort_unless($evidence !== null, 404);
        $currency = get_object_vars($evidence)['currency'] ?? null;
        if (! is_string($currency) || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['currency' => 'MAINTENANCE_PART_CURRENCY_INVALID']);
        }

        return ['currency' => $currency, 'branch_id' => $this->integer($evidence, 'branch_id')];
    }

    /** @param list<array{entry_side:string,line_role:string,account_code:string}> $lines */
    private function policyAccount(array $lines, string $role, string $side): string
    {
        foreach ($lines as $line) {
            if ($line['line_role'] === $role && $line['entry_side'] === $side) {
                return $line['account_code'];
            }
        }

        throw ValidationException::withMessages(['accounting_policy' => 'MAINTENANCE_PART_POLICY_INVALID']);
    }

    private function requestTransition(int $c, int $id, int $a, string $from, string $to, string $event): void
    {
        $this->db->transaction(function () use ($c, $id, $a, $from, $to, $event): void {
            $row = $this->request($c, $id, true);
            if ($this->string($row, 'status') === $to) {
                return;
            }

            abort_unless($this->string($row, 'status') === $from, 409);
            $this->db->table('maintenance_requests')->where('id', $id)->update(['status' => $to, 'updated_at' => now()]);
            $this->audit($event, $c, $id, $a);
        }

        );
    }

    private function request(int $c, int $id, bool $lock = false): object
    {
        $q = $this->db->table('maintenance_requests')->where(['id' => $id, 'company_id' => $c]);
        $r = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($r !== null, 404);

        return $r;
    }

    private function workOrder(int $c, int $id, bool $lock = false): object
    {
        $q = $this->db->table('maintenance_work_orders')->where(['id' => $id, 'company_id' => $c]);
        $r = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($r !== null, 404);

        return $r;
    }

    private function asset(int $c, int $id): object
    {
        $r = $this->db->table('finance_assets')->where(['id' => $id, 'company_id' => $c])->whereNotIn('status', ['disposed', 'inactive'])->first();
        abort_unless($r !== null, 404);

        return $r;
    }

    private function branch(int $c, mixed $id, object $asset): void
    {
        if ($id !== null && $id !== '') {
            abort_unless($this->db->table('branches')->where(['id' => $this->positive($id), 'company_id' => $c])->exists(), 404);
            abort_unless($this->integer($asset, 'branch_id') === $this->positive($id), 422, 'Chi nhánh không khớp tài sản.');
        }

    }

    private function employee(int $c, mixed $id): void
    {
        if ($id !== null && $id !== '') {
            abort_unless($this->db->table('employees')->where(['id' => $this->positive($id), 'company_id' => $c])->whereIn('employment_status', ['active', 'probation'])->exists(), 404);
        }

    }

    private function advancePlan(int $id): void
    {
        $p = $this->db->table('preventive_maintenance_plans')->where('id', $id)->lockForUpdate()->first();
        if ($p === null) {
            return;
        }

        $date = new CarbonImmutable($this->string($p, 'next_due_date'));
        $value = $this->integer($p, 'frequency_value');
        $next = match ($this->string($p, 'frequency_type')) {
            'DAILY' => $date->addDays($value),'WEEKLY' => $date->addWeeks($value),'MONTHLY' => $date->addMonthsNoOverflow($value),'YEARLY' => $date->addYearsNoOverflow($value),default => $date
        };
        $this->db->table('preventive_maintenance_plans')->where('id', $id)->update(['last_completed_at' => now(), 'next_due_date' => $next->toDateString(), 'updated_at' => now()]);
    }

    private function positive(mixed $v): int
    {
        $n = is_numeric($v) ? (int) $v : 0;
        abort_if($n < 1, 422);

        return $n;
    }

    private function string(object $r, string $k): string
    {
        $v = get_object_vars($r)[$k] ?? null;
        abort_unless(is_string($v), 500);

        return $v;
    }

    private function nullableString(object $r, string $k): ?string
    {
        $v = get_object_vars($r)[$k] ?? null;

        return is_string($v) ? $v : null;
    }

    private function integer(object $r, string $k): int
    {
        $v = get_object_vars($r)[$k] ?? null;
        abort_unless(is_numeric($v), 500);

        return (int) $v;
    }

    private function nullableInteger(object $r, string $k): ?int
    {
        $v = get_object_vars($r)[$k] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /** @param array<string,mixed> $extra */
    private function audit(string $event, int $c, int $id, int $actor, array $extra = []): void
    {
        activity('maintenance')->causedBy($actor)->event($event)->withProperties(['company_id' => $c, 'maintenance_id' => $id, ...$extra])->log('Maintenance operation');
    }
}
