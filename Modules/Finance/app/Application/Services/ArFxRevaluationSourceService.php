<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use stdClass;

/** Finance-owned, as-of read model for open foreign-currency customer receivables. */
final readonly class ArFxRevaluationSourceService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return list<FxRevaluableSource> */
    public function eligible(int $companyId, ?int $branchId, string $asOfDate): array
    {
        $company = $this->db->table('companies')->where('id', $companyId)->first(['functional_currency_code']);
        $functional = $company instanceof stdClass && is_string($company->functional_currency_code)
            ? strtoupper($company->functional_currency_code)
            : '';
        if ($functional === '') {
            throw ValidationException::withMessages(['company' => 'FUNCTIONAL_CURRENCY_REQUIRED']);
        }

        $recognitions = $this->db->table('customer_invoice_fx_recognitions as recognition')
            ->join('customer_invoices as invoice', 'invoice.id', '=', 'recognition.customer_invoice_id')
            ->where(['recognition.company_id' => $companyId, 'invoice.company_id' => $companyId])
            ->when($branchId !== null, fn ($query) => $query->where('invoice.branch_id', $branchId))
            ->whereDate('invoice.invoice_date', '<=', $asOfDate)
            ->whereIn('invoice.status', ['issued', 'partially_paid', 'paid', 'overdue'])
            ->whereColumn('recognition.transaction_currency', '<>', 'recognition.functional_currency')
            ->orderBy('invoice.id')
            ->get(['recognition.*', 'invoice.invoice_number', 'invoice.invoice_date']);

        $sources = [];
        foreach ($recognitions as $recognition) {
            $recognitionId = $this->nonNegativeInt($recognition->id ?? null);
            $invoiceId = $this->nonNegativeInt($recognition->customer_invoice_id ?? null);
            $settlements = $this->db->table('customer_collection_allocation_fx_settlements as settlement')
                ->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')
                ->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')
                ->where(['settlement.company_id' => $companyId, 'settlement.customer_invoice_fx_recognition_id' => $recognitionId, 'collection.status' => 'recorded'])
                ->whereDate('collection.collection_date', '<=', $asOfDate)
                ->orderBy('collection.collection_date')->orderBy('settlement.id')
                ->get(['settlement.id', 'settlement.customer_collection_allocation_id', 'settlement.foreign_settled_minor', 'settlement.released_carrying_minor', 'settlement.checksum', 'collection.collection_date']);

            $rawAllocationCount = $this->db->table('customer_collection_allocations as allocation')
                ->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')
                ->leftJoin('customer_collection_allocation_fx_settlements as settlement', 'settlement.customer_collection_allocation_id', '=', 'allocation.id')
                ->where(['allocation.customer_invoice_id' => $invoiceId, 'collection.company_id' => $companyId, 'collection.status' => 'recorded'])
                ->whereDate('collection.collection_date', '<=', $asOfDate)->whereNull('settlement.id')->count();
            if ($rawAllocationCount > 0) {
                throw ValidationException::withMessages(['settlement' => 'AR_FX_REVALUATION_SETTLEMENT_EVIDENCE_INCOMPLETE']);
            }

            $foreignSettled = $settlements->sum(fn (object $row): int => $this->nonNegativeInt($row->foreign_settled_minor ?? null));
            $released = $settlements->sum(fn (object $row): int => $this->nonNegativeInt($row->released_carrying_minor ?? null));
            $foreignOriginal = $this->nonNegativeInt($recognition->transaction_amount_minor ?? null);
            $functionalOriginal = $this->nonNegativeInt($recognition->functional_amount_minor ?? null);
            if ($foreignSettled > $foreignOriginal || $released > $functionalOriginal) {
                throw ValidationException::withMessages(['settlement' => 'AR_FX_REVALUATION_SETTLEMENT_EVIDENCE_INVALID']);
            }
            $outstanding = $foreignOriginal - $foreignSettled;
            if ($outstanding === 0) {
                continue;
            }

            $priorAdjustment = (int) $this->db->table('finance_fx_revaluation_lines as line')
                ->join('finance_fx_revaluation_runs as run', 'run.id', '=', 'line.fx_revaluation_run_id')
                ->where(['line.company_id' => $companyId, 'line.source_type' => 'customer_invoice', 'line.source_id' => $invoiceId, 'run.status' => 'posted'])
                ->whereDate('run.as_of_date', '<', $asOfDate)->sum('line.fx_difference_minor');
            $carrying = $functionalOriginal - $released + $priorAdjustment;
            if ($carrying < 0) {
                throw ValidationException::withMessages(['carrying' => 'AR_FX_REVALUATION_CARRYING_BASIS_INVALID']);
            }
            $snapshotId = $this->positiveInt($recognition->fx_rate_snapshot_id ?? null);
            $checksum = $this->checksum($recognition->checksum ?? null);
            $settlementEvidence = $settlements->map(fn (object $row): array => [
                'settlement_id' => $this->positiveInt($row->id ?? null),
                'allocation_id' => $this->positiveInt($row->customer_collection_allocation_id ?? null),
                'date' => $this->text($row->collection_date ?? null),
                'foreign_minor' => $this->nonNegativeInt($row->foreign_settled_minor ?? null),
                'released_minor' => $this->nonNegativeInt($row->released_carrying_minor ?? null),
                'checksum' => $this->checksum($row->checksum ?? null),
            ])->all();

            $sources[] = new FxRevaluableSource(
                'customer_invoice', $invoiceId, $companyId,
                $recognition->branch_id === null ? null : $this->positiveInt($recognition->branch_id),
                $this->currency($recognition->transaction_currency ?? null), $outstanding,
                $this->currency($recognition->functional_currency ?? null), $carrying, 'asset', $snapshotId, $checksum,
                ['valuation_date' => $asOfDate, 'invoice_number' => $this->text($recognition->invoice_number ?? null), 'invoice_date' => $this->text($recognition->invoice_date ?? null), 'original_foreign_minor' => $foreignOriginal, 'original_functional_minor' => $functionalOriginal, 'settled_foreign_minor' => $foreignSettled, 'released_carrying_minor' => $released, 'prior_active_revaluation_minor' => $priorAdjustment, 'settlements' => $settlementEvidence],
            );
        }

        return $sources;
    }

    private function positiveInt(mixed $value): int
    {
        $integer = $this->nonNegativeInt($value);

        return $integer > 0 ? $integer : throw new \RuntimeException('Invalid positive AR FX evidence.');
    }

    private function nonNegativeInt(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0
            ? (int) $value : throw new \RuntimeException('Invalid AR FX integer evidence.');
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper($this->text($value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : throw new \RuntimeException('Invalid AR FX currency evidence.');
    }

    private function checksum(mixed $value): string
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : throw new \RuntimeException('Invalid AR FX checksum evidence.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AR FX text evidence.');
    }
}
