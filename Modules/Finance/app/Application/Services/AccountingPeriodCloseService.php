<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class AccountingPeriodCloseService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        return [
            'periods' => $this->db->table('accounting_periods')->where('company_id', $companyId)->orderByDesc('year')->orderByDesc('month')->limit(36)->get(),
            'requests' => $this->db->table('accounting_period_close_requests as r')->join('accounting_periods as p', 'p.id', '=', 'r.accounting_period_id')->leftJoin('users as u', 'u.id', '=', 'r.requested_by')->where('r.company_id', $companyId)->select(['r.*', 'p.year', 'p.month', 'p.status as period_status', 'u.name as requester_name'])->orderByDesc('r.id')->limit(100)->get(),
        ];
    }

    public function request(int $companyId, int $periodId, string $action, User $actor, ?string $reason): int
    {
        if (! in_array($action, ['soft_close', 'close', 'reopen'], true)) {
            throw ValidationException::withMessages(['action' => 'Period close action không hợp lệ.']);
        }

        return $this->db->transaction(function () use ($companyId, $periodId, $action, $actor, $reason): int {
            $period = $this->db->table('accounting_periods')->where(['id' => $periodId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $periodData = get_object_vars($period);
            $allowed = ['open' => ['soft_close', 'close'], 'soft_closed' => ['close', 'reopen'], 'closed' => ['reopen']];
            if (! in_array($action, $allowed[$this->text($periodData['status'] ?? null)] ?? [], true)) {
                throw ValidationException::withMessages(['period' => 'Transition kỳ kế toán không hợp lệ.']);
            }
            if ($action === 'reopen' && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Reopen bắt buộc có lý do.']);
            }
            if ($this->db->table('accounting_period_close_requests')->where(['company_id' => $companyId, 'accounting_period_id' => $periodId, 'status' => 'pending'])->exists()) {
                throw ValidationException::withMessages(['period' => 'ACCOUNTING_PERIOD_CLOSE_ALREADY_PENDING']);
            }
            $id = $this->integer($this->db->table('accounting_period_close_requests')->insertGetId(['company_id' => $companyId, 'accounting_period_id' => $periodId, 'action' => $action, 'status' => 'pending', 'version' => 1, 'reason' => $reason, 'requested_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]));
            foreach ($this->checks($companyId, $period) as $key => $check) {
                $this->db->table('accounting_period_close_checks')->insert(['close_request_id' => $id, 'check_key' => $key, 'status' => $check['count'] === 0 ? 'pass' : 'blocked', 'finding_count' => $check['count'], 'summary' => $check['summary'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 3);
    }

    public function decide(int $companyId, int $requestId, bool $approve, User $actor, ?string $reason): int
    {
        return $this->db->transaction(function () use ($companyId, $requestId, $approve, $actor, $reason): int {
            $request = $this->db->table('accounting_period_close_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $requestData = get_object_vars($request);
            if ($this->text($requestData['status'] ?? null) !== 'pending') {
                return $requestId;
            }
            if ($this->integer($requestData['requested_by'] ?? null) === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'Maker không được tự quyết định close/reopen.']);
            }
            $blocked = $this->db->table('accounting_period_close_checks')->where('close_request_id', $requestId)->where('status', 'blocked')->exists();
            $action = $this->text($requestData['action'] ?? null);
            if ($approve && $action !== 'reopen' && $blocked) {
                throw ValidationException::withMessages(['checklist' => 'Không thể close khi checklist còn blocker.']);
            }
            $status = $approve ? 'approved' : 'rejected';
            $this->db->table('accounting_period_close_requests')->where('id', $requestId)->update(['status' => $status, 'version' => $this->integer($requestData['version'] ?? null) + 1, 'reason' => $reason ?? ($requestData['reason'] ?? null), 'decided_by' => $actor->id, 'decided_at' => now(), 'updated_at' => now()]);
            if ($approve) {
                $periodStatus = match ($action) {
                    'soft_close' => 'soft_closed', 'close' => 'closed', 'reopen' => 'open', default => throw new \LogicException('Unknown close action.')
                };
                $this->db->table('accounting_periods')->where(['id' => $this->integer($requestData['accounting_period_id'] ?? null), 'company_id' => $companyId])->update(['status' => $periodStatus, 'closed_by' => $periodStatus === 'open' ? null : $actor->id, 'closed_at' => $periodStatus === 'open' ? null : now(), 'updated_at' => now()]);
            }

            return $requestId;
        }, 3);
    }

    /** @return array<string,array{count:int,summary:string}> */
    private function checks(int $companyId, object $period): array
    {
        $periodData = get_object_vars($period);
        $from = $this->text($periodData['start_date'] ?? null);
        $to = $this->text($periodData['end_date'] ?? null);
        $unbalanced = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereBetween('posting_date', [$from, $to])->whereColumn('total_debit_minor', '<>', 'total_credit_minor')->count();
        $pendingExpenses = $this->db->table('finance_expenses')->where('company_id', $companyId)->whereBetween('expense_date', [$from, $to])->whereIn('status', ['submitted', 'approved'])->count();
        $pendingBills = $this->db->table('vendor_bills')->where('company_id', $companyId)->whereBetween('invoice_date', [$from, $to])->where('status', 'draft')->count();
        $unmatchedTreasury = $this->db->table('treasury_transactions')->where('company_id', $companyId)->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('status', 'recorded')->count();
        $duplicates = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->whereBetween('posting_date', [$from, $to])->whereNotNull('generic_source_key')->select('generic_source_key')->groupBy('generic_source_key')->havingRaw('COUNT(*) > 1');
        $duplicateJournals = $duplicates->exists() ? 1 : 0;

        return [
            'journal_balance' => ['count' => $unbalanced, 'summary' => 'Journal debit/credit phải cân bằng.'],
            'expense_posting' => ['count' => $pendingExpenses, 'summary' => 'Expense trong kỳ phải hoàn tất posting/payment workflow.'],
            'vendor_bill_posting' => ['count' => $pendingBills, 'summary' => 'Vendor Bill draft trong kỳ phải được xử lý.'],
            'treasury_reconciliation' => ['count' => $unmatchedTreasury, 'summary' => 'Treasury transaction trong kỳ phải reconcile.'],
            'journal_source_uniqueness' => ['count' => $duplicateJournals, 'summary' => 'Mỗi canonical source chỉ có một journal.'],
        ];
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid accounting integer.'));
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid accounting text.');
    }
}
