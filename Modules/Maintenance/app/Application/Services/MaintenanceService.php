<?php

namespace Modules\Maintenance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class MaintenanceService
{
    public function __construct(private ConnectionInterface $db, private InventoryService $inventory) {}

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
        $this->workOrder($company, $order);
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
        $this->workOrder($company, $order);
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
            $this->workOrder($company, $order);
            $row = $this->db->table('maintenance_part_requirements')->where(['id' => $part, 'work_order_id' => $order])->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            if ($this->string($row, 'status') === 'ISSUED') {
                return;
            }

            $this->inventory->adjust($this->integer($row, 'product_variant_id'), $this->integer($row, 'inventory_location_id'), 'decrease', $this->integer($row, 'quantity'), ['source_type' => 'maintenance_work_order', 'source_id' => $order, 'reference' => 'MWO-PART-'.$part, 'idempotency_key' => $this->string($row, 'idempotency_key'), 'actor_id' => $actor, 'reason' => 'Maintenance spare part issue']);
            $movement = $this->db->table('inventory_movements')->where('idempotency_key', $this->string($row, 'idempotency_key'))->first();
            abort_unless($movement !== null, 500);
            $this->db->table('maintenance_part_requirements')->where('id', $part)->update(['status' => 'ISSUED', 'inventory_movement_id' => $this->integer($movement, 'id'), 'actual_cost_minor' => abs($this->integer($movement, 'movement_value_minor')), 'updated_at' => now()]);
            $this->audit('part_issued', $company, $order, $actor, ['part_id' => $part]);
        }

        );
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
