<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AdjustmentPaymentResult;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;
use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;

final readonly class PayrollAdjustmentPaymentService
{
    public function __construct(private ConnectionInterface $db, private PayrollDownstreamSourceInvariant $invariant) {}

    public function create(int $companyId, int $adjustmentId, int $actorId): AdjustmentPaymentResult
    {
        return $this->db->transaction(function () use ($companyId, $adjustmentId, $actorId): AdjustmentPaymentResult {
            $intent = $this->intent($companyId, $adjustmentId, true);
            $intentId = $this->i($intent, 'id');
            $existing = $this->ni($intent, 'payment_batch_item_id') ?? $this->numeric($this->db->table('payroll_payment_batch_items')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->value('id'));
            if ($existing !== null) {
                $item = $this->db->table('payroll_payment_batch_items')->where('id', $existing)->first();
                if (! is_object($item)) {
                    throw new \RuntimeException('Adjustment Payment Item reference is invalid.');
                }

                return new AdjustmentPaymentResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $this->i($item, 'payment_batch_id'), $existing, $this->i($item, 'amount'), $this->s($item, 'currency'), true);
            }
            $source = $this->db->table('payroll_adjustment_requests as a')
                ->join('payroll_adjustment_runs as ar', 'ar.adjustment_request_id', '=', 'a.id')
                ->join('payroll_periods as p', 'p.id', '=', $this->db->raw('COALESCE(a.target_payment_period_id,a.source_payroll_period_id)'))
                ->where('a.company_id', $companyId)->where('a.id', $adjustmentId)->where('a.status', 'applied')
                ->lockForUpdate()->select(['a.*', 'ar.id as adjustment_run_id', 'ar.delta_net_minor', 'ar.delta_calculation_version', 'ar.currency', 'p.id as payment_period_id', 'p.payment_date'])->first();
            if (! is_object($source)) {
                throw ValidationException::withMessages(['adjustment' => 'ADJUSTMENT_NOT_APPLIED']);
            }
            $amount = $this->i($source, 'delta_net_minor');
            if ($amount <= 0) {
                return new AdjustmentPaymentResult($intentId, PayrollAdjustmentDownstreamStatus::NotApplicable, null, null, null, $this->s($source, 'currency'), false);
            }
            $paymentDate = $this->s($source, 'payment_date');
            $account = $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->where('employee_id', $this->i($source, 'employee_id'))->where('payment_method', 'bank_transfer')->where('status', 'active')->where('is_primary', true)->where('verification_status', 'verified')->where('effective_from', '<=', $paymentDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $paymentDate))->lockForUpdate()->first();
            if (! is_object($account)) {
                throw ValidationException::withMessages(['payment' => 'PAYMENT_ACCOUNT_VERIFIED_PRIMARY_EFFECTIVE_MISSING']);
            }
            if ($this->s($account, 'currency') !== $this->s($source, 'currency')) {
                throw ValidationException::withMessages(['payment' => 'PAYMENT_ACCOUNT_CURRENCY_MISMATCH']);
            }
            $this->invariant->assertPayment(PayrollDownstreamSourceType::RetroactiveAdjustment, null, $adjustmentId, $this->i($source, 'adjustment_run_id'));
            $key = 'ADJ-PAY:'.$adjustmentId;
            $batchId = (int) $this->db->table('payroll_payment_batches')->insertGetId([
                'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payroll_run_id' => $this->i($source, 'source_payroll_run_id'),
                'payroll_period_id' => $this->i($source, 'payment_period_id'), 'batch_number' => 'ADJ-PAY-'.$adjustmentId,
                'currency' => $this->s($source, 'currency'), 'payment_date' => $paymentDate, 'status' => 'draft',
                'calculation_version' => $this->i($source, 'delta_calculation_version'), 'employee_count' => 1,
                'eligible_count' => 1, 'excluded_count' => 0, 'failed_count' => 0, 'total_amount' => $amount,
                'created_by' => $actorId, 'idempotency_key' => $this->uuidFor($key), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $itemId = (int) $this->db->table('payroll_payment_batch_items')->insertGetId([
                'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payment_batch_id' => $batchId,
                'payroll_run_id' => $this->i($source, 'source_payroll_run_id'), 'payroll_item_id' => null,
                'source_type' => PayrollDownstreamSourceType::RetroactiveAdjustment->value,
                'adjustment_request_id' => $adjustmentId, 'adjustment_run_id' => $this->i($source, 'adjustment_run_id'),
                'source_payroll_item_id' => $this->i($source, 'source_payroll_item_id'),
                'source_calculation_version' => $this->i($source, 'source_calculation_version'),
                'delta_calculation_version' => $this->i($source, 'delta_calculation_version'),
                'active_adjustment_payment_key' => $key, 'employee_id' => $this->i($source, 'employee_id'),
                'calculation_version' => $this->i($source, 'delta_calculation_version'), 'payment_method' => 'bank_transfer',
                'currency' => $this->s($source, 'currency'), 'amount' => $amount,
                'beneficiary_name_snapshot' => $this->s($account, 'account_holder_name'), 'bank_code_snapshot' => $this->n($account, 'bank_code'),
                'bank_name_snapshot' => $this->n($account, 'bank_name'), 'branch_code_snapshot' => $this->n($account, 'branch_code'),
                'account_number_ciphertext' => $this->n($account, 'account_number_encrypted'), 'account_number_last4' => $this->n($account, 'account_number_last4'),
                'payment_reference' => 'ADJ-'.$adjustmentId, 'status' => 'pending', 'validation_status' => 'valid',
                'reconciliation_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            activity('payroll-adjustment')->causedBy($actorId)->event('payment_created')->withProperties(['company_id' => $companyId, 'adjustment_id' => $adjustmentId, 'payment_batch_id' => $batchId, 'payment_item_id' => $itemId])->log('Adjustment Payment instruction created');

            return new AdjustmentPaymentResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $batchId, $itemId, $amount, $this->s($source, 'currency'), false);
        }, 3);
    }

    private function intent(int $companyId, int $adjustmentId, bool $lock): object
    {
        $q = $this->db->table('payroll_adjustment_downstream_intents')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->whereIn('intent_type', ['payment', 'payment_eligible'])->orderByRaw("intent_type = 'payment_eligible' DESC");
        if ($lock) {
            $q->lockForUpdate();
        }
        $row = $q->first();
        if (! is_object($row)) {
            throw ValidationException::withMessages(['payment' => 'PAYMENT_INTENT_MISSING']);
        }

        return $row;
    }

    private function uuidFor(string $value): string
    {
        return substr(hash('sha256', $value), 0, 8).'-'.substr(hash('sha256', $value), 8, 4).'-4'.substr(hash('sha256', $value), 13, 3).'-a'.substr(hash('sha256', $value), 17, 3).'-'.substr(hash('sha256', $value), 20, 12);
    }

    private function numeric(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function i(object $row, string $key): int
    {
        return $this->numeric($row->{$key} ?? null) ?? 0;
    }

    private function ni(object $row, string $key): ?int
    {
        return $this->numeric($row->{$key} ?? null);
    }

    private function s(object $row, string $key): string
    {
        return $this->n($row, $key) ?? '';
    }

    private function n(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }
}
