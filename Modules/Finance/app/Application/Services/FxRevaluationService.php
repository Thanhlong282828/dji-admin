<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class FxRevaluationService
{
    public function __construct(
        private ConnectionInterface $db,
        private FxRateResolver $rates,
        private FxRateSnapshotService $snapshots,
        private FxRevaluationCalculator $calculator,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $posting,
    ) {}

    /** @param list<FxRevaluableSource> $sources */
    public function create(int $companyId, ?int $branchId, int $periodId, string $asOfDate, string $idempotencyKey, User $actor, array $sources): int
    {
        if ($sources === []) {
            throw ValidationException::withMessages(['sources' => 'NO_ELIGIBLE_FX_EXPOSURE']);
        }
        $functional = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code');
        if (! is_string($functional) || ! preg_match('/^[A-Z]{3}$/', $functional)) {
            throw ValidationException::withMessages(['company' => 'FUNCTIONAL_CURRENCY_REQUIRED']);
        }
        $period = $this->db->table('accounting_periods')->where(['id' => $periodId, 'company_id' => $companyId])->first(['start_date', 'end_date', 'status']);
        if (! $period instanceof stdClass || $period->status !== 'open' || $asOfDate < $this->text($period->start_date ?? null) || $asOfDate > $this->text($period->end_date ?? null)) {
            throw ValidationException::withMessages(['accounting_period_id' => 'FX_REVALUATION_OPEN_PERIOD_REQUIRED']);
        }

        return $this->db->transaction(function () use ($companyId, $branchId, $periodId, $asOfDate, $idempotencyKey, $actor, $sources, $functional): int {
            $existing = $this->db->table('finance_fx_revaluation_runs')->where(['company_id' => $companyId, 'idempotency_key' => $idempotencyKey])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $runId = (int) $this->db->table('finance_fx_revaluation_runs')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'accounting_period_id' => $periodId,
                'as_of_date' => $asOfDate, 'functional_currency' => $functional, 'status' => 'draft',
                'idempotency_key' => $idempotencyKey, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($sources as $source) {
                $this->assertSource($source, $companyId, $branchId, $functional);
                $duplicate = $this->db->table('finance_fx_revaluation_lines as line')
                    ->join('finance_fx_revaluation_runs as existing_run', 'existing_run.id', '=', 'line.fx_revaluation_run_id')
                    ->where(['line.company_id' => $companyId, 'line.source_type' => $source->sourceType, 'line.source_id' => $source->sourceId, 'existing_run.as_of_date' => $asOfDate])
                    ->where('existing_run.status', '<>', 'reversed')->lockForUpdate()->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['source' => 'FX_REVALUATION_ACTIVE_SOURCE_DATE_DUPLICATE']);
                }
                $rate = $this->rates->resolve($companyId, $source->transactionCurrency, $functional, $asOfDate, 'closing');
                $snapshotId = $this->snapshots->capture($companyId, $rate, 'fx_revaluation:'.$runId.':'.$source->sourceType, $source->sourceId);
                $amounts = $this->calculator->calculate($source->foreignOutstandingMinor, $source->carryingFunctionalMinor, $this->rate($rate['rate_decimal'] ?? null), $source->transactionCurrency, $source->functionalCurrency);
                $this->db->table('finance_fx_revaluation_lines')->insert([
                    'company_id' => $companyId, 'fx_revaluation_run_id' => $runId, 'fx_rate_snapshot_id' => $snapshotId,
                    'source_type' => $source->sourceType, 'source_id' => $source->sourceId, 'transaction_currency' => $source->transactionCurrency,
                    'foreign_outstanding_minor' => $source->foreignOutstandingMinor, 'carrying_functional_minor' => $source->carryingFunctionalMinor,
                    'revalued_functional_minor' => $amounts['revalued_functional_minor'], 'fx_difference_minor' => $amounts['fx_difference_minor'],
                    'mapping_snapshot' => json_encode(['recognition_snapshot_id' => $source->recognitionSnapshotId, 'recognition_checksum' => $source->recognitionChecksum, 'balance_nature' => $source->balanceNature, 'source_evidence' => $source->evidence], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $runId;
        }, 3);
    }

    public function transition(int $companyId, int $runId, string $action, User $actor): void
    {
        $this->db->transaction(function () use ($companyId, $runId, $action, $actor): void {
            $run = $this->run($companyId, $runId);
            $status = $this->text($run->status ?? null);
            if ($action === 'submit' && $status === 'draft') {
                $this->db->table('finance_fx_revaluation_runs')->where('id', $runId)->update(['status' => 'submitted', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now()]);

                return;
            }
            if ($action === 'approve' && $status === 'submitted') {
                if ($this->integer($run->created_by ?? null) === (int) $actor->id) {
                    throw ValidationException::withMessages(['actor' => 'FX_REVALUATION_MAKER_CHECKER_REQUIRED']);
                }
                $this->db->table('finance_fx_revaluation_runs')->where('id', $runId)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

                return;
            }
            throw ValidationException::withMessages(['status' => 'FX_REVALUATION_INVALID_TRANSITION']);
        }, 3);
    }

    public function post(int $companyId, int $runId, User $actor): ?int
    {
        return $this->db->transaction(function () use ($companyId, $runId, $actor): ?int {
            $run = $this->run($companyId, $runId);
            if ($run->status === 'posted') {
                return $run->accounting_journal_id === null ? null : $this->integer($run->accounting_journal_id);
            }
            if ($run->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'FX_REVALUATION_INVALID_TRANSITION']);
            }
            $difference = $this->accountingEffect($runId);
            if ($difference === 0) {
                throw ValidationException::withMessages(['accounting' => 'FX_REVALUATION_ZERO_DIFFERENCE_NO_POSTING_REQUIRED']);
            }
            $event = $difference > 0 ? 'FX_UNREALIZED_GAIN' : 'FX_UNREALIZED_LOSS';
            $amount = abs($difference);
            $date = $this->text($run->as_of_date ?? null);
            $currency = $this->text($run->functional_currency ?? null);
            $branchId = $run->branch_id === null ? null : $this->integer($run->branch_id);
            $policy = $this->policies->resolve($companyId, $event, null, $date, $currency);
            $lines = array_map(fn (array $line): array => ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0, 'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => $event.' '.$line['line_role']], $policy['lines']);
            $journal = $this->posting->postPolicyBacked($companyId, $branchId, 'fx_revaluation', $runId, strtolower($event), $date, $currency, $lines, $actor->id, $policy['policy_id'], $policy['version']);
            $this->db->table('finance_fx_revaluation_lines')->where('fx_revaluation_run_id', $runId)->update(['accounting_policy_id' => $policy['policy_id'], 'policy_version' => $policy['version'], 'updated_at' => now()]);
            $this->db->table('finance_fx_revaluation_runs')->where('id', $runId)->update(['status' => 'posted', 'accounting_journal_id' => $journal, 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now()]);

            return $journal;
        }, 3);
    }

    public function reverse(int $companyId, int $runId, User $actor): int
    {
        return $this->db->transaction(function () use ($companyId, $runId, $actor): int {
            $run = $this->run($companyId, $runId);
            if ($run->status === 'reversed' && $run->reversal_journal_id !== null) {
                return $this->integer($run->reversal_journal_id);
            }
            if ($run->status !== 'posted' || $run->accounting_journal_id === null) {
                throw ValidationException::withMessages(['status' => 'FX_REVALUATION_INVALID_TRANSITION']);
            }
            $originalJournalId = $this->integer($run->accounting_journal_id);
            $date = $this->text($run->as_of_date ?? null);
            $currency = $this->text($run->functional_currency ?? null);
            $branchId = $run->branch_id === null ? null : $this->integer($run->branch_id);
            $journal = $this->posting->reversePolicyBackedJournal($companyId, $branchId, 'fx_revaluation', $runId, 'fx_revaluation_reversal', $date, $currency, $actor->id, $originalJournalId);
            $this->db->table('finance_fx_revaluation_runs')->where('id', $runId)->update(['status' => 'reversed', 'reversal_journal_id' => $journal, 'reversed_by' => $actor->id, 'reversed_at' => now(), 'updated_at' => now()]);

            return $journal;
        }, 3);
    }

    private function run(int $companyId, int $runId): stdClass
    {
        $run = $this->db->table('finance_fx_revaluation_runs')->where(['company_id' => $companyId, 'id' => $runId])->lockForUpdate()->first();
        if (! $run instanceof stdClass) {
            throw ValidationException::withMessages(['run' => 'FX_REVALUATION_NOT_FOUND']);
        }

        return $run;
    }

    private function assertSource(FxRevaluableSource $source, int $companyId, ?int $branchId, string $functional): void
    {
        if ($source->companyId !== $companyId || $source->functionalCurrency !== $functional || ($branchId !== null && $source->branchId !== $branchId) || ! in_array($source->balanceNature, ['asset', 'liability'], true) || $source->recognitionSnapshotId < 1 || ! preg_match('/^[a-f0-9]{64}$/', $source->recognitionChecksum)) {
            throw ValidationException::withMessages(['source' => 'SOURCE_NOT_READY_FOR_REVALUATION']);
        }
    }

    private function accountingEffect(int $runId): int
    {
        return $this->db->table('finance_fx_revaluation_lines')->where('fx_revaluation_run_id', $runId)->get(['fx_difference_minor', 'mapping_snapshot'])->sum(function (object $line): int {
            $snapshot = json_decode($this->text($line->mapping_snapshot ?? null), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot) || ! isset($snapshot['balance_nature']) || ! is_string($snapshot['balance_nature']) || ! in_array($snapshot['balance_nature'], ['asset', 'liability'], true)) {
                throw ValidationException::withMessages(['source' => 'SOURCE_NOT_READY_FOR_REVALUATION']);
            }
            $difference = $this->signedInteger($line->fx_difference_minor ?? null);

            return $snapshot['balance_nature'] === 'asset' ? $difference : -$difference;
        });
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid FX integer evidence.'));
    }

    private function signedInteger(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : throw new \RuntimeException('Invalid signed FX integer evidence.'));
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid FX text evidence.');
    }

    /** @return numeric-string */
    private function rate(mixed $value): string
    {
        if (! is_string($value) || ! is_numeric($value) || ! preg_match('/^\d+(?:\.\d{1,12})?$/', $value)) {
            throw new \RuntimeException('Invalid FX rate evidence.');
        }

        return $value;
    }
}
