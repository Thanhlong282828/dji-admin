<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Payroll\Domain\Events\AccountingJournalChanged;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayrollAccountingJournalService
{
    public function __construct(private ConnectionInterface $db, private SpreadsheetFormulaSanitizer $sanitizer, private PaymentFileIntegrityService $integrity) {}

    public function create(int $companyId, int $runId, string $postingDate, int $actorId, string $idempotencyKey): int
    {
        return $this->db->transaction(function () use ($companyId, $runId, $postingDate, $actorId, $idempotencyKey): int {
            $existing = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $run = $this->db->table('payroll_runs as r')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->where('r.company_id', $companyId)->where('r.id', $runId)->where('r.status', 'finalized')->lockForUpdate()->select(['r.id', 'r.payroll_period_id', 'r.payroll_input_snapshot_id', 'r.calculation_version', 'r.currency_code', 'p.code as period_code', 'p.period_end'])->first();
            if (! is_object($run)) {
                throw ValidationException::withMessages(['run' => 'Accounting Journal chỉ tạo từ finalized Payroll Run.']);
            }
            $version = $this->i($run, 'calculation_version');
            $intent = $runId.':'.$version;
            $active = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('active_intent_key', $intent)->whereIn('status', ['draft', 'validated', 'reviewed', 'approved', 'internally_posted', 'exported'])->lockForUpdate()->value('id');
            if (is_numeric($active)) {
                return (int) $active;
            }
            $resolutionDate = $this->s($run, 'period_end');
            $currency = $this->s($run, 'currency_code');
            $policies = $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('status', 'active')->where('currency', $currency)->whereDate('effective_from', '<=', $resolutionDate)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $resolutionDate))->orderByDesc('version')->get();
            if ($policies->count() !== 1) {
                throw ValidationException::withMessages(['policy' => $policies->isEmpty() ? 'ACCOUNTING_POLICY_MISSING' : 'ACCOUNTING_POLICY_OVERLAP']);
            }
            $policy = $policies->first();
            if (! is_object($policy)) {
                throw new \LogicException('Policy resolution failed.');
            }
            $mappings = $this->db->table('payroll_accounting_mappings as m')->leftJoin('accounting_accounts as d', 'd.id', '=', 'm.debit_account_id')->leftJoin('accounting_accounts as c', 'c.id', '=', 'm.credit_account_id')->where('m.company_id', $companyId)->where('m.accounting_policy_id', $this->i($policy, 'id'))->where('m.status', 'active')->whereDate('m.effective_from', '<=', $resolutionDate)->where(fn (Builder $q): Builder => $q->whereNull('m.effective_to')->orWhereDate('m.effective_to', '>=', $resolutionDate))->select(['m.*', 'd.code as debit_code', 'd.name as debit_name', 'd.status as debit_status', 'd.allow_posting as debit_postable', 'd.currency as debit_currency', 'c.code as credit_code', 'c.name as credit_name', 'c.status as credit_status', 'c.allow_posting as credit_postable', 'c.currency as credit_currency'])->orderByDesc('m.priority')->get();
            $items = $this->db->table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->where('calculation_version', $version)->orderBy('id')->get(['id', 'employee_id', 'gross_amount_minor', 'insurance_employee_total_minor', 'insurance_employer_total_minor', 'personal_income_tax_minor', 'net_after_personal_income_tax_minor', 'currency_code']);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['run' => 'ACCOUNTING_SOURCE_AMOUNT_MISSING']);
            }
            $itemIds = $items->pluck('id')->map(fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)->all();
            // Current Payroll Items are replaced atomically on recalculation; scoping components
            // by their current item IDs is authoritative even when legacy salary rows retain version=1.
            $components = $this->db->table('payroll_item_components')->where('company_id', $companyId)->where('payroll_run_id', $runId)->whereIn('payroll_item_id', $itemIds)->orderBy('id')->get(['id', 'payroll_item_id', 'component_code_snapshot', 'component_name_snapshot', 'category', 'source_type', 'amount_minor']);
            $dimensions = $this->dimensions($companyId, $this->i($run, 'payroll_input_snapshot_id'));
            $journalId = (int) $this->db->table('payroll_accounting_journals')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payroll_run_id' => $runId, 'payroll_period_id' => $this->i($run, 'payroll_period_id'), 'accounting_policy_id' => $this->i($policy, 'id'), 'policy_version' => $this->i($policy, 'version'), 'journal_number' => 'JRN-'.$this->s($run, 'period_code').'-'.Str::upper(Str::random(8)), 'journal_date' => $postingDate, 'posting_date' => $postingDate, 'resolution_date' => $resolutionDate, 'currency' => $currency, 'calculation_version' => $version, 'status' => 'draft', 'active_intent_key' => $intent, 'idempotency_key' => $idempotencyKey, 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            /** @var array<string,array{mapping:object,debit:int,credit:int,description:string,source_type:string,source_code:string,dimension:array{organization_unit_id:int|null,organization_code:string|null,organization_name:string|null,cost_center_id:int|null,cost_center_code:string|null,cost_center_name:string|null},sources:list<array{item:int,component:int|null,employee:int|null,amount:int,type:string,code:string}>}> $groups */
            $groups = [];
            foreach ($items as $item) {
                $itemId = $this->i($item, 'id');
                $employeeId = $this->i($item, 'employee_id');
                $dim = $dimensions[$employeeId] ?? $this->emptyDimension();
                foreach ($components->where('payroll_item_id', $itemId) as $component) {
                    $category = $this->s($component, 'category');
                    $sourceType = $this->s($component, 'source_type');
                    $amount = $this->i($component, 'amount_minor');
                    $code = $this->s($component, 'component_code_snapshot');
                    if ($amount <= 0 || ! in_array($sourceType, ['salary_component_assignment'], true) || ! in_array($category, ['earning', 'deduction'], true)) {
                        continue;
                    }
                    $type = $category === 'earning' ? 'earning_component' : 'other_deduction';
                    $this->aggregate($groups, $mappings, $type, $code, $this->s($component, 'component_name_snapshot'), $amount, $dim, $itemId, $this->i($component, 'id'), $employeeId, $currency);
                }
                foreach ([['employee_insurance', 'EMPLOYEE_INSURANCE', 'Employee insurance payable', $this->i($item, 'insurance_employee_total_minor')], ['employer_insurance_expense', 'EMPLOYER_INSURANCE_EXPENSE', 'Employer insurance expense', $this->i($item, 'insurance_employer_total_minor')], ['employer_insurance_liability', 'EMPLOYER_INSURANCE_LIABILITY', 'Employer insurance payable', $this->i($item, 'insurance_employer_total_minor')], ['personal_income_tax', 'PERSONAL_INCOME_TAX', 'Personal income tax payable', $this->i($item, 'personal_income_tax_minor')], ['net_pay', 'NET_PAY', 'Net salary payable', $this->i($item, 'net_after_personal_income_tax_minor')]] as [$type, $code, $description, $amount]) {
                    if ($amount > 0) {
                        $this->aggregate($groups, $mappings, $type, $code, $description, $amount, $dim, $itemId, null, $employeeId, $currency);
                    }
                }
            }
            $lineNo = 0;
            $debit = 0;
            $credit = 0;
            foreach ($groups as $group) {
                $lineNo++;
                $mapping = $group['mapping'];
                $isDebit = $group['debit'] > 0;
                $accountId = $this->i($mapping, $isDebit ? 'debit_account_id' : 'credit_account_id');
                $lineId = (int) $this->db->table('payroll_accounting_journal_lines')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'journal_id' => $journalId, 'line_number' => $lineNo, 'source_type' => $group['source_type'], 'source_code' => $group['source_code'], 'description' => $group['description'], 'account_id' => $accountId, 'account_code_snapshot' => $this->s($mapping, $isDebit ? 'debit_code' : 'credit_code'), 'account_name_snapshot' => $this->s($mapping, $isDebit ? 'debit_name' : 'credit_name'), 'debit_minor' => $group['debit'], 'credit_minor' => $group['credit'], 'currency' => $currency, 'cost_center_id' => $group['dimension']['cost_center_id'], 'cost_center_code_snapshot' => $group['dimension']['cost_center_code'], 'cost_center_name_snapshot' => $group['dimension']['cost_center_name'], 'organization_unit_id' => $group['dimension']['organization_unit_id'], 'organization_unit_code_snapshot' => $group['dimension']['organization_code'], 'organization_unit_name_snapshot' => $group['dimension']['organization_name'], 'mapping_id' => $this->i($mapping, 'id'), 'mapping_snapshot' => json_encode(['mapping_id' => $this->i($mapping, 'id'), 'priority' => $this->i($mapping, 'priority'), 'resolved_at' => $resolutionDate], JSON_THROW_ON_ERROR), 'source_count' => count($group['sources']), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($group['sources'] as $source) {
                    $this->db->table('payroll_accounting_journal_sources')->insert(['company_id' => $companyId, 'journal_id' => $journalId, 'journal_line_id' => $lineId, 'payroll_item_id' => $source['item'], 'payroll_item_component_id' => $source['component'], 'employee_id' => $source['employee'], 'calculation_version' => $version, 'source_type' => $source['type'], 'source_code' => $source['code'], 'source_amount_minor' => $source['amount'], 'created_at' => now()]);
                }
                $debit += $group['debit'];
                $credit += $group['credit'];
            }
            if ($debit !== $credit || $lineNo === 0) {
                $this->issue($companyId, $journalId, 'ACCOUNTING_DEBIT_CREDIT_IMBALANCE', 'Debit và Credit không cân bằng.', true);
            }
            $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['total_debit_minor' => $debit, 'total_credit_minor' => $credit, 'line_count' => $lineNo, 'updated_at' => now()]);
            $this->activity($actorId, 'created', $companyId, $journalId);
            event(new AccountingJournalChanged($companyId, $journalId, 'draft', $lineNo, $debit === $credit));

            return $journalId;
        }, 3);
    }

    public function validate(int $companyId, int $journalId, int $actorId): void
    {
        $this->transition($companyId, $journalId, $actorId, 'draft', 'validated');
    }

    public function review(int $companyId, int $journalId, int $actorId): void
    {
        $this->transition($companyId, $journalId, $actorId, 'validated', 'reviewed', ['reviewed_by' => $actorId, 'reviewed_at' => now()]);
    }

    public function approve(int $companyId, int $journalId, int $actorId): void
    {
        $j = $this->journal($companyId, $journalId, true);
        if ($this->s($j, 'status') === 'approved') {
            return;
        } if ($this->i($j, 'created_by') === $actorId) {
            throw ValidationException::withMessages(['actor' => 'Maker không được tự approve Accounting Journal.']);
        } $this->transition($companyId, $journalId, $actorId, 'reviewed', 'approved', ['approved_by' => $actorId, 'approved_at' => now()]);
    }

    public function post(int $companyId, int $journalId, int $actorId): void
    {
        $this->transition($companyId, $journalId, $actorId, 'approved', 'internally_posted', ['posted_by' => $actorId, 'posted_at' => now()]);
    }

    public function reject(int $companyId, int $journalId, int $actorId, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Lý do reject là bắt buộc.']);
        }
        $this->db->transaction(function () use ($companyId, $journalId, $actorId, $reason): void {
            $j = $this->journal($companyId, $journalId, true);
            if (! in_array($this->s($j, 'status'), ['draft', 'validated', 'reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Journal không thể reject ở trạng thái hiện tại.']);
            } $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['status' => 'rejected', 'active_intent_key' => null, 'rejected_by' => $actorId, 'rejected_at' => now(), 'rejection_reason' => $reason, 'version' => $this->i($j, 'version') + 1, 'updated_at' => now()]);
            $this->activity($actorId, 'rejected', $companyId, $journalId);
            event(new AccountingJournalChanged($companyId, $journalId, 'rejected', $this->i($j, 'line_count'), true));
        });
    }

    public function export(int $companyId, int $journalId, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $journalId, $actorId): void {
            $j = $this->journal($companyId, $journalId, true);
            if ($this->s($j, 'status') === 'exported') {
                return;
            } if ($this->s($j, 'status') !== 'internally_posted') {
                throw ValidationException::withMessages(['status' => 'Chỉ internally posted Journal mới được export.']);
            } $this->assertBalanced($j);
            $lines = $this->db->table('payroll_accounting_journal_lines')->where('company_id', $companyId)->where('journal_id', $journalId)->orderBy('line_number')->get();
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Unable to create accounting CSV.');
            } fputcsv($stream, ['Journal Number', 'Posting Date', 'Line Number', 'Account Code', 'Account Name', 'Description', 'Debit Minor', 'Credit Minor', 'Currency', 'Cost Center', 'Organization Unit', 'Source Code', 'Source Count']);
            foreach ($lines as $line) {
                fputcsv($stream, [$this->sanitizer->sanitize($this->s($j, 'journal_number')), $this->s($j, 'posting_date'), (string) $this->i($line, 'line_number'), $this->sanitizer->sanitize($this->s($line, 'account_code_snapshot')), $this->sanitizer->sanitize($this->s($line, 'account_name_snapshot')), $this->sanitizer->sanitize($this->s($line, 'description')), (string) $this->i($line, 'debit_minor'), (string) $this->i($line, 'credit_minor'), $this->s($line, 'currency'), $this->sanitizer->sanitize($this->s($line, 'cost_center_code_snapshot')), $this->sanitizer->sanitize($this->s($line, 'organization_unit_code_snapshot')), $this->sanitizer->sanitize($this->s($line, 'source_code')), (string) $this->i($line, 'source_count')]);
            } rewind($stream);
            $bytes = stream_get_contents($stream);
            fclose($stream);
            if (! is_string($bytes)) {
                throw new \RuntimeException('Unable to render accounting CSV.');
            } $path = 'payroll-accounting/'.$companyId.'/'.$this->s($j, 'uuid').'.csv';
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new \RuntimeException('Private accounting storage failed.');
            } $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['status' => 'exported', 'export_format' => 'generic_payroll_journal_csv_v1', 'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => 'payroll-journal-'.$this->s($j, 'uuid').'.csv', 'mime_type' => 'text/csv', 'file_size' => strlen($bytes), 'checksum' => $this->integrity->checksum($bytes), 'exported_by' => $actorId, 'exported_at' => now(), 'version' => $this->i($j, 'version') + 1, 'updated_at' => now()]);
            $this->access($companyId, $journalId, $actorId, 'export');
            $this->activity($actorId, 'exported', $companyId, $journalId);
            event(new AccountingJournalChanged($companyId, $journalId, 'exported', $this->i($j, 'line_count'), true));
        });
    }

    public function download(int $companyId, int $journalId, int $actorId): StreamedResponse
    {
        $j = $this->journal($companyId, $journalId, false);
        if ($this->s($j, 'status') !== 'exported') {
            abort(403);
        } $bytes = Storage::disk($this->s($j, 'storage_disk'))->get($this->s($j, 'storage_path'));
        if (! is_string($bytes) || ! $this->integrity->valid($bytes, $this->s($j, 'checksum'))) {
            throw ValidationException::withMessages(['file' => 'ACCOUNTING_EXPORT_INTEGRITY_FAILED']);
        } $this->access($companyId, $journalId, $actorId, 'download');

        return Storage::disk('local')->download($this->s($j, 'storage_path'), $this->s($j, 'file_name'), ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function reverse(int $companyId, int $journalId, int $actorId, string $reason, string $idempotencyKey): int
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Lý do reversal là bắt buộc.']);
        }

        return $this->db->transaction(function () use ($companyId, $journalId, $actorId, $reason, $idempotencyKey): int {
            $original = $this->journal($companyId, $journalId, true);
            if (! in_array($this->s($original, 'status'), ['internally_posted', 'exported'], true)) {
                throw ValidationException::withMessages(['status' => 'Chỉ internally posted/exported Journal được reverse.']);
            } $existing = $original->reversed_by_journal_id ?? null;
            if (is_numeric($existing)) {
                return (int) $existing;
            } $reversal = (int) $this->db->table('payroll_accounting_journals')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payroll_run_id' => $this->i($original, 'payroll_run_id'), 'payroll_period_id' => $this->i($original, 'payroll_period_id'), 'accounting_policy_id' => $this->i($original, 'accounting_policy_id'), 'policy_version' => $this->i($original, 'policy_version'), 'journal_number' => 'REV-'.$this->s($original, 'journal_number'), 'journal_date' => now()->toDateString(), 'posting_date' => now()->toDateString(), 'resolution_date' => $this->s($original, 'resolution_date'), 'currency' => $this->s($original, 'currency'), 'calculation_version' => $this->i($original, 'calculation_version'), 'status' => 'draft', 'total_debit_minor' => $this->i($original, 'total_credit_minor'), 'total_credit_minor' => $this->i($original, 'total_debit_minor'), 'line_count' => $this->i($original, 'line_count'), 'active_intent_key' => 'REV:'.$journalId, 'idempotency_key' => $idempotencyKey, 'created_by' => $actorId, 'reversal_of_journal_id' => $journalId, 'reversal_reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            $lines = $this->db->table('payroll_accounting_journal_lines')->where('journal_id', $journalId)->orderBy('line_number')->get();
            foreach ($lines as $line) {
                $values = $this->objectValues($line);
                unset($values['id']);
                $values['uuid'] = (string) Str::uuid();
                $values['journal_id'] = $reversal;
                $values['debit_minor'] = $this->i($line, 'credit_minor');
                $values['credit_minor'] = $this->i($line, 'debit_minor');
                $values['description'] = 'Reversal: '.$this->s($line, 'description');
                $values['created_at'] = now();
                $values['updated_at'] = now();
                $newLine = (int) $this->db->table('payroll_accounting_journal_lines')->insertGetId($values);
                foreach ($this->db->table('payroll_accounting_journal_sources')->where('journal_line_id', $this->i($line, 'id'))->get() as $source) {
                    $sv = (array) $source;
                    unset($sv['id']);
                    $sv['journal_id'] = $reversal;
                    $sv['journal_line_id'] = $newLine;
                    $sv['created_at'] = now();
                    $this->db->table('payroll_accounting_journal_sources')->insert($sv);
                }
            } $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['reversed_by_journal_id' => $reversal, 'updated_at' => now()]);
            $this->activity($actorId, 'reversal_created', $companyId, $reversal);
            event(new AccountingJournalChanged($companyId, $reversal, 'draft', $this->i($original, 'line_count'), true));

            return $reversal;
        }, 3);
    }

    /**
     * @param  array<string,array{mapping:object,debit:int,credit:int,description:string,source_type:string,source_code:string,dimension:array{organization_unit_id:int|null,organization_code:string|null,organization_name:string|null,cost_center_id:int|null,cost_center_code:string|null,cost_center_name:string|null},sources:list<array{item:int,component:int|null,employee:int|null,amount:int,type:string,code:string}>}>  $groups
     * @param  Collection<int,\stdClass>  $mappings
     * @param  array{organization_unit_id:int|null,organization_code:string|null,organization_name:string|null,cost_center_id:int|null,cost_center_code:string|null,cost_center_name:string|null}  $dimension
     */
    private function aggregate(array &$groups, $mappings, string $type, string $code, string $description, int $amount, array $dimension, int $itemId, ?int $componentId, int $employeeId, string $currency): void
    {
        $candidates = $mappings->filter(fn (object $m): bool => $this->s($m, 'source_type') === $type && $this->s($m, 'source_code') === $code)->sortByDesc(function (object $m) use ($dimension): int {
            $cost = $this->ni($m, 'cost_center_id');
            $organization = $this->ni($m, 'organization_unit_id');
            $specificity = match (true) {
                $cost === $dimension['cost_center_id'] && $cost !== null && $organization === $dimension['organization_unit_id'] && $organization !== null => 4,
                $cost === $dimension['cost_center_id'] && $cost !== null && $organization === null => 3,
                $cost === null && $organization === $dimension['organization_unit_id'] && $organization !== null => 2,
                $cost === null && $organization === null => 1,
                default => 0,
            };

            return ($specificity * 100_000) + $this->i($m, 'priority');
        });
        $mapping = $candidates->first(fn (object $m): bool => ($this->ni($m, 'cost_center_id') === null || $this->ni($m, 'cost_center_id') === $dimension['cost_center_id']) && ($this->ni($m, 'organization_unit_id') === null || $this->ni($m, 'organization_unit_id') === $dimension['organization_unit_id']));
        if (! is_object($mapping)) {
            throw ValidationException::withMessages(['mapping' => 'ACCOUNTING_MAPPING_MISSING: '.$type.'/'.$code]);
        }
        $isDebit = $this->ni($mapping, 'debit_account_id') !== null;
        $accountStatus = $this->s($mapping, $isDebit ? 'debit_status' : 'credit_status');
        $postable = $this->i($mapping, $isDebit ? 'debit_postable' : 'credit_postable') === 1;
        $accountCurrency = $this->n($mapping, $isDebit ? 'debit_currency' : 'credit_currency');
        if ($accountStatus !== 'active' || ! $postable) {
            throw ValidationException::withMessages(['mapping' => 'ACCOUNTING_ACCOUNT_INACTIVE']);
        } if ($accountCurrency !== null && $accountCurrency !== $currency) {
            throw ValidationException::withMessages(['mapping' => 'ACCOUNTING_CURRENCY_MISMATCH']);
        }
        $key = implode('|', [$this->i($mapping, 'id'), $isDebit ? 'D' : 'C', (string) ($dimension['cost_center_id'] ?? 0), (string) ($dimension['organization_unit_id'] ?? 0), $code]);
        if (! isset($groups[$key])) {
            $groups[$key] = ['mapping' => $mapping, 'debit' => 0, 'credit' => 0, 'description' => $description, 'source_type' => $type, 'source_code' => $code, 'dimension' => $dimension, 'sources' => []];
        }
        $groups[$key][$isDebit ? 'debit' : 'credit'] += $amount;
        $groups[$key]['sources'][] = ['item' => $itemId, 'component' => $componentId, 'employee' => $employeeId, 'amount' => $amount, 'type' => $type, 'code' => $code];
    }

    /** @return array<int,array{organization_unit_id:int|null,organization_code:string|null,organization_name:string|null,cost_center_id:int|null,cost_center_code:string|null,cost_center_name:string|null}> */
    private function dimensions(int $companyId, int $snapshotId): array
    {
        $rows = $this->db->table('payroll_employee_inputs as i')->leftJoin('organization_units as o', 'o.id', '=', 'i.organization_unit_id')->leftJoin('cost_centers as c', 'c.id', '=', 'o.cost_center_id')->where('i.company_id', $companyId)->where('i.payroll_input_snapshot_id', $snapshotId)->get(['i.employee_id', 'o.id as organization_id', 'o.code as organization_code', 'o.name as organization_name', 'c.id as cost_center_id', 'c.code as cost_center_code', 'c.name as cost_center_name']);
        $result = [];
        foreach ($rows as $r) {
            $result[$this->i($r, 'employee_id')] = ['organization_unit_id' => $this->ni($r, 'organization_id'), 'organization_code' => $this->n($r, 'organization_code'), 'organization_name' => $this->n($r, 'organization_name'), 'cost_center_id' => $this->ni($r, 'cost_center_id'), 'cost_center_code' => $this->n($r, 'cost_center_code'), 'cost_center_name' => $this->n($r, 'cost_center_name')];
        }

        return $result;
    }

    /** @return array{organization_unit_id:null,organization_code:null,organization_name:null,cost_center_id:null,cost_center_code:null,cost_center_name:null} */
    private function emptyDimension(): array
    {
        return ['organization_unit_id' => null, 'organization_code' => null, 'organization_name' => null, 'cost_center_id' => null, 'cost_center_code' => null, 'cost_center_name' => null];
    }

    /** @param array<string,mixed> $extra */
    private function transition(int $companyId, int $journalId, int $actorId, string $from, string $to, array $extra = []): void
    {
        $this->db->transaction(function () use ($companyId, $journalId, $actorId, $from, $to, $extra): void {
            $j = $this->journal($companyId, $journalId, true);
            if ($this->s($j, 'status') === $to) {
                return;
            } if ($this->s($j, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'Invalid Accounting Journal transition.']);
            } $this->assertBalanced($j);
            if ($this->db->table('payroll_accounting_validation_issues')->where('journal_id', $journalId)->where('is_blocking', true)->exists()) {
                throw ValidationException::withMessages(['validation' => 'Journal còn blocking validation issues.']);
            } $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['status' => $to, 'version' => $this->i($j, 'version') + 1, 'updated_at' => now()] + $extra);
            $this->activity($actorId, $to, $companyId, $journalId);
            event(new AccountingJournalChanged($companyId, $journalId, $to, $this->i($j, 'line_count'), true));
        });
    }

    private function assertBalanced(object $j): void
    {
        $debit = (int) $this->db->table('payroll_accounting_journal_lines')->where('journal_id', $this->i($j, 'id'))->sum('debit_minor');
        $credit = (int) $this->db->table('payroll_accounting_journal_lines')->where('journal_id', $this->i($j, 'id'))->sum('credit_minor');
        if ($debit !== $credit || $debit !== $this->i($j, 'total_debit_minor') || $credit !== $this->i($j, 'total_credit_minor') || $debit <= 0) {
            throw ValidationException::withMessages(['balance' => 'ACCOUNTING_DEBIT_CREDIT_IMBALANCE']);
        }
    }

    private function journal(int $companyId, int $journalId, bool $lock): object
    {
        $q = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('id', $journalId);
        if ($lock) {
            $q->lockForUpdate();
        } $j = $q->first();
        if (! is_object($j)) {
            abort(404);
        }

        return $j;
    }

    private function issue(int $companyId, int $journalId, string $code, string $message, bool $blocking): void
    {
        $this->db->table('payroll_accounting_validation_issues')->insert(['company_id' => $companyId, 'journal_id' => $journalId, 'code' => $code, 'severity' => $blocking ? 'error' : 'warning', 'message' => $message, 'is_blocking' => $blocking, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function access(int $companyId, int $journalId, int $actorId, string $action): void
    {
        $this->db->table('payroll_accounting_access_logs')->insert(['company_id' => $companyId, 'journal_id' => $journalId, 'actor_id' => $actorId, 'action' => $action, 'accessed_at' => now()]);
    }

    private function activity(int $actorId, string $event, int $companyId, int $journalId): void
    {
        activity('payroll-accounting')->causedBy($actorId)->event($event)->withProperties(['company_id' => $companyId, 'journal_id' => $journalId])->log('Accounting Journal '.$event);
    }

    private function i(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
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

    /** @return array<string,mixed> */
    private function objectValues(object $row): array
    {
        $values = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
