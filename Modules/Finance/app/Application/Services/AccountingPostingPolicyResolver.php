<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class AccountingPostingPolicyResolver
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{policy_id:int,policy_key:string,version:int,source_type:string,business_event:string,classification:?string,currency:string,effective_from:string,effective_to:?string,lines:list<array{sequence:int,entry_side:string,line_role:string,account_id:int,account_code:string,account_name:string,account_type:string,requires_cost_center:bool}>} */
    public function resolve(int $companyId, string $businessEvent, ?string $classification, string $postingDate, string $currency): array
    {
        $policies = $this->db->table('accounting_posting_policies')
            ->where(['company_id' => $companyId, 'business_event' => $businessEvent, 'currency' => $currency, 'status' => 'approved'])
            ->where('classification', $classification)
            ->whereDate('effective_from', '<=', $postingDate)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $postingDate))
            ->orderByDesc('version')->get();
        if ($policies->isEmpty()) {
            throw ValidationException::withMessages(['accounting_policy' => 'ACCOUNTING_POSTING_POLICY_MISSING']);
        }
        if ($policies->count() !== 1) {
            throw ValidationException::withMessages(['accounting_policy' => 'ACCOUNTING_POSTING_POLICY_AMBIGUOUS']);
        }
        $policy = $policies->first();
        $lines = $this->lines($companyId, $this->integer($policy->id ?? null));

        return [
            'policy_id' => $this->integer($policy->id ?? null),
            'policy_key' => $this->text($policy->policy_key ?? null),
            'version' => $this->integer($policy->version ?? null),
            'source_type' => $this->text($policy->source_type ?? null),
            'business_event' => $this->text($policy->business_event ?? null),
            'classification' => $this->nullableText($policy->classification ?? null),
            'currency' => $this->text($policy->currency ?? null),
            'effective_from' => $this->text($policy->effective_from ?? null),
            'effective_to' => $this->nullableText($policy->effective_to ?? null),
            'lines' => array_values($lines->map(fn (stdClass $line): array => [
                'sequence' => $this->integer($line->sequence ?? null), 'entry_side' => $this->text($line->entry_side ?? null),
                'line_role' => $this->text($line->line_role ?? null), 'account_id' => $this->integer($line->account_id ?? null),
                'account_code' => $this->text($line->account_code ?? null), 'account_name' => $this->text($line->account_name ?? null),
                'account_type' => $this->text($line->account_type ?? null), 'requires_cost_center' => (bool) ($line->requires_cost_center ?? false),
            ])->all()),
        ];
    }

    /** @return Collection<int,stdClass> */
    private function lines(int $companyId, int $policyId): Collection
    {
        return $this->db->table('accounting_posting_policy_lines as l')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where(['l.accounting_posting_policy_id' => $policyId, 'a.company_id' => $companyId])
            ->orderBy('l.sequence')->get(['l.sequence', 'l.entry_side', 'l.line_role', 'l.account_id', 'l.requires_cost_center', 'a.code as account_code', 'a.name as account_name', 'a.account_type']);
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new \RuntimeException('Invalid accounting policy integer.'));
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid accounting policy text.');
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
