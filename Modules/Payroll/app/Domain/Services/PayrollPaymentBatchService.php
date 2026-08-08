<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Payroll\Domain\Events\PaymentBatchChanged;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayrollPaymentBatchService
{
    public function __construct(private ConnectionInterface $db, private SpreadsheetFormulaSanitizer $sanitizer, private PaymentFileIntegrityService $integrity) {}

    public function create(int $companyId, int $runId, int $actorId, string $idempotencyKey): int
    {
        return $this->db->transaction(function () use ($companyId, $runId, $actorId, $idempotencyKey): int {
            $existing = $this->db->table('payroll_payment_batches')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $run = $this->db->table('payroll_runs as r')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->where('r.company_id', $companyId)->where('r.id', $runId)->where('r.status', 'finalized')->lockForUpdate()->select(['r.id', 'r.payroll_period_id', 'r.calculation_version', 'r.currency_code', 'p.payment_date'])->first();
            if (! is_object($run)) {
                throw ValidationException::withMessages(['run' => 'Payment Batch chỉ tạo từ finalized Payroll Run.']);
            }
            // The finalized Run is the aggregate lock. Re-check active intent only
            // after acquiring it so concurrent requests cannot create an empty
            // second batch before the item-level unique key rejects duplicates.
            $activeBatch = $this->db->table('payroll_payment_batches')->where('company_id', $companyId)->where('payroll_run_id', $runId)->whereIn('status', ['draft', 'validated', 'reviewed', 'approved', 'released'])->lockForUpdate()->value('id');
            if (is_numeric($activeBatch)) {
                return (int) $activeBatch;
            }
            $version = $this->i($run, 'calculation_version');
            $currency = $this->s($run, 'currency_code');
            $period = $this->i($run, 'payroll_period_id');
            $items = $this->db->table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->where('calculation_version', $version)->orderBy('id')->get(['id', 'employee_id', 'employee_name_snapshot', 'currency_code', 'net_after_personal_income_tax_minor']);
            $employeeIds = $items->map(fn (object $r): int => $this->i($r, 'employee_id'))->all();
            $accounts = $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)->where('payment_method', 'bank_transfer')->where('status', 'active')->where('is_primary', true)->where('effective_from', '<=', $this->s($run, 'payment_date'))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $this->s($run, 'payment_date')))->get()->keyBy('employee_id');
            $batchNumber = 'PAY-'.$period.'-'.Str::upper(Str::random(8));
            $batch = (int) $this->db->table('payroll_payment_batches')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payroll_run_id' => $runId, 'payroll_period_id' => $period, 'batch_number' => $batchNumber, 'currency' => $currency, 'payment_date' => $this->s($run, 'payment_date'), 'status' => 'draft', 'calculation_version' => $version, 'created_by' => $actorId, 'idempotency_key' => $idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            $eligible = 0;
            $excluded = 0;
            $failed = 0;
            $total = 0;
            $sequence = 0;
            foreach ($items as $item) {
                $amount = $this->i($item, 'net_after_personal_income_tax_minor');
                $account = $accounts->get($this->i($item, 'employee_id'));
                $issue = null;
                if ($amount === 0) {
                    $issue = ['PAYMENT_AMOUNT_ZERO', 'warning', 'Zero net payment is excluded.', false];
                    $excluded++;
                } elseif ($amount < 0) {
                    $issue = ['PAYMENT_AMOUNT_NEGATIVE', 'error', 'Negative payment is blocked.', true];
                    $failed++;
                } elseif (! is_object($account)) {
                    $issue = ['PAYMENT_ACCOUNT_MISSING', 'error', 'Verified effective bank account is missing.', true];
                    $failed++;
                } elseif ($this->s($account, 'verification_status') !== 'verified') {
                    $issue = ['PAYMENT_ACCOUNT_NOT_VERIFIED', 'error', 'Payment account is not verified.', true];
                    $failed++;
                } elseif ($this->s($account, 'currency') !== $currency) {
                    $issue = ['PAYMENT_ACCOUNT_CURRENCY_MISMATCH', 'error', 'Payment account currency mismatch.', true];
                    $failed++;
                }
                if ($issue !== null) {
                    $this->issue($companyId, $batch, $this->i($item, 'id'), $this->i($item, 'employee_id'), $issue);

                    continue;
                }
                $sequence++;
                $reference = substr('P'.$period.'B'.$batch.'E'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT), 0, 80);
                $activeKey = $this->i($item, 'id').':'.$version;
                try {
                    $this->db->table('payroll_payment_batch_items')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payment_batch_id' => $batch, 'payroll_run_id' => $runId, 'payroll_item_id' => $this->i($item, 'id'), 'employee_id' => $this->i($item, 'employee_id'), 'calculation_version' => $version, 'active_intent_key' => $activeKey, 'payment_method' => 'bank_transfer', 'currency' => $currency, 'amount' => $amount, 'beneficiary_name_snapshot' => $this->s($account, 'account_holder_name'), 'bank_code_snapshot' => $this->n($account, 'bank_code'), 'bank_name_snapshot' => $this->n($account, 'bank_name'), 'branch_code_snapshot' => $this->n($account, 'branch_code'), 'account_number_ciphertext' => $this->n($account, 'account_number_encrypted'), 'account_number_last4' => $this->n($account, 'account_number_last4'), 'payment_reference' => $reference, 'status' => 'pending', 'validation_status' => 'valid', 'reconciliation_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                    $eligible++;
                    $total += $amount;
                } catch (\Throwable) {
                    $this->issue($companyId, $batch, $this->i($item, 'id'), $this->i($item, 'employee_id'), ['PAYMENT_ITEM_ALREADY_BATCHED', 'error', 'Payroll Item already has an active payment intent.', true]);
                    $failed++;
                }
            }
            $this->db->table('payroll_payment_batches')->where('id', $batch)->update(['employee_count' => $eligible, 'eligible_count' => $eligible, 'excluded_count' => $excluded, 'failed_count' => $failed, 'total_amount' => $total, 'updated_at' => now()]);
            $this->history($companyId, $batch, $actorId, 'created');
            event(new PaymentBatchChanged($companyId, $batch, 'draft', $eligible));

            return $batch;
        }, 3);
    }

    public function validate(int $companyId, int $batchId, int $actorId): void
    {
        $this->transition($companyId, $batchId, $actorId, 'draft', 'validated');
    }

    public function review(int $companyId, int $batchId, int $actorId): void
    {
        $this->transition($companyId, $batchId, $actorId, 'validated', 'reviewed', ['reviewed_by' => $actorId, 'reviewed_at' => now()]);
    }

    public function approve(int $companyId, int $batchId, int $actorId): void
    {
        $batch = $this->batch($companyId, $batchId, true);
        if ($this->s($batch, 'status') === 'approved') {
            return;
        }if ($this->i($batch, 'created_by') === $actorId) {
            throw ValidationException::withMessages(['actor' => 'Maker không được tự approve Payment Batch.']);
        }$this->transition($companyId, $batchId, $actorId, 'reviewed', 'approved', ['approved_by' => $actorId, 'approved_at' => now()]);
    }

    public function reject(int $companyId, int $batchId, int $actorId, string $reason): void
    {
        $this->db->transaction(function () use ($companyId, $batchId, $actorId, $reason): void {
            $batch = $this->batch($companyId, $batchId, true);
            if (! in_array($this->s($batch, 'status'), ['draft', 'validated', 'reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Batch không thể reject ở trạng thái hiện tại.']);
            }$this->db->table('payroll_payment_batches')->where('id', $batchId)->update(['status' => 'rejected', 'rejected_by' => $actorId, 'rejected_at' => now(), 'rejection_reason' => $reason, 'version' => $this->i($batch, 'version') + 1, 'updated_at' => now()]);
            $this->db->table('payroll_payment_batch_items')->where('payment_batch_id', $batchId)->update(['active_intent_key' => null, 'status' => 'rejected', 'updated_at' => now()]);
            $this->history($companyId, $batchId, $actorId, 'rejected');
            event(new PaymentBatchChanged($companyId, $batchId, 'rejected', $this->i($batch, 'employee_count')));
        });
    }

    public function release(int $companyId, int $batchId, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $batchId, $actorId): void {
            $batch = $this->batch($companyId, $batchId, true);
            if ($this->s($batch, 'status') === 'released') {
                return;
            }if ($this->s($batch, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Chỉ approved Batch mới được release.']);
            }$this->assertTotals($batch);
            $rows = $this->db->table('payroll_payment_batch_items')->where('company_id', $companyId)->where('payment_batch_id', $batchId)->orderBy('id')->get();
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Unable to create payment export.');
            }fputcsv($stream, ['Payment Reference', 'Beneficiary Name', 'Bank Code', 'Branch Code', 'Account Number', 'Amount Minor', 'Currency', 'Payment Date', 'Description', 'Source Type']);
            foreach ($rows as $r) {
                $cipher = $this->n($r, 'account_number_ciphertext');
                if ($cipher === null) {
                    throw ValidationException::withMessages(['file' => 'PAYMENT_FILE_INTEGRITY_FAILED']);
                }$account = Crypt::decryptString($cipher);
                fputcsv($stream, [$this->sanitizer->sanitize($this->s($r, 'payment_reference')), $this->sanitizer->sanitize($this->s($r, 'beneficiary_name_snapshot')), $this->sanitizer->sanitize($this->s($r, 'bank_code_snapshot')), $this->sanitizer->sanitize($this->s($r, 'branch_code_snapshot')), $account, (string) $this->i($r, 'amount'), $this->s($r, 'currency'), $this->s($batch, 'payment_date'), $this->s($r, 'source_type') === 'retroactive_adjustment' ? 'Retroactive Payroll Adjustment' : 'Payroll payment instruction', $this->s($r, 'source_type') ?: 'regular_payroll']);
            }rewind($stream);
            $bytes = stream_get_contents($stream);
            fclose($stream);
            if (! is_string($bytes)) {
                throw new \RuntimeException('Unable to render payment export.');
            }$path = 'payroll-payments/'.$companyId.'/'.$this->s($batch, 'uuid').'.csv';
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new \RuntimeException('Private payment storage failed.');
            }$checksum = $this->integrity->checksum($bytes);
            $this->db->table('payroll_payment_batches')->where('id', $batchId)->update(['status' => 'released', 'released_by' => $actorId, 'released_at' => now(), 'export_format' => 'generic_csv_v1_minor_units', 'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => 'payment-'.$this->s($batch, 'uuid').'.csv', 'mime_type' => 'text/csv', 'file_size' => strlen($bytes), 'checksum' => $checksum, 'version' => $this->i($batch, 'version') + 1, 'updated_at' => now()]);
            $this->db->table('payroll_payment_batch_items')->where('payment_batch_id', $batchId)->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
            $this->history($companyId, $batchId, $actorId, 'released');
            $this->access($companyId, $batchId, $actorId, 'release_file');
            event(new PaymentBatchChanged($companyId, $batchId, 'released', $this->i($batch, 'employee_count')));
        });
    }

    public function download(int $companyId, int $batchId, int $actorId): StreamedResponse
    {
        $batch = $this->batch($companyId, $batchId, false);
        if ($this->s($batch, 'status') !== 'released') {
            abort(403);
        }$path = $this->s($batch, 'storage_path');
        $bytes = Storage::disk($this->s($batch, 'storage_disk'))->get($path);
        if (! is_string($bytes) || ! $this->integrity->valid($bytes, $this->s($batch, 'checksum'))) {
            throw ValidationException::withMessages(['file' => 'PAYMENT_FILE_INTEGRITY_FAILED']);
        }$this->access($companyId, $batchId, $actorId, 'download_file');

        return Storage::disk('local')->download($path, $this->s($batch, 'file_name'), ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @param array<string, mixed> $extra */
    private function transition(int $companyId, int $batchId, int $actorId, string $from, string $to, array $extra = []): void
    {
        $this->db->transaction(function () use ($companyId, $batchId, $actorId, $from, $to, $extra): void {
            $batch = $this->batch($companyId, $batchId, true);
            if ($this->s($batch, 'status') === $to) {
                return;
            }if ($this->s($batch, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'Invalid Payment Batch transition.']);
            }if ($to === 'validated') {
                $this->assertTotals($batch);
                if ($this->db->table('payroll_payment_validation_issues')->where('payment_batch_id', $batchId)->where('is_blocking', true)->exists()) {
                    throw ValidationException::withMessages(['validation' => 'Payment Batch còn blocking validation issues.']);
                }
            }
            $values = ['status' => $to, 'version' => $this->i($batch, 'version') + 1, 'updated_at' => now()] + $extra;
            $this->db->table('payroll_payment_batches')->where('id', $batchId)->update($values);
            $this->history($companyId, $batchId, $actorId, $to);
            event(new PaymentBatchChanged($companyId, $batchId, $to, $this->i($batch, 'employee_count')));
        });
    }

    private function assertTotals(object $batch): void
    {
        $count = $this->db->table('payroll_payment_batch_items')->where('payment_batch_id', $this->i($batch, 'id'))->count();
        $sum = $this->db->table('payroll_payment_batch_items')->where('payment_batch_id', $this->i($batch, 'id'))->sum('amount');
        $total = (int) $sum;
        if ($count !== $this->i($batch, 'employee_count') || $total !== $this->i($batch, 'total_amount')) {
            throw ValidationException::withMessages(['totals' => 'PAYMENT_BATCH_TOTAL_MISMATCH']);
        }
    }

    private function batch(int $companyId, int $batchId, bool $lock): object
    {
        $q = $this->db->table('payroll_payment_batches')->where('company_id', $companyId)->where('id', $batchId);
        if ($lock) {
            $q->lockForUpdate();
        }$r = $q->first();
        if (! is_object($r)) {
            abort(404);
        }

        return $r;
    }

    /** @param array{string,string,string,bool} $issue */
    private function issue(int $companyId, int $batchId, int $itemId, int $employeeId, array $issue): void
    {
        $this->db->table('payroll_payment_validation_issues')->insert(['company_id' => $companyId, 'payment_batch_id' => $batchId, 'payroll_item_id' => $itemId, 'employee_id' => $employeeId, 'code' => $issue[0], 'severity' => $issue[1], 'message' => $issue[2], 'is_blocking' => $issue[3], 'created_at' => now(), 'updated_at' => now()]);
    }

    private function history(int $companyId, int $batchId, int $actorId, string $event): void
    {
        activity('payroll-payment')->causedBy($actorId)->event($event)->withProperties(['company_id' => $companyId, 'payment_batch_id' => $batchId])->log('Payment Batch '.$event);
    }

    private function access(int $companyId, int $batchId, int $actorId, string $action): void
    {
        $this->db->table('payroll_payment_access_logs')->insert(['company_id' => $companyId, 'payment_batch_id' => $batchId, 'actor_id' => $actorId, 'action' => $action, 'accessed_at' => now()]);
    }

    private function i(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function s(object $r, string $k): string
    {
        return $this->n($r, $k) ?? '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }
}
