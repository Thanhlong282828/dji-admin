<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\ApFxCreditCalculator;
use stdClass;

final readonly class SupplierCreditFxEvidenceService
{
    public function __construct(private ConnectionInterface $db, private ApFxCreditCalculator $calculator) {}

    /** @return array{recognition_id:int,return_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_credit_minor:int,cumulative_foreign_credit_minor:int,ap_foreign_minor:int,ap_carrying_release_minor:int,cumulative_ap_carrying_release_minor:int,supplier_receivable_foreign_minor:int,supplier_receivable_functional_minor:int,inventory_return_basis_minor:int,purchase_price_variance_minor:int} */
    public function prepare(int $companyId, object $credit): array
    {
        $recognition = $this->db->table('supplier_invoice_fx_recognitions')->where([
            'company_id' => $companyId,
            'vendor_bill_id' => $this->integer($credit->vendor_bill_id ?? null),
        ])->lockForUpdate()->first();
        if (! $recognition instanceof stdClass) {
            throw ValidationException::withMessages(['fx_evidence' => 'SUPPLIER_CREDIT_AP_FX_RECOGNITION_REQUIRED']);
        }
        if ($this->currency($credit->currency ?? null) !== $this->currency($recognition->transaction_currency ?? null)
            || $this->nullableInteger($credit->branch_id ?? null) !== $this->nullableInteger($recognition->branch_id ?? null)) {
            throw ValidationException::withMessages(['fx_evidence' => 'SUPPLIER_CREDIT_AP_FX_SCOPE_MISMATCH']);
        }

        $settled = $this->db->table('supplier_payment_fx_settlements')->where('supplier_invoice_fx_recognition_id', $recognition->id)
            ->selectRaw('COALESCE(SUM(foreign_settled_minor),0) foreign_minor, COALESCE(SUM(released_carrying_minor),0) carrying_minor')->first();
        $prior = $this->db->table('supplier_credit_fx_evidence as evidence')
            ->join('supplier_credit_notes as credit', 'credit.id', '=', 'evidence.supplier_credit_note_id')
            ->where('evidence.supplier_invoice_fx_recognition_id', $recognition->id)
            ->selectRaw('COALESCE(SUM(evidence.foreign_credit_minor),0) total_credit_minor, COALESCE(SUM(credit.ap_reduction_minor),0) ap_credit_minor, COALESCE(SUM(evidence.ap_carrying_release_minor),0) carrying_minor')->first();
        $priorRevaluation = (int) $this->db->table('finance_fx_revaluation_lines as line')
            ->join('finance_fx_revaluation_runs as run', 'run.id', '=', 'line.fx_revaluation_run_id')
            ->where(['line.company_id' => $companyId, 'line.source_type' => 'vendor_bill', 'line.source_id' => $recognition->vendor_bill_id, 'run.status' => 'posted'])
            ->whereDate('run.as_of_date', '<', $this->text($credit->credit_date ?? null))->sum('line.fx_difference_minor');

        $calculated = $this->calculator->calculate(
            $this->integer($recognition->transaction_amount_minor ?? null),
            $this->integer($recognition->functional_amount_minor ?? null),
            $this->integer($settled->foreign_minor ?? 0),
            $this->integer($settled->carrying_minor ?? 0),
            $this->integer($prior->ap_credit_minor ?? 0),
            $this->integer($prior->carrying_minor ?? 0),
            $priorRevaluation,
            $this->integer($credit->total_minor ?? null),
        );
        $returnBasis = $this->db->table('inventory_return_fx_reversals')->where([
            'company_id' => $companyId,
            'goods_return_id' => $this->integer($credit->goods_return_id ?? null),
        ])->lockForUpdate()->sum('released_functional_basis_minor');
        if ((int) $returnBasis <= 0) {
            throw ValidationException::withMessages(['fx_evidence' => 'SUPPLIER_CREDIT_INVENTORY_RETURN_FX_EVIDENCE_REQUIRED']);
        }
        $returnEvidenceId = $this->db->table('inventory_return_fx_reversals')->where([
            'company_id' => $companyId,
            'goods_return_id' => $this->integer($credit->goods_return_id ?? null),
        ])->value('id');

        return [
            'recognition_id' => $this->integer($recognition->id ?? null),
            'return_evidence_id' => $this->integer($returnEvidenceId),
            'transaction_currency' => $this->currency($recognition->transaction_currency ?? null),
            'functional_currency' => $this->currency($recognition->functional_currency ?? null),
            'foreign_credit_minor' => $this->integer($credit->total_minor ?? null),
            'cumulative_foreign_credit_minor' => $this->integer($prior->total_credit_minor ?? 0) + $this->integer($credit->total_minor ?? null),
            'ap_foreign_minor' => $calculated['ap_foreign_minor'],
            'ap_carrying_release_minor' => $calculated['ap_carrying_release_minor'],
            'cumulative_ap_carrying_release_minor' => $this->integer($prior->carrying_minor ?? 0) + $calculated['ap_carrying_release_minor'],
            'supplier_receivable_foreign_minor' => $calculated['supplier_receivable_foreign_minor'],
            'supplier_receivable_functional_minor' => $calculated['supplier_receivable_functional_minor'],
            'inventory_return_basis_minor' => (int) $returnBasis,
            'purchase_price_variance_minor' => (int) $returnBasis - $calculated['ap_carrying_release_minor'] - $calculated['supplier_receivable_functional_minor'],
        ];
    }

    /** @param array{recognition_id:int,return_evidence_id:int,transaction_currency:string,functional_currency:string,foreign_credit_minor:int,cumulative_foreign_credit_minor:int,ap_foreign_minor:int,ap_carrying_release_minor:int,cumulative_ap_carrying_release_minor:int,supplier_receivable_foreign_minor:int,supplier_receivable_functional_minor:int,inventory_return_basis_minor:int,purchase_price_variance_minor:int} $prepared */
    public function record(int $companyId, object $credit, array $prepared, int $policyId, int $policyVersion, int $journalId, int $actorId): int
    {
        $payload = [
            'company_id' => $companyId,
            'branch_id' => $this->integer($credit->branch_id ?? null),
            'supplier_credit_note_id' => $this->integer($credit->id ?? null),
            'vendor_bill_id' => $this->integer($credit->vendor_bill_id ?? null),
            'supplier_invoice_fx_recognition_id' => $prepared['recognition_id'],
            'goods_return_id' => $this->integer($credit->goods_return_id ?? null),
            'inventory_return_fx_reversal_id' => $prepared['return_evidence_id'],
            'transaction_currency' => $prepared['transaction_currency'],
            'foreign_credit_minor' => $prepared['foreign_credit_minor'],
            'cumulative_foreign_credit_minor' => $prepared['cumulative_foreign_credit_minor'],
            'functional_currency' => $prepared['functional_currency'],
            'ap_carrying_release_minor' => $prepared['ap_carrying_release_minor'],
            'cumulative_ap_carrying_release_minor' => $prepared['cumulative_ap_carrying_release_minor'],
            'supplier_receivable_functional_minor' => $prepared['supplier_receivable_functional_minor'],
            'purchase_price_variance_minor' => $prepared['purchase_price_variance_minor'],
            'accounting_policy_id' => $policyId,
            'accounting_policy_version' => $policyVersion,
            'accounting_journal_id' => $journalId,
            'version' => 1,
        ];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = $this->db->table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $payload['supplier_credit_note_id'])->lockForUpdate()->first();
        if ($existing instanceof stdClass) {
            return $this->existing($existing, $checksum);
        }
        try {
            return (int) $this->db->table('supplier_credit_fx_evidence')->insertGetId($payload + ['checksum' => $checksum, 'created_by' => $actorId, 'created_at' => now()]);
        } catch (QueryException) {
            $winner = $this->db->table('supplier_credit_fx_evidence')->where('supplier_credit_note_id', $payload['supplier_credit_note_id'])->first();
            if (! $winner instanceof stdClass) {
                throw ValidationException::withMessages(['fx_evidence' => 'SUPPLIER_CREDIT_FX_EVIDENCE_CONCURRENT_CREATE_FAILED']);
            }

            return $this->existing($winner, $checksum);
        }
    }

    private function existing(stdClass $row, string $checksum): int
    {
        if (($row->checksum ?? null) !== $checksum) {
            throw ValidationException::withMessages(['fx_evidence' => 'SUPPLIER_CREDIT_FX_EVIDENCE_IMMUTABLE']);
        }

        return $this->integer($row->id ?? null);
    }

    private function integer(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0
            ? (int) $value
            : throw new \RuntimeException('Invalid Supplier Credit FX integer evidence.');
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : $this->integer($value);
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper($this->text($value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : throw new \RuntimeException('Invalid Supplier Credit FX currency evidence.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid Supplier Credit FX text evidence.');
    }
}
