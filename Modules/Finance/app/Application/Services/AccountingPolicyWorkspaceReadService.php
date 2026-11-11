<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final readonly class AccountingPolicyWorkspaceReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{policies:list<array<string,mixed>>,selected_policy_id:?int,totals:array{policies:int,approved:int,used_by_journals:int}} */
    public function workspace(int $companyId, ?int $policyId, ?User $actor): array
    {
        $canViewJournals = $actor?->can('payroll.accounting.view') ?? false;
        $policies = $this->db->table('accounting_posting_policies as policy')
            ->where('policy.company_id', $companyId)
            ->when($policyId !== null, fn ($query) => $query->where('policy.id', $policyId))
            ->orderByDesc('policy.effective_from')->orderBy('policy.policy_key')->orderByDesc('policy.version')
            ->get(['policy.id', 'policy.policy_key', 'policy.version', 'policy.source_type', 'policy.business_event', 'policy.classification', 'policy.currency', 'policy.status', 'policy.effective_from', 'policy.effective_to']);
        /** @var list<int> $ids */
        $ids = array_values($policies->pluck('id')->map(fn (mixed $id): int => $this->integer($id))->all());
        /** @var Collection<int, object> $lines */
        $lines = $ids === [] ? collect() : $this->db->table('accounting_posting_policy_lines as line')
            ->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->whereIn('line.accounting_posting_policy_id', $ids)->where('account.company_id', $companyId)
            ->orderBy('line.sequence')->get(['line.accounting_posting_policy_id', 'line.sequence', 'line.entry_side', 'line.line_role', 'line.requires_cost_center', 'account.id as account_id', 'account.code as account_code', 'account.name as account_name']);
        $usage = $this->journalUsage($companyId, $ids);
        $items = $policies->map(function (object $policy) use ($lines, $usage, $canViewJournals, $companyId, $actor): array {
            $id = $this->integer($policy->id ?? null);
            /** @var Collection<int,array{id:int,number:string,source_type:string,source_id:int,posting_type:string,date:string,branch_id?:int|null}> $policyUsage */
            $policyUsage = $usage->get($id, collect());

            return [
                'id' => $id, 'key' => $this->text($policy->policy_key ?? null), 'version' => $this->integer($policy->version ?? null),
                'source_type' => $this->text($policy->source_type ?? null), 'business_event' => $this->text($policy->business_event ?? null),
                'classification' => $this->nullableText($policy->classification ?? null), 'currency' => $this->text($policy->currency ?? null),
                'status' => $this->text($policy->status ?? null), 'effective_from' => $this->text($policy->effective_from ?? null), 'effective_to' => $this->nullableText($policy->effective_to ?? null),
                'lines' => array_values($lines->where('accounting_posting_policy_id', $id)->map(fn (object $line): array => [
                    'sequence' => $this->integer($line->sequence ?? null), 'side' => $this->text($line->entry_side ?? null), 'role' => $this->text($line->line_role ?? null),
                    'account_id' => $this->integer($line->account_id ?? null), 'account' => $this->text($line->account_code ?? null).' · '.$this->text($line->account_name ?? null),
                    'requires_cost_center' => (bool) ($line->requires_cost_center ?? false),
                    'account_url' => $canViewJournals ? route('admin.payroll.accounting.accounts.index', ['company_id' => $companyId]) : null,
                ])->all()),
                'usage' => array_values($policyUsage->map(fn (array $journal): array => $journal + [
                    'url' => $canViewJournals ? route('admin.payroll.accounting.journals.show', ['journal' => $journal['id'], 'company_id' => $companyId]) : null,
                    ...$this->sourceLink($journal, $companyId, $actor),
                ])->all()),
            ];
        })->all();

        return ['policies' => array_values($items), 'selected_policy_id' => $policyId, 'totals' => ['policies' => $policies->count(), 'approved' => $policies->where('status', 'approved')->count(), 'used_by_journals' => $usage->flatten(1)->count()]];
    }

    /**
     * @param  list<int>  $policyIds
     * @return Collection<int,Collection<int,array{id:int,number:string,source_type:string,source_id:int,posting_type:string,date:string,branch_id?:int|null}>>
     */
    private function journalUsage(int $companyId, array $policyIds): Collection
    {
        if ($policyIds === []) {
            /** @var Collection<int,Collection<int,array{id:int,number:string,source_type:string,source_id:int,posting_type:string,date:string,branch_id?:int|null}>> $empty */
            $empty = collect();

            return $empty;
        }
        $rows = $this->db->table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->where('journal.company_id', $companyId)->whereNotNull('line.mapping_snapshot')
            ->orderByDesc('journal.posting_date')->get(['journal.id', 'journal.journal_number', 'journal.branch_id', 'journal.source_type', 'journal.source_id', 'journal.posting_type', 'journal.posting_date', 'line.mapping_snapshot']);
        $found = [];
        foreach ($rows as $row) {
            $snapshot = json_decode($this->text($row->mapping_snapshot ?? null), true);
            $resolvedId = is_array($snapshot) && is_int($snapshot['policy_id'] ?? null) ? $snapshot['policy_id'] : null;
            if ($resolvedId === null || ! in_array($resolvedId, $policyIds, true)) {
                continue;
            }
            $journalId = $this->integer($row->id ?? null);
            $found[$resolvedId][$journalId] ??= ['id' => $journalId, 'branch_id' => is_numeric($row->branch_id ?? null) ? (int) $row->branch_id : null, 'number' => $this->text($row->journal_number ?? null), 'source_type' => $this->text($row->source_type ?? null), 'source_id' => $this->integer($row->source_id ?? null), 'posting_type' => $this->text($row->posting_type ?? null), 'date' => $this->text($row->posting_date ?? null)];
        }

        /** @var Collection<int,Collection<int,array{id:int,number:string,source_type:string,source_id:int,posting_type:string,date:string,branch_id?:int|null}>> $grouped */
        $grouped = collect($found)->map(fn (array $journals): Collection => collect(array_values($journals)));

        return $grouped;
    }

    /**
     * @param  array<string,mixed>  $journal
     * @return array{source_label:string,source_url:?string}
     */
    private function sourceLink(array $journal, int $companyId, ?User $actor): array
    {
        $sourceType = is_string($journal['source_type'] ?? null) ? $journal['source_type'] : '';
        $branchId = is_int($journal['branch_id'] ?? null) ? $journal['branch_id'] : null;
        $scope = array_filter(['company_id' => $companyId, 'branch_id' => $branchId], fn (mixed $value): bool => $value !== null);
        [$label, $permission, $routeName, $parameters] = match ($sourceType) {
            'goods_receipt' => ['Goods Receipt', 'goods-receipt.view', 'admin.procurement.receipts', $scope],
            'customer_collection' => ['Customer Collection', 'sales.view', 'admin.sales.index', $scope],
            'sales_fulfillment' => ['Sales Fulfillment / COGS', 'sales.view', 'admin.sales.index', $scope],
            'finance_expense' => ['Operating Expense', 'finance.operations.view', 'admin.finance.operations', ['page' => 'expenses', ...$scope]],
            default => [str($sourceType)->headline()->toString(), null, null, []],
        };

        return ['source_label' => $label, 'source_url' => $permission !== null && $routeName !== null && ($actor?->can($permission) ?? false) ? route($routeName, $parameters) : null];
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid policy workspace integer.'));
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid policy workspace text.');
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
