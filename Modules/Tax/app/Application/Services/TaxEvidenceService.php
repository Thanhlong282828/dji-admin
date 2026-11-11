<?php

namespace Modules\Tax\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class TaxEvidenceService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $policy
     * @param  array<string, int|string>  $context
     */
    public function record(
        int $companyId,
        ?int $branchId,
        string $sourceType,
        int $sourceId,
        string $date,
        string $currency,
        int $taxableAmountMinor,
        int $taxAmountMinor,
        int $journalId,
        array $policy,
        array $context = [],
    ): int {
        $existing = $this->db->table('tax_transaction_evidence')->where([
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'tax_category_id' => $policy['category_id'] ?? null,
        ])->lockForUpdate()->first();
        if ($existing !== null) {
            /** @var object{id:int,accounting_journal_id:int,tax_amount_minor:int} $existing */
            if ($existing->accounting_journal_id !== $journalId || $existing->tax_amount_minor !== $taxAmountMinor) {
                throw ValidationException::withMessages(['tax_evidence' => 'TAX_SOURCE_ALREADY_POSTED']);
            }

            return $existing->id;
        }

        return (int) $this->db->table('tax_transaction_evidence')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'tax_category_id' => $policy['category_id'],
            'tax_policy_id' => $policy['policy_id'],
            'policy_version' => $policy['version'],
            'rate_basis_points' => $policy['rate_basis_points'],
            'taxable_amount_minor' => $taxableAmountMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'currency' => $currency,
            'posting_date' => $date,
            'accounting_journal_id' => $journalId,
            'mapping_snapshot' => json_encode([
                'tax_account_id' => $policy['tax_account_id'],
                'tax_account_code' => $policy['tax_account_code'],
                'accounting_policy_id' => $policy['accounting_policy_id'],
                'accounting_policy_version' => $policy['accounting_policy_version'],
            ] + $context, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
