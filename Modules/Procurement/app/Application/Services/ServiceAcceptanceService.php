<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;

final readonly class ServiceAcceptanceService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('service_acceptances')->where(['company_id' => $companyId, 'idempotency_key' => $key])->first();
            if ($existing !== null) {
                return $this->integer($existing->id ?? null);
            }
            $orderId = $this->positive($data, 'purchase_order_id');
            $order = $this->db->table('purchase_orders')->where(['id' => $orderId, 'company_id' => $companyId])
                ->whereIn('status', ['approved', 'sent', 'partially_received'])->lockForUpdate()->firstOrFail();
            $lineId = $this->positive($data, 'purchase_order_line_id');
            $line = $this->db->table('purchase_order_lines')->where(['id' => $lineId, 'purchase_order_id' => $orderId])->lockForUpdate()->firstOrFail();
            if ($this->textValue($line->procurement_classification ?? null) !== 'operating_expense' || ($line->product_variant_id ?? null) !== null) {
                throw ValidationException::withMessages(['purchase_order_line_id' => 'SERVICE_ACCEPTANCE_LINE_NOT_ELIGIBLE']);
            }
            $quantity = $this->positive($data, 'accepted_quantity');
            if ($quantity > $this->integer($line->ordered_quantity ?? null)) {
                throw ValidationException::withMessages(['accepted_quantity' => 'SERVICE_ACCEPTANCE_EXCEEDS_ORDER']);
            }
            $unit = $this->integer($line->unit_cost_minor ?? null);
            $amount = $quantity * $unit;
            $id = (int) $this->db->table('service_acceptances')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->integer($order->branch_id ?? null), 'vendor_id' => $this->integer($order->vendor_id ?? null),
                'purchase_order_id' => $orderId, 'reference' => $this->text($data, 'reference'), 'acceptance_date' => $this->date($data, 'acceptance_date'),
                'status' => 'draft', 'currency' => $this->textValue($order->currency ?? null), 'total_minor' => $amount, 'idempotency_key' => $key,
                'created_by' => $actor->id, 'note' => $this->nullableText($data, 'note'), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('service_acceptance_lines')->insert([
                'service_acceptance_id' => $id, 'purchase_order_line_id' => $lineId, 'cost_center_id' => $line->cost_center_id,
                'cost_center_code_snapshot' => $line->cost_center_code_snapshot, 'cost_center_name_snapshot' => $line->cost_center_name_snapshot,
                'procurement_classification' => 'operating_expense', 'account_id' => $this->integer($line->account_id ?? null),
                'account_code_snapshot' => $this->textValue($line->account_code_snapshot ?? null), 'account_name_snapshot' => $this->textValue($line->account_name_snapshot ?? null),
                'accepted_quantity' => $quantity, 'unit_cost_minor' => $unit, 'accepted_minor' => $amount, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $acceptanceId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $acceptanceId, $actor): int {
            $row = $this->acceptance($companyId, $acceptanceId);
            $status = $this->textValue($row->status ?? null);
            if (in_array($status, ['submitted', 'approved'], true)) {
                return $acceptanceId;
            }
            if ($status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'SERVICE_ACCEPTANCE_TRANSITION_INVALID']);
            }
            $this->db->table('service_acceptances')->where('id', $acceptanceId)->update([
                'status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now(),
            ]);

            return $acceptanceId;
        }, 3);
    }

    public function approve(int $companyId, int $acceptanceId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $acceptanceId, $actor): int {
            $acceptance = $this->acceptance($companyId, $acceptanceId);
            $status = $this->textValue($acceptance->status ?? null);
            if ($status === 'approved') {
                return $acceptanceId;
            }
            if ($status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'SERVICE_ACCEPTANCE_TRANSITION_INVALID']);
            }
            if ($this->integer($acceptance->created_by ?? null) === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'MAKER_CHECKER_REQUIRED']);
            }
            $lines = $this->db->table('service_acceptance_lines')->where('service_acceptance_id', $acceptanceId)->lockForUpdate()->get();
            $postingLines = [];
            foreach ($lines as $line) {
                $poLine = $this->db->table('purchase_order_lines')->where('id', $this->integer($line->purchase_order_line_id ?? null))->lockForUpdate()->firstOrFail();
                if ($this->textValue($poLine->procurement_classification ?? null) !== 'operating_expense') {
                    throw ValidationException::withMessages(['purchase_order_line_id' => 'SERVICE_ACCEPTANCE_LINE_NOT_ELIGIBLE']);
                }
                $approved = (int) $this->db->table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')
                    ->where('sal.purchase_order_line_id', $this->integer($line->purchase_order_line_id ?? null))->where('sa.status', 'approved')->sum('sal.accepted_quantity');
                if ($approved + $this->integer($line->accepted_quantity ?? null) > $this->integer($poLine->ordered_quantity ?? null)) {
                    throw ValidationException::withMessages(['accepted_quantity' => 'SERVICE_ACCEPTANCE_EXCEEDS_ORDER']);
                }
            }
            $date = $this->textValue($acceptance->acceptance_date ?? null);
            $currency = $this->textValue($acceptance->currency ?? null);
            $policy = $this->policies->resolve($companyId, 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', $date, $currency);
            $amount = $this->integer($acceptance->total_minor ?? null);
            $firstLine = $lines->first();
            if (! is_object($firstLine)) {
                throw ValidationException::withMessages(['lines' => 'SERVICE_ACCEPTANCE_LINES_REQUIRED']);
            }
            foreach ($policy['lines'] as $line) {
                $postingLines[] = [
                    'account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0,
                    'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => 'Service acceptance '.$line['line_role'],
                    'cost_center_id' => $line['requires_cost_center'] ? $this->nullableIntegerValue($firstLine->cost_center_id ?? null) : null,
                    'cost_center_code_snapshot' => $line['requires_cost_center'] ? $this->nullableStringValue($firstLine->cost_center_code_snapshot ?? null) : null,
                    'cost_center_name_snapshot' => $line['requires_cost_center'] ? $this->nullableStringValue($firstLine->cost_center_name_snapshot ?? null) : null,
                ];
            }
            $journalId = $this->accounting->post($companyId, $this->integer($acceptance->branch_id ?? null), 'service_acceptance', $acceptanceId, 'service_acceptance_approved', $date, $currency, $postingLines, $actor->id);
            $this->db->table('service_acceptances')->where('id', $acceptanceId)->update([
                'status' => 'approved', 'accounting_policy_id' => $policy['policy_id'], 'accounting_policy_version' => $policy['version'],
                'accounting_journal_id' => $journalId, 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now(),
            ]);

            return $acceptanceId;
        }, 3);
    }

    private function acceptance(int $companyId, int $acceptanceId): object
    {
        return $this->db->table('service_acceptances')->where(['id' => $acceptanceId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        return $this->textValue($data[$key] ?? null);
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    /** @param array<string,mixed> $data */
    private function date(array $data, string $key): string
    {
        $date = $this->text($data, $key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : throw ValidationException::withMessages([$key => 'date required']);
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid service acceptance integer.'));
    }

    private function textValue(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid service acceptance text.');
    }

    private function nullableIntegerValue(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->integer($value);
    }

    private function nullableStringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
