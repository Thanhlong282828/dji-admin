<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use App\Services\WorkforceNotificationDeliveryService;
use App\Services\WorkforceNotificationRecipientResolver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class AccountingAdjustmentService
{
    public function __construct(private ConnectionInterface $db, private WorkforceNotificationRecipientResolver $recipients, private WorkforceNotificationDeliveryService $delivery) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        return [
            'requests' => $this->db->table('accounting_adjustment_requests as r')->join('payroll_accounting_journals as j', 'j.id', '=', 'r.original_journal_id')->join('accounting_periods as p', 'p.id', '=', 'r.target_accounting_period_id')->leftJoin('users as maker', 'maker.id', '=', 'r.requested_by')->leftJoin('users as checker', 'checker.id', '=', 'r.approved_by')->where('r.company_id', $companyId)->select(['r.*', 'j.journal_number as original_journal_number', 'j.total_debit_minor as original_debit', 'j.total_credit_minor as original_credit', 'p.year as target_year', 'p.month as target_month', 'p.status as target_period_status', 'maker.name as requester_name', 'checker.name as approver_name'])->orderByDesc('r.id')->limit(100)->get(),
            'journals' => $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereIn('status', ['internally_posted', 'exported'])->orderByDesc('posting_date')->limit(100)->get(),
            'periods' => $this->db->table('accounting_periods')->where(['company_id' => $companyId, 'status' => 'open'])->orderByDesc('start_date')->get(),
        ];
    }

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data): int
    {
        $id = match ($action) {
            'create' => $this->create($companyId, $actor, $data),
            'submit' => $this->transition($companyId, $actor, $data, 'draft', 'submitted'),
            'approve' => $this->approve($companyId, $actor, $data, true),
            'reject' => $this->approve($companyId, $actor, $data, false),
            'post' => $this->post($companyId, $actor, $data),
            default => throw ValidationException::withMessages(['action' => 'ACCOUNTING_ADJUSTMENT_ACTION_INVALID']),
        };
        $this->notify($companyId, $actor, $id, $action);

        return $id;
    }

    private function notify(int $companyId, User $actor, int $entityId, string $action): void
    {
        $payload = ['company_id' => $companyId, 'entity_type' => 'accounting_adjustment', 'entity_id' => $entityId, 'employee_id' => null, 'action' => $action, 'safe_title' => 'Cập nhật accounting adjustment', 'safe_message' => 'Một yêu cầu điều chỉnh kế toán cần được theo dõi.', 'version' => 1, 'timestamp' => now()->toIso8601String(), 'url' => '/admin/finance/accounting-adjustments?company_id='.$companyId];
        foreach ($this->recipients->approvers($companyId, 0, 'accounting.adjustment.approve') as $recipient) {
            $this->delivery->deliver($recipient, 'accounting_adjustment', $payload);
        }
    }

    /** @param array<string,mixed> $data */
    private function create(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $journal = $this->postedJournal($companyId, $this->int($data, 'original_journal_id'));
            $period = $this->period($companyId, $this->int($data, 'target_accounting_period_id'));
            if ($this->textField($period, 'status') !== 'open') {
                throw ValidationException::withMessages(['target_accounting_period_id' => 'ACCOUNTING_PERIOD_CLOSED']);
            }$checksum = $this->checksum($this->intField($journal, 'id'));

            return (int) $this->db->table('accounting_adjustment_requests')->insertGetId(['company_id' => $companyId, 'original_journal_id' => $this->intField($journal, 'id'), 'target_accounting_period_id' => $this->intField($period, 'id'), 'reference' => $this->text($data, 'reference'), 'adjustment_type' => 'reversal', 'reason' => $this->text($data, 'reason'), 'status' => 'draft', 'original_checksum' => $checksum, 'requested_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function transition(int $companyId, User $actor, array $data, string $from, string $to): int
    {
        return $this->db->transaction(function () use ($companyId, $data, $from, $to): int {
            $row = $this->row($companyId, $this->int($data, 'id'));
            if ($this->textField($row, 'status') === $to) {
                return $this->intField($row, 'id');
            }if ($this->textField($row, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'ACCOUNTING_ADJUSTMENT_TRANSITION_INVALID']);
            }$this->db->table('accounting_adjustment_requests')->where('id', $this->intField($row, 'id'))->update(['status' => $to, 'submitted_at' => $to === 'submitted' ? now() : data_get($row, 'submitted_at'), 'updated_at' => now()]);

            return $this->intField($row, 'id');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function approve(int $companyId, User $actor, array $data, bool $approved): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data, $approved): int {
            $row = $this->row($companyId, $this->int($data, 'id'));
            if ($this->textField($row, 'status') === ($approved ? 'approved' : 'rejected')) {
                return $this->intField($row, 'id');
            }if ($this->textField($row, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'ACCOUNTING_ADJUSTMENT_TRANSITION_INVALID']);
            }if ($this->intField($row, 'requested_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }$this->db->table('accounting_adjustment_requests')->where('id', $this->intField($row, 'id'))->update(['status' => $approved ? 'approved' : 'rejected', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $this->intField($row, 'id');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function post(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $row = $this->row($companyId, $this->int($data, 'id'));
            $resultingJournalId = $this->nullableIntField($row, 'resulting_journal_id');
            if ($this->textField($row, 'status') === 'posted' && $resultingJournalId !== null) {
                return $resultingJournalId;
            }if ($this->textField($row, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'ACCOUNTING_ADJUSTMENT_NOT_APPROVED']);
            }$period = $this->period($companyId, $this->intField($row, 'target_accounting_period_id'));
            if ($this->textField($period, 'status') !== 'open') {
                throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_CLOSED']);
            }if (! hash_equals($this->textField($row, 'original_checksum'), $this->checksum($this->intField($row, 'original_journal_id')))) {
                throw ValidationException::withMessages(['journal' => 'ORIGINAL_JOURNAL_CHANGED']);
            }$original = $this->postedJournal($companyId, $this->intField($row, 'original_journal_id'));
            $adjustmentId = $this->intField($row, 'id');
            $key = $companyId.':accounting_adjustment:'.$adjustmentId.':reversal';
            $existing = $this->db->table('payroll_accounting_journals')->where('generic_source_key', $key)->value('id');
            if (is_numeric($existing)) {
                $journalId = (int) $existing;
            } else {
                $date = $this->textField($period, 'end_date');
                $journalId = (int) $this->db->table('payroll_accounting_journals')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'branch_id' => $this->nullableIntField($original, 'branch_id'), 'source_type' => 'accounting_adjustment', 'source_id' => $adjustmentId, 'posting_type' => 'reversal', 'generic_source_key' => $key, 'payroll_run_id' => null, 'payroll_period_id' => null, 'accounting_period_id' => $this->intField($period, 'id'), 'accounting_policy_id' => null, 'policy_version' => null, 'journal_number' => 'ADJ-'.$adjustmentId.'-'.substr(hash('sha256', $key), 0, 8), 'journal_date' => $date, 'posting_date' => $date, 'resolution_date' => $date, 'currency' => $this->textField($original, 'currency'), 'calculation_version' => 1, 'status' => 'internally_posted', 'total_debit_minor' => $this->intField($original, 'total_credit_minor'), 'total_credit_minor' => $this->intField($original, 'total_debit_minor'), 'line_count' => $this->intField($original, 'line_count'), 'active_intent_key' => null, 'idempotency_key' => (string) Str::uuid(), 'version' => 1, 'reversal_of_journal_id' => $this->intField($original, 'id'), 'created_by' => $actor->id, 'posted_by' => $actor->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($this->journalLines($this->intField($original, 'id')) as $line) {
                    $this->db->table('payroll_accounting_journal_lines')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'journal_id' => $journalId, 'line_number' => $this->intField($line, 'line_number'), 'source_type' => 'accounting_adjustment', 'source_code' => 'reversal', 'description' => 'Reversal: '.$this->textField($line, 'description'), 'account_id' => $this->intField($line, 'account_id'), 'account_code_snapshot' => $this->textField($line, 'account_code_snapshot'), 'account_name_snapshot' => $this->textField($line, 'account_name_snapshot'), 'debit_minor' => $this->intField($line, 'credit_minor'), 'credit_minor' => $this->intField($line, 'debit_minor'), 'currency' => $this->textField($line, 'currency'), 'cost_center_id' => $this->nullableIntField($line, 'cost_center_id'), 'cost_center_code_snapshot' => data_get($line, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => data_get($line, 'cost_center_name_snapshot'), 'organization_unit_id' => $this->nullableIntField($line, 'organization_unit_id'), 'organization_unit_code_snapshot' => data_get($line, 'organization_unit_code_snapshot'), 'organization_unit_name_snapshot' => data_get($line, 'organization_unit_name_snapshot'), 'mapping_id' => null, 'mapping_snapshot' => json_encode(['policy' => 'accounting_adjustment_reversal_v1'], JSON_THROW_ON_ERROR), 'source_count' => 1, 'created_at' => now(), 'updated_at' => now()]);
                }$this->db->table('payroll_accounting_journals')->where('id', $this->intField($original, 'id'))->update(['reversed_by_journal_id' => $journalId, 'updated_at' => now()]);
            }$this->db->table('accounting_adjustment_requests')->where('id', $adjustmentId)->update(['status' => 'posted', 'resulting_journal_id' => $journalId, 'posted_at' => now(), 'updated_at' => now()]);
            activity('accounting-adjustment')->causedBy($actor)->event('posted')->withProperties(['company_id' => $companyId, 'adjustment_id' => $adjustmentId, 'original_journal_id' => $this->intField($row, 'original_journal_id'), 'resulting_journal_id' => $journalId])->log('Accounting adjustment posted');

            return $journalId;
        }, 3);
    }

    private function checksum(int $journalId): string
    {
        $journal = $this->journalTotals($journalId);
        $lines = $this->db->table('payroll_accounting_journal_lines')->where('journal_id', $journalId)->orderBy('line_number')->get(['account_id', 'debit_minor', 'credit_minor', 'currency']);

        return hash('sha256', json_encode([$this->intField($journal, 'total_debit_minor'), $this->intField($journal, 'total_credit_minor'), $this->intField($journal, 'line_count'), $lines], JSON_THROW_ON_ERROR));
    }

    private function row(int $companyId, int $id): object
    {
        return $this->db->table('accounting_adjustment_requests')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    private function period(int $companyId, int $id): object
    {
        return $this->db->table('accounting_periods')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail(['id', 'status', 'end_date']);
    }

    private function postedJournal(int $companyId, int $id): object
    {
        return $this->db->table('payroll_accounting_journals')->where(['id' => $id, 'company_id' => $companyId])->whereIn('status', ['internally_posted', 'exported'])->lockForUpdate()->firstOrFail(['id', 'branch_id', 'currency', 'total_debit_minor', 'total_credit_minor', 'line_count']);
    }

    private function journalTotals(int $id): object
    {
        return $this->db->table('payroll_accounting_journals')->where('id', $id)->firstOrFail(['total_debit_minor', 'total_credit_minor', 'line_count']);
    }

    /** @return Collection<int,stdClass> */
    private function journalLines(int $journalId): Collection
    {
        return $this->db->table('payroll_accounting_journal_lines')->where('journal_id', $journalId)->orderBy('line_number')->get(['line_number', 'description', 'account_id', 'account_code_snapshot', 'account_name_snapshot', 'debit_minor', 'credit_minor', 'currency', 'cost_center_id', 'cost_center_code_snapshot', 'cost_center_name_snapshot', 'organization_unit_id', 'organization_unit_code_snapshot', 'organization_unit_name_snapshot']);
    }

    /** @param array<string,mixed> $data */
    private function int(array $data, string $key): int
    {
        $v = $data[$key] ?? null;

        return is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']));
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$key => 'Giá trị bắt buộc.']);
    }

    private function intField(object $row, string $key): int
    {
        $value = data_get($row, $key);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.']));
    }

    private function nullableIntField(object $row, string $key): ?int
    {
        $value = data_get($row, $key);

        return $value === null ? null : (is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.'])));
    }

    private function textField(object $row, string $key): string
    {
        $value = data_get($row, $key);

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.']);
    }
}
