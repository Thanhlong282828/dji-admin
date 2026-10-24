<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class ApPaymentReversalService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingAdjustmentService $adjustments,
        private TreasuryService $treasury,
    ) {}

    public function request(int $companyId, int $paymentId, int $periodId, string $reason, string $key, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $paymentId, $periodId, $reason, $key, $actor): int {
            $existing = $this->db->table('ap_payment_reversal_requests')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->intField($existing, 'ap_payment_id') !== $paymentId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'AP_PAYMENT_REVERSAL_KEY_CONFLICT']);
                }

                return $this->intField($existing, 'id');
            }
            $payment = $this->db->table('ap_payments')->where(['id' => $paymentId, 'company_id' => $companyId, 'status' => 'approved'])->lockForUpdate()->firstOrFail();
            if ($this->db->table('ap_payment_reversal_requests')->where('ap_payment_id', $paymentId)->exists()) {
                throw ValidationException::withMessages(['payment' => 'PAYMENT_ALREADY_HAS_REVERSAL']);
            }
            $period = $this->db->table('accounting_periods')->where(['id' => $periodId, 'company_id' => $companyId, 'status' => 'open'])->lockForUpdate()->firstOrFail();
            unset($period);
            $journalId = $this->journalId($companyId, 'ap_payment', $paymentId);
            if (! $this->db->table('treasury_transactions')->where(['company_id' => $companyId, 'source_type' => 'ap_payment', 'source_id' => $paymentId])->exists()) {
                throw ValidationException::withMessages(['treasury' => 'AP_PAYMENT_TREASURY_SOURCE_REQUIRED']);
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'AP_PAYMENT_REVERSAL_REASON_REQUIRED']);
            }
            $id = (int) $this->db->table('ap_payment_reversal_requests')->insertGetId(['company_id' => $companyId, 'ap_payment_id' => $paymentId, 'target_accounting_period_id' => $periodId, 'status' => 'submitted', 'reason' => trim($reason), 'idempotency_key' => $key, 'requested_by' => $actor->id, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $adjustment = $this->adjustments->mutate('create', $companyId, $actor, ['original_journal_id' => $journalId, 'target_accounting_period_id' => $periodId, 'reference' => 'AP-PAY-REV-'.$id, 'reason' => trim($reason)]);
            $this->adjustments->mutate('submit', $companyId, $actor, ['id' => $adjustment]);
            $this->db->table('ap_payment_reversal_requests')->where('id', $id)->update(['accounting_adjustment_id' => $adjustment, 'updated_at' => now()]);
            activity('ap-payment-reversal')->causedBy($actor)->event('requested')->withProperties(['company_id' => $companyId, 'payment_id' => $paymentId, 'request_id' => $id, 'accounting_adjustment_id' => $adjustment])->log('AP payment reversal requested');

            return $id;
        }, 3);
    }

    public function approve(int $companyId, int $requestId, string $effectiveAt, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $requestId, $effectiveAt, $actor): int {
            $request = $this->db->table('ap_payment_reversal_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($request->status === 'reversed' && $request->reversal_journal_id !== null) {
                return $this->intField($request, 'reversal_journal_id');
            }
            if ($request->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'AP_PAYMENT_REVERSAL_TRANSITION_INVALID']);
            }
            if ($this->intField($request, 'requested_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $payment = $this->db->table('ap_payments')->where(['id' => $this->intField($request, 'ap_payment_id'), 'company_id' => $companyId, 'status' => 'approved'])->lockForUpdate()->firstOrFail();
            $period = $this->db->table('accounting_periods')->where(['id' => $this->intField($request, 'target_accounting_period_id'), 'company_id' => $companyId, 'status' => 'open'])->lockForUpdate()->firstOrFail();
            if ($effectiveAt < $this->textField($period, 'start_date') || $effectiveAt > $this->textField($period, 'end_date')) {
                throw ValidationException::withMessages(['effective_at' => 'REVERSAL_EFFECTIVE_DATE_OUTSIDE_TARGET_PERIOD']);
            }
            $paymentId = $this->intField($payment, 'id');
            $allocations = $this->db->table('ap_payment_allocations')->where('payment_id', $paymentId)->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages(['allocation' => 'AP_PAYMENT_ALLOCATION_REQUIRED']);
            }
            $adjustmentId = $this->intField($request, 'accounting_adjustment_id');
            $this->adjustments->mutate('approve', $companyId, $actor, ['id' => $adjustmentId]);
            $journalId = $this->adjustments->mutate('post', $companyId, $actor, ['id' => $adjustmentId]);
            foreach ($allocations as $allocation) {
                $this->db->table('ap_payment_allocation_reversals')->insertOrIgnore(['ap_payment_allocation_id' => $this->intField($allocation, 'id'), 'payment_reversal_request_id' => $requestId, 'reversed_minor' => $this->intField($allocation, 'allocated_minor'), 'effective_at' => $effectiveAt.' 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
            }
            $originalTreasury = $this->db->table('treasury_transactions')->where(['company_id' => $companyId, 'source_type' => 'ap_payment', 'source_id' => $paymentId])->lockForUpdate()->firstOrFail();
            $treasuryId = $this->treasury->recordSource($companyId, $this->nullableIntField($originalTreasury, 'branch_id'), 'ap_payment_reversal', $requestId, $journalId, 'REV-'.$this->textField($payment, 'reference'), $this->intField($payment, 'amount_minor'), $effectiveAt.' 12:00:00', $actor->id, 'inflow');
            $this->db->table('ap_payment_reversal_requests')->where('id', $requestId)->update(['status' => 'reversed', 'approved_by' => $actor->id, 'approved_at' => now(), 'effective_at' => $effectiveAt.' 00:00:00', 'reversal_journal_id' => $journalId, 'treasury_reversal_transaction_id' => $treasuryId, 'updated_at' => now()]);
            activity('ap-payment-reversal')->causedBy($actor)->event('approved')->withProperties(['company_id' => $companyId, 'payment_id' => $paymentId, 'request_id' => $requestId])->log('AP payment reversal approved');
            activity('ap-payment-reversal')->causedBy($actor)->event('reversed')->withProperties(['company_id' => $companyId, 'payment_id' => $paymentId, 'request_id' => $requestId, 'reversal_journal_id' => $journalId])->log('AP payment reversed');

            return $journalId;
        }, 3);
    }

    private function journalId(int $companyId, string $sourceType, int $sourceId): int
    {
        $id = $this->db->table('payroll_accounting_journals')->where(['company_id' => $companyId, 'source_type' => $sourceType, 'source_id' => $sourceId])->whereIn('status', ['internally_posted', 'exported'])->value('id');

        return is_numeric($id) ? (int) $id : throw ValidationException::withMessages(['journal' => 'ORIGINAL_PAYMENT_JOURNAL_REQUIRED']);
    }

    private function intField(object $row, string $field): int
    {
        $value = data_get($row, $field);

        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid AP reversal integer.');
    }

    private function nullableIntField(object $row, string $field): ?int
    {
        $value = data_get($row, $field);

        return $value === null ? null : (is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid AP reversal nullable integer.'));
    }

    private function textField(object $row, string $field): string
    {
        $value = data_get($row, $field);

        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AP reversal text.');
    }
}
