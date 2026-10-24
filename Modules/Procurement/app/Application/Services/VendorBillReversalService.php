<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingAdjustmentService;

final readonly class VendorBillReversalService
{
    public function __construct(private ConnectionInterface $db, private AccountingAdjustmentService $adjustments) {}

    public function request(int $companyId, int $billId, int $periodId, string $reason, string $key, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $billId, $periodId, $reason, $key, $actor): int {
            $existing = $this->db->table('vendor_bill_reversal_requests')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->intField($existing, 'vendor_bill_id') !== $billId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'VENDOR_BILL_REVERSAL_KEY_CONFLICT']);
                }

                return $this->intField($existing, 'id');
            }
            $bill = $this->db->table('vendor_bills')->where(['id' => $billId, 'company_id' => $companyId])->whereIn('status', ['approved', 'partially_paid', 'paid'])->lockForUpdate()->firstOrFail();
            if ($this->db->table('vendor_bill_reversal_requests')->where('vendor_bill_id', $billId)->exists()) {
                throw ValidationException::withMessages(['bill' => 'VENDOR_BILL_ALREADY_HAS_REVERSAL']);
            }
            $this->classification($billId);
            $this->db->table('accounting_periods')->where(['id' => $periodId, 'company_id' => $companyId, 'status' => 'open'])->lockForUpdate()->firstOrFail();
            if ($this->activeAllocatedMinor($billId, now()->toDateTimeString()) > 0) {
                throw ValidationException::withMessages(['bill' => 'VENDOR_BILL_HAS_ACTIVE_PAYMENT']);
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'VENDOR_BILL_REVERSAL_REASON_REQUIRED']);
            }
            $journalId = $this->journalId($companyId, $billId);
            $id = (int) $this->db->table('vendor_bill_reversal_requests')->insertGetId(['company_id' => $companyId, 'vendor_bill_id' => $billId, 'target_accounting_period_id' => $periodId, 'status' => 'submitted', 'reason' => trim($reason), 'idempotency_key' => $key, 'requested_by' => $actor->id, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $adjustment = $this->adjustments->mutate('create', $companyId, $actor, ['original_journal_id' => $journalId, 'target_accounting_period_id' => $periodId, 'reference' => 'VB-REV-'.$id, 'reason' => trim($reason)]);
            $this->adjustments->mutate('submit', $companyId, $actor, ['id' => $adjustment]);
            $this->db->table('vendor_bill_reversal_requests')->where('id', $id)->update(['accounting_adjustment_id' => $adjustment, 'updated_at' => now()]);
            activity('vendor-bill-reversal')->causedBy($actor)->event('requested')->withProperties(['company_id' => $companyId, 'vendor_bill_id' => $billId, 'request_id' => $id, 'accounting_adjustment_id' => $adjustment])->log('Vendor bill reversal requested');

            return $id;
        }, 3);
    }

    public function approve(int $companyId, int $requestId, string $effectiveAt, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $requestId, $effectiveAt, $actor): int {
            $request = $this->db->table('vendor_bill_reversal_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($request->status === 'reversed' && $request->reversal_journal_id !== null) {
                return $this->intField($request, 'reversal_journal_id');
            }
            if ($request->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'VENDOR_BILL_REVERSAL_TRANSITION_INVALID']);
            }
            if ($this->intField($request, 'requested_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $bill = $this->db->table('vendor_bills')->where(['id' => $this->intField($request, 'vendor_bill_id'), 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $period = $this->db->table('accounting_periods')->where(['id' => $this->intField($request, 'target_accounting_period_id'), 'company_id' => $companyId, 'status' => 'open'])->lockForUpdate()->firstOrFail();
            if ($effectiveAt < $this->textField($period, 'start_date') || $effectiveAt > $this->textField($period, 'end_date')) {
                throw ValidationException::withMessages(['effective_at' => 'REVERSAL_EFFECTIVE_DATE_OUTSIDE_TARGET_PERIOD']);
            }
            $billId = $this->intField($bill, 'id');
            if ($this->activeAllocatedMinor($billId, $effectiveAt.' 23:59:59') > 0) {
                throw ValidationException::withMessages(['bill' => 'VENDOR_BILL_HAS_ACTIVE_PAYMENT']);
            }
            $adjustmentId = $this->intField($request, 'accounting_adjustment_id');
            $this->adjustments->mutate('approve', $companyId, $actor, ['id' => $adjustmentId]);
            $journalId = $this->adjustments->mutate('post', $companyId, $actor, ['id' => $adjustmentId]);
            $this->db->table('vendor_bill_reversal_requests')->where('id', $requestId)->update(['status' => 'reversed', 'approved_by' => $actor->id, 'approved_at' => now(), 'effective_at' => $effectiveAt.' 00:00:00', 'reversal_journal_id' => $journalId, 'updated_at' => now()]);
            activity('vendor-bill-reversal')->causedBy($actor)->event('approved')->withProperties(['company_id' => $companyId, 'vendor_bill_id' => $billId, 'request_id' => $requestId])->log('Vendor bill reversal approved');
            activity('vendor-bill-reversal')->causedBy($actor)->event('reversed')->withProperties(['company_id' => $companyId, 'vendor_bill_id' => $billId, 'request_id' => $requestId, 'reversal_journal_id' => $journalId])->log('Vendor bill reversed');

            return $journalId;
        }, 3);
    }

    private function activeAllocatedMinor(int $billId, string $asOf): int
    {
        $value = $this->db->table('ap_payment_allocations as allocation')->leftJoin('ap_payment_allocation_reversals as reversal', function (JoinClause $join) use ($asOf): void {
            $join->on('reversal.ap_payment_allocation_id', '=', 'allocation.id')->where('reversal.effective_at', '<=', $asOf);
        })->where('allocation.vendor_bill_id', $billId)->selectRaw('COALESCE(SUM(allocation.allocated_minor-COALESCE(reversal.reversed_minor,0)),0) amount')->value('amount');

        return is_numeric($value) ? (int) $value : 0;
    }

    private function classification(int $billId): string
    {
        $classifications = $this->db->table('vendor_bill_lines')->where('vendor_bill_id', $billId)->distinct()->pluck('procurement_classification');
        if ($classifications->count() !== 1 || ! in_array($classifications->first(), ['inventory', 'operating_expense'], true)) {
            throw ValidationException::withMessages(['bill' => 'VENDOR_BILL_REVERSAL_CLASSIFICATION_UNSUPPORTED']);
        }
        $classification = $classifications->first();
        $invalidLineage = $classification === 'inventory'
            ? $this->db->table('vendor_bill_lines')->where('vendor_bill_id', $billId)->whereNotNull('service_acceptance_line_id')->exists()
            : $this->db->table('vendor_bill_lines')->where('vendor_bill_id', $billId)->whereNull('service_acceptance_line_id')->exists();
        if ($invalidLineage) {
            throw ValidationException::withMessages(['bill' => 'VENDOR_BILL_REVERSAL_LINEAGE_INVALID']);
        }

        return $classification;
    }

    private function journalId(int $companyId, int $billId): int
    {
        $id = $this->db->table('payroll_accounting_journals')->where(['company_id' => $companyId, 'source_type' => 'vendor_bill', 'source_id' => $billId])->whereIn('status', ['internally_posted', 'exported'])->value('id');

        return is_numeric($id) ? (int) $id : throw ValidationException::withMessages(['journal' => 'ORIGINAL_VENDOR_BILL_JOURNAL_REQUIRED']);
    }

    private function intField(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid vendor bill reversal integer.');
    }

    private function textField(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid vendor bill reversal text.');
    }
}
