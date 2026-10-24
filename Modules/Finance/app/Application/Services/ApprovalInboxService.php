<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class ApprovalInboxService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{pending:list<array<string,int|string>>,submitted:list<array<string,int|string>>,history:list<array<string,int|string>>} */
    public function read(int $companyId, int $userId, bool $includeDownstreamReversals = true): array
    {
        /** @var list<array<string,int|string>> $pending */
        $pending = [];
        /** @var list<array<string,int|string>> $submitted */
        $submitted = [];
        /** @var list<array<string,int|string>> $history */
        $history = [];
        $this->collect($pending, $submitted, $history, $companyId, $userId, 'purchase_requests', 'Purchase Request', 'requester_id', 'reviewed_by', 'reference', 'admin.procurement.requests', []);
        $this->collect($pending, $submitted, $history, $companyId, $userId, 'purchase_orders', 'Purchase Order', 'created_by', 'approved_by', 'reference', 'admin.procurement.orders', []);
        $this->collect($pending, $submitted, $history, $companyId, $userId, 'finance_expenses', 'Expense', 'created_by', 'reviewed_by', 'reference', 'admin.finance.operations', ['page' => 'expenses']);
        $this->collect($pending, $submitted, $history, $companyId, $userId, 'vendor_bills', 'Vendor Bill', 'created_by', 'approved_by', 'invoice_number', 'admin.procurement.payables', []);
        $this->collectServiceAcceptances($pending, $submitted, $history, $companyId, $userId);
        if ($includeDownstreamReversals) {
            $this->collectDownstreamReversals($pending, $submitted, $history, $companyId, $userId);
        }
        $this->collect($pending, $submitted, $history, $companyId, $userId, 'accounting_period_close_requests', 'Accounting Period Close', 'requested_by', 'decided_by', 'id', 'admin.finance.period-close', []);
        $this->collectMaintenance($pending, $submitted, $history, $companyId, $userId);
        $this->collectProjectTimesheets($pending, $submitted, $history, $companyId, $userId);
        $this->collectPayroll($pending, $submitted, $history, $companyId, $userId);
        $this->collectWorkflowTasks($pending, $history, $companyId, $userId);

        return ['pending' => $pending, 'submitted' => $submitted, 'history' => $history];
    }

    /**
     * @param  list<array<string,int|string>>  $pending
     * @param  list<array<string,int|string>>  $submitted
     * @param  list<array<string,int|string>>  $history
     */
    private function collectDownstreamReversals(array &$pending, array &$submitted, array &$history, int $companyId, int $userId): void
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $user->hasAnyPermission([
            'treasury.payment.reversal.request',
            'treasury.payment.reversal.approve',
            'vendor-bill.reversal.request',
            'vendor-bill.reversal.approve',
        ])) {
            return;
        }
        $definitions = [
            ['ap_payment_reversal_requests', 'AP Payment Reversal', 'ap_payment_id', 'admin.finance.treasury', 'treasury.payment.reversal.approve'],
            ['vendor_bill_reversal_requests', 'Vendor Bill Reversal', 'vendor_bill_id', 'admin.procurement.payables', 'vendor-bill.reversal.approve'],
        ];
        foreach ($definitions as [$table, $type, $reference, $route, $permission]) {
            foreach ($this->db->table($table)->where('company_id', $companyId)->orderByDesc('updated_at')->limit(100)->get() as $row) {
                $values = $this->values($row);
                $status = strtolower($this->text($values['status'] ?? null));
                $makerId = $this->integer($values['requested_by'] ?? null);
                $item = $this->item($type, $values, $reference, 'requested_by', 'approved_by', $route, ['company_id' => (string) $companyId]);
                if ($status === 'submitted' && $makerId !== $userId && $user->can($permission)) {
                    $pending[] = $item;
                } elseif ($status === 'submitted' && $makerId === $userId) {
                    $submitted[] = $item;
                } elseif ($status === 'reversed') {
                    $history[] = $item;
                }
            }
        }
    }

    /**
     * @param  list<array<string,int|string>>  $pending
     * @param  list<array<string,int|string>>  $submitted
     * @param  list<array<string,int|string>>  $history
     */
    private function collectServiceAcceptances(array &$pending, array &$submitted, array &$history, int $companyId, int $userId): void
    {
        foreach ($this->db->table('service_acceptances')->where('company_id', $companyId)->orderByDesc('updated_at')->limit(100)->get() as $row) {
            $values = $this->values($row);
            $status = strtolower($this->text($values['status'] ?? null));
            $reversal = in_array($status, ['reversal_requested', 'reversed'], true);
            $values['inbox_maker'] = $reversal ? ($values['reversal_requested_by'] ?? 0) : ($values['created_by'] ?? 0);
            $values['inbox_approver'] = $reversal ? ($values['reversed_by'] ?? null) : ($values['approved_by'] ?? null);
            $item = $this->item($reversal ? 'Service Acceptance Reversal Approval' : 'Service Acceptance Approval', $values, 'reference', 'inbox_maker', 'inbox_approver', 'admin.procurement.service-acceptances', ['company_id' => (string) $companyId]);
            $makerId = $this->integer($values['inbox_maker']);
            if (in_array($status, ['submitted', 'reversal_requested'], true) && $makerId !== $userId) {
                $pending[] = $item;
            } elseif ($makerId === $userId && in_array($status, ['draft', 'submitted', 'reversal_requested'], true)) {
                $submitted[] = $item;
            } elseif (in_array($status, ['approved', 'reversed'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  list<array<string, int|string>>  $pending
     * @param  list<array<string, int|string>>  $submitted
     * @param  list<array<string, int|string>>  $history
     */
    private function collectMaintenance(array &$pending, array &$submitted, array &$history, int $companyId, int $userId): void
    {
        foreach ($this->db->table('maintenance_requests')->where('company_id', $companyId)->orderByDesc('updated_at')->limit(100)->get() as $row) {
            $values = $this->values($row);
            $status = $this->text($values['status'] ?? null);
            $makerId = $this->integer($values['reported_by'] ?? null);
            $item = $this->item('Maintenance Request', $values, 'code', 'reported_by', 'approved_by', 'admin.maintenance.requests', ['company_id' => (string) $companyId]);
            if ($status === 'SUBMITTED' && $makerId !== $userId) {
                $pending[] = $item;
            } elseif ($makerId === $userId && in_array($status, ['DRAFT', 'SUBMITTED'], true)) {
                $submitted[] = $item;
            } elseif (in_array($status, ['APPROVED', 'REJECTED', 'CONVERTED', 'CANCELLED'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  list<array<string, int|string>>  $pending
     * @param  list<array<string, int|string>>  $submitted
     * @param  list<array<string, int|string>>  $history
     */
    private function collectProjectTimesheets(array &$pending, array &$submitted, array &$history, int $companyId, int $userId): void
    {
        $rows = $this->db->table('project_timesheets as timesheet')->join('projects as project', 'project.id', '=', 'timesheet.project_id')->where('project.company_id', $companyId)->orderByDesc('timesheet.updated_at')->limit(100)->get(['timesheet.*', 'project.code as project_code']);
        foreach ($rows as $row) {
            $values = $this->values($row);
            $status = $this->text($values['status'] ?? null);
            $makerId = $this->integer($values['created_by'] ?? null);
            $projectId = $this->integer($values['project_id'] ?? null);
            $item = $this->item('Project Timesheet', $values, 'project_code', 'created_by', 'approved_by', 'admin.projects.show', ['project' => (string) $projectId, 'company_id' => (string) $companyId]);
            $item['reference'] = $this->text($item['reference']).' / TS-'.$this->integer($values['id'] ?? null);
            if ($status === 'SUBMITTED' && $makerId !== $userId) {
                $pending[] = $item;
            } elseif ($makerId === $userId && in_array($status, ['DRAFT', 'SUBMITTED'], true)) {
                $submitted[] = $item;
            } elseif (in_array($status, ['APPROVED', 'REJECTED'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  list<array<string,int|string>>  $pending
     * @param  list<array<string,int|string>>  $submitted
     * @param  list<array<string,int|string>>  $history
     * @param  array<string,string>  $parameters
     */
    private function collect(array &$pending, array &$submitted, array &$history, int $companyId, int $userId, string $table, string $type, string $maker, string $approver, string $reference, string $route, array $parameters): void
    {
        foreach ($this->db->table($table)->where('company_id', $companyId)->orderByDesc('updated_at')->limit(100)->get() as $row) {
            $values = $this->values($row);
            $status = strtolower($this->text($values['status'] ?? null));
            $makerId = $this->integer($values[$maker] ?? null);
            $item = $this->item($type, $values, $reference, $maker, $approver, $route, $parameters + ['company_id' => (string) $companyId]);
            if (in_array($status, ['submitted', 'pending'], true) && $makerId !== $userId) {
                $pending[] = $item;
            }
            if ($makerId === $userId && in_array($status, ['draft', 'submitted', 'pending'], true)) {
                $submitted[] = $item;
            }
            if (in_array($status, ['approved', 'rejected', 'paid', 'closed'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  list<array<string,int|string>>  $pending
     * @param  list<array<string,int|string>>  $submitted
     * @param  list<array<string,int|string>>  $history
     */
    private function collectPayroll(array &$pending, array &$submitted, array &$history, int $companyId, int $userId): void
    {
        foreach ($this->db->table('payroll_runs')->where('company_id', $companyId)->orderByDesc('updated_at')->limit(100)->get() as $row) {
            $values = $this->values($row);
            $status = strtolower($this->text($values['status'] ?? null));
            $makerId = $this->integer($values['created_by'] ?? null);
            $item = $this->item('Payroll Run', $values, 'code', 'created_by', 'approved_by', 'admin.payroll.runs.show', ['run' => (string) $this->integer($values['id'] ?? null), 'company_id' => (string) $companyId]);
            if ($status === 'submitted' && $makerId !== $userId) {
                $pending[] = $item;
            } elseif ($makerId === $userId && in_array($status, ['calculated', 'submitted'], true)) {
                $submitted[] = $item;
            } elseif (in_array($status, ['approved', 'rejected', 'finalized'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  list<array<string,int|string>>  $pending
     * @param  list<array<string,int|string>>  $history
     */
    private function collectWorkflowTasks(array &$pending, array &$history, int $companyId, int $userId): void
    {
        $rows = $this->db->table('workflow_tasks as task')
            ->join('workflow_instances as instance', 'instance.id', '=', 'task.workflow_instance_id')
            ->where('task.company_id', $companyId)
            ->where('task.assigned_user_id', $userId)
            ->orderByDesc('task.updated_at')
            ->limit(100)
            ->get(['task.*', 'instance.workflow_type', 'instance.started_by']);
        foreach ($rows as $row) {
            $values = $this->values($row);
            $status = strtolower($this->text($values['status'] ?? null));
            $values['reference'] = $this->text($values['workflow_type'] ?? null).' / TASK-'.$this->integer($values['id'] ?? null);
            $values['approved_by'] = $values['claimed_by'] ?? null;
            $item = $this->item('Workflow Task', $values, 'reference', 'started_by', 'approved_by', 'admin.workflows.instances.show', ['instance' => (string) $this->integer($values['workflow_instance_id'] ?? null), 'company_id' => (string) $companyId]);
            if (in_array($status, ['pending', 'claimed', 'escalated'], true)) {
                $pending[] = $item;
            } elseif (in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
                $history[] = $item;
            }
        }
    }

    /**
     * @param  array<string,mixed>  $values
     * @param  array<string,string>  $parameters
     * @return array<string,int|string>
     */
    private function item(string $type, array $values, string $reference, string $maker, string $approver, string $route, array $parameters): array
    {
        $updatedAt = $this->text($values['updated_at'] ?? null);
        $approverId = $values[$approver] ?? null;

        return [
            'type' => $type,
            'id' => $this->integer($values['id'] ?? null),
            'reference' => $this->reference($values[$reference] ?? null),
            'status' => $this->text($values['status'] ?? null),
            'maker_id' => $this->integer($values[$maker] ?? null),
            'approver_id' => is_numeric($approverId) ? (int) $approverId : 0,
            'maker' => 'User #'.$this->integer($values[$maker] ?? null),
            'approver' => is_numeric($approverId) ? 'User #'.(int) $approverId : '—',
            'age_days' => (int) max(0, CarbonImmutable::parse($updatedAt)->startOfDay()->diffInDays(now()->startOfDay())),
            'route' => $route,
            'parameters' => http_build_query($parameters),
            'url' => route($route, $parameters),
            'updated_at' => $updatedAt,
        ];
    }

    /** @return array<string,mixed> */
    private function values(object $row): array
    {
        $values = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid approval integer.'));
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid approval text.');
    }

    private function reference(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : (is_int($value) ? (string) $value : throw new \RuntimeException('Invalid approval reference.'));
    }
}
