<?php

namespace Modules\Maintenance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;

final readonly class MaintenanceWorkOrderCloseoutService
{
    public function __construct(private ConnectionInterface $db) {}

    public function accept(int $companyId, int $workOrderId, int $actorId, string $note, string $idempotencyKey): int
    {
        return $this->db->transaction(function () use ($companyId, $workOrderId, $actorId, $note, $idempotencyKey): int {
            $order = $this->order($companyId, $workOrderId, true);
            abort_unless($this->text($order, 'status') === 'COMPLETED', 409, 'Work Order phải hoàn thành trước khi nghiệm thu.');
            abort_if(trim($note) === '' || trim($idempotencyKey) === '', 422);
            $existing = $this->db->table('maintenance_work_order_acceptances')->where('work_order_id', $workOrderId)->orWhere('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->integer($existing, 'work_order_id') !== $workOrderId || $this->text($existing, 'idempotency_key') !== $idempotencyKey) {
                    throw ValidationException::withMessages(['idempotency_key' => 'MAINTENANCE_ACCEPTANCE_CONFLICT']);
                }

                return $this->integer($existing, 'id');
            }

            $id = (int) $this->db->table('maintenance_work_order_acceptances')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->nullableInteger($order, 'branch_id'), 'work_order_id' => $workOrderId,
                'idempotency_key' => $idempotencyKey, 'acceptance_note' => trim($note), 'accepted_by' => $actorId,
                'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit('work_order_accepted', $companyId, $workOrderId, $actorId, ['acceptance_id' => $id]);

            return $id;
        }, 3);
    }

    public function close(int $companyId, int $workOrderId, int $actorId, string $idempotencyKey): int
    {
        return $this->db->transaction(function () use ($companyId, $workOrderId, $actorId, $idempotencyKey): int {
            $order = $this->order($companyId, $workOrderId, true);
            $existing = $this->db->table('maintenance_work_order_closeouts')->where('work_order_id', $workOrderId)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->text($existing, 'idempotency_key') !== $idempotencyKey) {
                    throw ValidationException::withMessages(['idempotency_key' => 'MAINTENANCE_CLOSEOUT_CONFLICT']);
                }

                return $this->integer($existing, 'id');
            }
            abort_unless($this->text($order, 'status') === 'COMPLETED', 409, 'Work Order phải ở trạng thái COMPLETED.');
            $acceptance = $this->db->table('maintenance_work_order_acceptances')->where(['company_id' => $companyId, 'work_order_id' => $workOrderId])->lockForUpdate()->first();
            abort_unless($acceptance !== null, 409, 'Work Order chưa có bằng chứng nghiệm thu.');

            $snapshot = $this->reconcile($companyId, $workOrderId, $this->nullableInteger($order, 'branch_id'));
            ksort($snapshot);
            $canonical = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $checksum = hash('sha256', $canonical);
            $id = (int) $this->db->table('maintenance_work_order_closeouts')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->nullableInteger($order, 'branch_id'), 'work_order_id' => $workOrderId,
                'acceptance_id' => $this->integer($acceptance, 'id'), 'idempotency_key' => $idempotencyKey, 'currency' => $snapshot['currency'],
                'gross_spare_cost_minor' => $snapshot['gross_spare_cost_minor'], 'returned_spare_cost_minor' => $snapshot['returned_spare_cost_minor'],
                'net_spare_cost_minor' => $snapshot['net_spare_cost_minor'], 'other_verified_cost_minor' => $snapshot['other_verified_cost_minor'],
                'total_actual_cost_minor' => $snapshot['total_actual_cost_minor'], 'lineage_snapshot' => $canonical, 'snapshot_checksum' => $checksum,
                'closed_by' => $actorId, 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('maintenance_work_orders')->where('id', $workOrderId)->update(['status' => 'CLOSED', 'updated_at' => now()]);
            $this->audit('work_order_closed', $companyId, $workOrderId, $actorId, ['closeout_id' => $id, 'snapshot_checksum' => $checksum, 'total_actual_cost_minor' => $snapshot['total_actual_cost_minor']]);

            return $id;
        }, 3);
    }

    /** @return array<string,mixed> */
    public function preview(int $companyId, int $workOrderId): array
    {
        $order = $this->order($companyId, $workOrderId);

        return $this->reconcile($companyId, $workOrderId, $this->nullableInteger($order, 'branch_id'));
    }

    /** @return array{currency:string,gross_spare_cost_minor:int,returned_spare_cost_minor:int,net_spare_cost_minor:int,other_verified_cost_minor:int,total_actual_cost_minor:int,issue_movement_ids:list<int>,return_movement_ids:list<int>,issue_journal_ids:list<int>,return_journal_ids:list<int>,expense_ids:list<int>} */
    private function reconcile(int $companyId, int $workOrderId, ?int $branchId): array
    {
        $journalEvidence = $this->db->table('payroll_accounting_journal_lines as line')
            ->join('payroll_accounting_journals as evidence_journal', 'evidence_journal.id', '=', 'line.journal_id')
            ->leftJoin('accounting_posting_policies as evidence_policy', static fn (JoinClause $join): JoinClause => $join
                ->on('evidence_policy.company_id', '=', 'evidence_journal.company_id')
                ->whereRaw("evidence_policy.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy_id')) AS UNSIGNED)")
                ->whereRaw("evidence_policy.version = CAST(JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy_version')) AS UNSIGNED)"))
            ->selectRaw('line.journal_id, SUM(line.debit_minor) line_debit_minor, SUM(line.credit_minor) line_credit_minor, SUM(CASE WHEN evidence_policy.id IS NOT NULL THEN 1 ELSE 0 END) policy_lineage_count')
            ->groupBy('line.journal_id');
        $parts = $this->db->table('maintenance_part_requirements as p')
            ->join('maintenance_work_orders as w', 'w.id', '=', 'p.work_order_id')
            ->leftJoin('inventory_movements as m', 'm.id', '=', 'p.inventory_movement_id')
            ->leftJoin('payroll_accounting_journals as j', static fn (JoinClause $join): JoinClause => $join->on('j.source_id', '=', 'p.id')->where('j.source_type', 'maintenance_part_requirement')->where('j.posting_type', 'maintenance_part_consumption')->whereNull('j.reversal_of_journal_id'))
            ->leftJoinSub($journalEvidence, 'je', 'je.journal_id', '=', 'j.id')
            ->where(['p.work_order_id' => $workOrderId, 'w.company_id' => $companyId])->get(['p.*', 'm.id as movement_id', 'm.product_variant_id as movement_product_variant_id', 'm.inventory_location_id as movement_location_id', 'm.movement_value_minor', 'm.source_type as movement_source_type', 'm.source_id as movement_source_id', 'j.id as journal_id', 'j.company_id as journal_company_id', 'j.branch_id as journal_branch_id', 'j.currency as journal_currency', 'j.total_debit_minor', 'j.total_credit_minor', 'je.line_debit_minor', 'je.line_credit_minor', 'je.policy_lineage_count']);
        abort_if($parts->contains(fn (object $part): bool => $this->text($part, 'status') !== 'ISSUED'), 409, 'Còn spare part chưa được xuất.');
        $currencies = [];
        $issueMovements = [];
        $issueJournals = [];
        $gross = 0;
        foreach ($parts as $part) {
            $amount = $this->integer($part, 'actual_cost_minor');
            abort_if($this->nullableInteger($part, 'movement_id') === null || $this->text($part, 'movement_source_type') !== 'maintenance_work_order' || $this->integer($part, 'movement_source_id') !== $workOrderId || $this->integer($part, 'movement_product_variant_id') !== $this->integer($part, 'product_variant_id') || $this->integer($part, 'movement_location_id') !== $this->integer($part, 'inventory_location_id') || abs($this->integer($part, 'movement_value_minor')) !== $amount, 409, 'Issue movement thiếu hoặc không khớp.');
            $this->assertJournal($part, $companyId, $branchId, $amount);
            $gross += $amount;
            $issueMovements[] = $this->integer($part, 'movement_id');
            $issueJournals[] = $this->integer($part, 'journal_id');
            $currencies[] = $this->text($part, 'journal_currency');
        }

        $returns = $this->db->table('maintenance_part_returns as r')->join('maintenance_part_requirements as p', 'p.id', '=', 'r.maintenance_part_requirement_id')->leftJoin('inventory_movements as m', 'm.id', '=', 'r.inventory_movement_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'r.accounting_journal_id')->leftJoinSub($journalEvidence, 'je', 'je.journal_id', '=', 'j.id')->where(['r.company_id' => $companyId, 'r.work_order_id' => $workOrderId, 'r.status' => 'POSTED'])->get(['r.*', 'p.product_variant_id as required_product_variant_id', 'm.id as movement_id', 'm.product_variant_id as movement_product_variant_id', 'm.inventory_location_id as movement_location_id', 'm.movement_value_minor', 'm.source_type as movement_source_type', 'm.source_id as movement_source_id', 'j.id as journal_id', 'j.company_id as journal_company_id', 'j.branch_id as journal_branch_id', 'j.currency as journal_currency', 'j.total_debit_minor', 'j.total_credit_minor', 'je.line_debit_minor', 'je.line_credit_minor', 'je.policy_lineage_count']);
        $returned = 0;
        $returnMovements = [];
        $returnJournals = [];
        foreach ($returns as $return) {
            $amount = $this->integer($return, 'returned_value_minor');
            abort_if($this->nullableInteger($return, 'movement_id') === null || $this->text($return, 'movement_source_type') !== 'maintenance_part_return' || $this->integer($return, 'movement_source_id') !== $this->integer($return, 'id') || $this->integer($return, 'movement_product_variant_id') !== $this->integer($return, 'required_product_variant_id') || $this->integer($return, 'movement_location_id') !== $this->integer($return, 'inventory_location_id') || $this->integer($return, 'movement_value_minor') !== $amount, 409, 'Return movement thiếu hoặc không khớp.');
            abort_if($this->nullableInteger($return, 'accounting_policy_id') === null || $this->nullableInteger($return, 'accounting_policy_version') === null, 409, 'Return policy snapshot không hợp lệ.');
            $this->assertJournal($return, $companyId, $branchId, $amount);
            $returned += $amount;
            $returnMovements[] = $this->integer($return, 'movement_id');
            $returnJournals[] = $this->integer($return, 'journal_id');
            $currencies[] = $this->text($return, 'currency');
        }
        abort_if($returned > $gross, 409, 'Returned spare cost vượt gross spare cost.');

        $expenses = $this->db->table('finance_expenses')->where(['company_id' => $companyId, 'source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $workOrderId])->whereIn('status', ['approved', 'paid'])->get(['id', 'branch_id', 'amount_minor', 'currency']);
        $other = 0;
        foreach ($expenses as $expense) {
            abort_if($branchId !== null && $this->nullableInteger($expense, 'branch_id') !== $branchId, 409, 'Finance Expense khác chi nhánh Work Order.');
            $other += $this->integer($expense, 'amount_minor');
            $currencies[] = $this->text($expense, 'currency');
        }
        if ($currencies === []) {
            $currency = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code') ?? $this->db->table('companies')->where('id', $companyId)->value('currency_code');
            abort_unless(is_string($currency) && preg_match('/^[A-Z]{3}$/', $currency), 409, 'Company currency chưa được cấu hình.');
        } else {
            $currency = $currencies[0];
            abort_if(count(array_unique($currencies)) !== 1, 409, 'Bằng chứng chi phí khác currency.');
        }
        $net = $gross - $returned;

        $expenseIds = [];
        foreach ($expenses as $expense) {
            $expenseIds[] = $this->integer($expense, 'id');
        }

        return ['currency' => $currency, 'gross_spare_cost_minor' => $gross, 'returned_spare_cost_minor' => $returned, 'net_spare_cost_minor' => $net, 'other_verified_cost_minor' => $other, 'total_actual_cost_minor' => $net + $other, 'issue_movement_ids' => $issueMovements, 'return_movement_ids' => $returnMovements, 'issue_journal_ids' => $issueJournals, 'return_journal_ids' => $returnJournals, 'expense_ids' => $expenseIds];
    }

    private function assertJournal(object $row, int $companyId, ?int $branchId, int $amount): void
    {
        abort_if($this->nullableInteger($row, 'journal_id') === null || $this->integer($row, 'journal_company_id') !== $companyId || ($branchId !== null && $this->nullableInteger($row, 'journal_branch_id') !== $branchId) || $this->integer($row, 'total_debit_minor') !== $amount || $this->integer($row, 'total_credit_minor') !== $amount || $this->integer($row, 'line_debit_minor') !== $amount || $this->integer($row, 'line_credit_minor') !== $amount || $this->integer($row, 'policy_lineage_count') < 2, 409, 'Accounting journal thiếu, mất policy lineage hoặc không cân bằng.');
    }

    private function order(int $companyId, int $workOrderId, bool $lock = false): object
    {
        $query = $this->db->table('maintenance_work_orders')->where(['id' => $workOrderId, 'company_id' => $companyId]);
        $order = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($order !== null, 404);

        return $order;
    }

    private function text(object $row, string $field): string
    {
        $value = get_object_vars($row)[$field] ?? null;
        abort_unless(is_string($value), 409);

        return $value;
    }

    private function integer(object $row, string $field): int
    {
        $value = get_object_vars($row)[$field] ?? null;
        abort_unless(is_numeric($value), 409);

        return (int) $value;
    }

    private function nullableInteger(object $row, string $field): ?int
    {
        $value = get_object_vars($row)[$field] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string,mixed> $extra */
    private function audit(string $event, int $companyId, int $workOrderId, int $actorId, array $extra = []): void
    {
        activity('maintenance')->causedBy($actorId)->event($event)->withProperties(['company_id' => $companyId, 'maintenance_id' => $workOrderId, ...$extra])->log('Maintenance operation');
    }
}
