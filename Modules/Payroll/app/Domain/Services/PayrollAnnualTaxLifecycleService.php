<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AnnualTaxLifecycleResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxAction;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus as Status;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxResultType;

final readonly class PayrollAnnualTaxLifecycleService
{
    public function __construct(private ConnectionInterface $db, private PayrollAnnualTaxValidator $validator) {}

    public function transition(int $companyId, int $id, PayrollAnnualTaxAction|string $action, int $actorId, ?string $reason = null): AnnualTaxLifecycleResult
    {
        $action = $action instanceof PayrollAnnualTaxAction ? $action->value : $action;

        return $this->db->transaction(function () use ($companyId, $id, $action, $actorId, $reason): AnnualTaxLifecycleResult {
            $row = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $id)->lockForUpdate()->first();
            if (! $row instanceof \stdClass) {
                abort(404);
            }
            $from = Status::from($this->string($row, 'status'));
            $to = match ($action) {
                'submit' => Status::Submitted,
                'review' => Status::Reviewed,
                'approve' => Status::Approved,
                'finalize' => Status::Finalized,
                'reject' => Status::Rejected,
                default => throw ValidationException::withMessages(['action' => 'Unsupported Annual Tax lifecycle action.']),
            };
            $allowed = ['submit' => Status::Validated, 'review' => Status::Submitted, 'approve' => Status::Reviewed, 'finalize' => Status::Approved];
            if ($action === 'reject') {
                if (! in_array($from, [Status::Submitted, Status::Reviewed, Status::Approved], true) || $reason === null || trim($reason) === '') {
                    throw ValidationException::withMessages(['reason' => 'A reason is required to reject a submitted Annual Tax reconciliation.']);
                }
            } elseif ($allowed[$action] !== $from) {
                throw ValidationException::withMessages(['status' => "Annual Tax {$action} is not allowed from {$from->value}."]);
            }
            if ($action !== 'reject' && ! $this->validator->validate($companyId, $id, 'pre_'.$action)->valid) {
                throw ValidationException::withMessages(['validation' => 'Blocking Annual Tax issues must be resolved first.']);
            }
            $createdBy = $this->integer($row, 'created_by');
            $reviewedBy = $this->nullableInteger($row, 'reviewed_by');
            $approvedBy = $this->nullableInteger($row, 'approved_by');
            if (($action === 'review' || $action === 'approve') && $actorId === $createdBy) {
                throw ValidationException::withMessages(['actor' => 'Creator cannot review or approve the same Annual Tax reconciliation.']);
            }
            if ($action === 'approve' && $actorId === $reviewedBy) {
                throw ValidationException::withMessages(['actor' => 'Reviewer cannot approve the same Annual Tax reconciliation.']);
            }
            if ($action === 'finalize' && $actorId === $approvedBy) {
                throw ValidationException::withMessages(['actor' => 'Approver cannot finalize the same Annual Tax reconciliation.']);
            }
            $changes = ['status' => $to->value, 'updated_at' => now()];
            if (in_array($action, ['submit', 'review', 'approve', 'finalize'], true)) {
                $prefix = ['submit' => 'submitted', 'review' => 'reviewed', 'approve' => 'approved', 'finalize' => 'finalized'][$action];
                $changes[$prefix.'_by'] = $actorId;
                $changes[$prefix.'_at'] = now();
            }
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update($changes);
            $this->history($row, $from, $to, $action, $actorId, $reason);
            if ($action === 'finalize') {
                $this->createIntents($row);
            }

            return new AnnualTaxLifecycleResult($id, $from, $to);
        }, 3);
    }

    private function history(object $row, Status $from, Status $to, string $action, int $actor, ?string $reason): void
    {
        $this->db->table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $this->integer($row, 'id'), 'company_id' => $this->integer($row, 'company_id'), 'employee_id' => $this->integer($row, 'employee_id'), 'from_status' => $from->value, 'to_status' => $to->value, 'action' => $action, 'actor_id' => $actor, 'safe_reason' => $reason === null ? null : mb_substr(strip_tags($reason), 0, 1000), 'created_at' => now()]);
    }

    private function createIntents(object $row): void
    {
        $result = PayrollAnnualTaxResultType::from($this->string($row, 'result_type'));
        $statuses = ['additional_tax_recovery' => $result === PayrollAnnualTaxResultType::Payable ? 'eligible' : 'not_applicable', 'tax_refund_payment' => $result === PayrollAnnualTaxResultType::Refund ? 'eligible' : 'not_applicable', 'accounting_journal' => 'eligible', 'annual_statement' => 'eligible'];
        foreach ($statuses as $type => $status) {
            $this->db->table('payroll_annual_tax_downstream_intents')->updateOrInsert(['annual_tax_reconciliation_id' => $this->integer($row, 'id'), 'intent_type' => $type], ['company_id' => $this->integer($row, 'company_id'), 'employee_id' => $this->integer($row, 'employee_id'), 'status' => $status, 'idempotency_key' => (string) Str::uuid(), 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    private function integer(object $row, string $key): int
    {
        return $this->nullableInteger($row, $key) ?? 0;
    }

    private function nullableInteger(object $row, string $key): ?int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
