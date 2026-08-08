<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Throwable;

final readonly class PayrollAdjustmentDownstreamService
{
    public function __construct(
        private ConnectionInterface $db,
        private PayrollAdjustmentPaymentService $payment,
        private PayrollAdjustmentAccountingService $accounting,
        private PayrollAdjustmentStatementService $statement,
    ) {}

    public function executeAll(int $companyId, int $adjustmentId, int $actorId): void
    {
        foreach ([PayrollAdjustmentDownstreamType::Payment, PayrollAdjustmentDownstreamType::Accounting, PayrollAdjustmentDownstreamType::Statement] as $type) {
            $this->execute($companyId, $adjustmentId, $actorId, $type);
        }
    }

    public function execute(int $companyId, int $adjustmentId, int $actorId, PayrollAdjustmentDownstreamType $type): void
    {
        $intent = $this->begin($companyId, $adjustmentId, $type);
        if ($intent === null) {
            return;
        }
        try {
            $artifact = match ($type) {
                PayrollAdjustmentDownstreamType::Payment => $this->payment->create($companyId, $adjustmentId, $actorId)->paymentBatchItemId,
                PayrollAdjustmentDownstreamType::Accounting => $this->accounting->create($companyId, $adjustmentId, $actorId)->journalId,
                PayrollAdjustmentDownstreamType::Statement => $this->statement->generate($companyId, $adjustmentId, $actorId)->statementId,
                PayrollAdjustmentDownstreamType::Recovery => null,
            };
            $status = $artifact === null && $type === PayrollAdjustmentDownstreamType::Payment ? 'not_applicable' : 'completed';
            $column = match ($type) {
                PayrollAdjustmentDownstreamType::Payment => 'payment_batch_item_id', PayrollAdjustmentDownstreamType::Accounting => 'accounting_journal_id', PayrollAdjustmentDownstreamType::Statement => 'adjustment_statement_id', PayrollAdjustmentDownstreamType::Recovery => 'recovery_id'
            };
            $this->db->table('payroll_adjustment_downstream_intents')->where('id', $intent)->update(['status' => $status, $column => $artifact, 'downstream_record_id' => $artifact, 'artifact_type' => $type->value, 'completed_at' => now(), 'failed_at' => null, 'failure_code' => null, 'failure_message' => null, 'updated_at' => now()]);
        } catch (ValidationException $exception) {
            $this->fail($intent, 'blocked', $this->safeCode($exception), 'Downstream business prerequisite is not ready.');
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($intent, 'failed', 'DOWNSTREAM_RUNTIME_FAILURE', 'Downstream processing failed and can be retried.');
        }
    }

    private function begin(int $companyId, int $adjustmentId, PayrollAdjustmentDownstreamType $type): ?int
    {
        return $this->db->transaction(function () use ($companyId, $adjustmentId, $type): ?int {
            $types = match ($type) {
                PayrollAdjustmentDownstreamType::Payment => ['payment', 'payment_eligible'], PayrollAdjustmentDownstreamType::Accounting => ['accounting', 'accounting_eligible'], default => [$type->value]
            };
            $row = $this->db->table('payroll_adjustment_downstream_intents')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->whereIn('intent_type', $types)->lockForUpdate()->first();
            if (! is_object($row)) {
                return null;
            }
            $status = $row->status ?? null;
            if (in_array($status, ['completed', 'not_applicable', 'processing'], true)) {
                return null;
            }
            $id = is_numeric($row->id ?? null) ? (int) $row->id : 0;
            $attempt = is_numeric($row->attempt_count ?? null) ? (int) $row->attempt_count : 0;
            $this->db->table('payroll_adjustment_downstream_intents')->where('id', $id)->update(['status' => 'processing', 'attempt_count' => $attempt + 1, 'last_attempt_at' => now(), 'processing_started_at' => now(), 'failed_at' => null, 'failure_code' => null, 'failure_message' => null, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function fail(int $intentId, string $status, string $code, string $message): void
    {
        $this->db->table('payroll_adjustment_downstream_intents')->where('id', $intentId)->update(['status' => $status, 'failed_at' => now(), 'failure_code' => substr($code, 0, 100), 'failure_message' => $message, 'updated_at' => now()]);
    }

    private function safeCode(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (! is_array($messages)) {
                continue;
            }
            foreach ($messages as $message) {
                if (is_string($message)) {
                    return preg_replace('/[^A-Z0-9_: -]/', '', strtoupper($message)) ?: 'DOWNSTREAM_BLOCKED';
                }
            }
        }

        return 'DOWNSTREAM_BLOCKED';
    }
}
