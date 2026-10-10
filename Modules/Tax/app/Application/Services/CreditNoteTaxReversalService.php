<?php

namespace Modules\Tax\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

final readonly class CreditNoteTaxReversalService
{
    public function __construct(
        private ConnectionInterface $db,
        private TaxEvidenceService $evidence,
    ) {}

    public function record(
        int $companyId,
        int $branchId,
        int $creditNoteId,
        int $invoiceId,
        string $date,
        string $currency,
        int $taxableAmountMinor,
        int $taxAmountMinor,
        int $journalId,
    ): ?int {
        if ($taxAmountMinor === 0) {
            return null;
        }

        $source = $this->db->table('tax_transaction_evidence')->where([
            'company_id' => $companyId,
            'source_type' => 'customer_invoice',
            'source_id' => $invoiceId,
        ])->lockForUpdate()->get();
        if ($source->count() !== 1) {
            throw ValidationException::withMessages(['tax_evidence' => 'CREDIT_NOTE_TAX_SOURCE_REQUIRED']);
        }
        $original = $source->first();
        if ($original === null || (int) $original->branch_id !== $branchId || (string) $original->currency !== $currency) {
            throw ValidationException::withMessages(['tax_evidence' => 'CREDIT_NOTE_TAX_SOURCE_SCOPE_INVALID']);
        }

        $rate = (int) $original->rate_basis_points;
        if ($taxAmountMinor !== intdiv(($taxableAmountMinor * $rate) + 5_000, 10_000)) {
            throw ValidationException::withMessages(['tax_minor' => 'CREDIT_NOTE_TAX_RATE_MISMATCH']);
        }
        $reversed = $this->db->table('tax_transaction_evidence as evidence')
            ->join('customer_credit_notes as note', 'note.id', '=', 'evidence.source_id')
            ->where(['evidence.company_id' => $companyId, 'evidence.source_type' => 'customer_credit_note', 'evidence.tax_category_id' => $original->tax_category_id, 'note.customer_invoice_id' => $invoiceId])
            ->where('note.id', '<>', $creditNoteId);
        if ($taxAmountMinor > max((int) $original->tax_amount_minor - (int) (clone $reversed)->sum('evidence.tax_amount_minor'), 0)
            || $taxableAmountMinor > max((int) $original->taxable_amount_minor - (int) (clone $reversed)->sum('evidence.taxable_amount_minor'), 0)) {
            throw ValidationException::withMessages(['tax_minor' => 'CREDIT_NOTE_TAX_EXCEEDS_ORIGINAL']);
        }

        $mapping = json_decode((string) $original->mapping_snapshot, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($mapping)
            || ! is_numeric($mapping['tax_account_id'] ?? null)
            || ! is_string($mapping['tax_account_code'] ?? null)
            || ! is_numeric($mapping['accounting_policy_id'] ?? null)
            || ! is_numeric($mapping['accounting_policy_version'] ?? null)) {
            throw ValidationException::withMessages(['tax_evidence' => 'CREDIT_NOTE_TAX_MAPPING_REQUIRED']);
        }
        $taxLine = $this->db->table('payroll_accounting_journal_lines')->where([
            'journal_id' => $journalId,
            'account_id' => (int) $mapping['tax_account_id'],
            'debit_minor' => $taxAmountMinor,
            'credit_minor' => 0,
        ])->count();
        if ($taxLine !== 1) {
            throw ValidationException::withMessages(['tax_evidence' => 'CREDIT_NOTE_TAX_JOURNAL_MAPPING_INVALID']);
        }

        return $this->evidence->record($companyId, $branchId, 'customer_credit_note', $creditNoteId, $date, $currency, $taxableAmountMinor, $taxAmountMinor, $journalId, [
            'category_id' => (int) $original->tax_category_id,
            'policy_id' => (int) $original->tax_policy_id,
            'version' => (int) $original->policy_version,
            'rate_basis_points' => $rate,
            'tax_account_id' => (int) $mapping['tax_account_id'],
            'tax_account_code' => $mapping['tax_account_code'],
            'accounting_policy_id' => (int) $mapping['accounting_policy_id'],
            'accounting_policy_version' => (int) $mapping['accounting_policy_version'],
        ], [
            'event' => 'vat_reversal',
            'reversal_of_tax_evidence_id' => (int) $original->id,
            'reversal_of_source_type' => 'customer_invoice',
            'reversal_of_source_id' => $invoiceId,
        ]);
    }
}
