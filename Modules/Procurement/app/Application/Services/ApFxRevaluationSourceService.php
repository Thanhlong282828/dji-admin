<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\FxRevaluableSource;
use Modules\Finance\Application\Services\FxRevaluationSourceProvider;

/** Procurement-owned, historical as-of read model for open foreign Service Procurement liabilities. */
final readonly class ApFxRevaluationSourceService implements FxRevaluationSourceProvider
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return list<FxRevaluableSource> */
    public function eligible(int $companyId, ?int $branchId, string $asOfDate): array
    {
        $functional = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code');
        if (! is_string($functional) || preg_match('/^[A-Z]{3}$/', $functional) !== 1) {
            throw ValidationException::withMessages(['company' => 'FUNCTIONAL_CURRENCY_REQUIRED']);
        }

        $recognitions = $this->db->table('supplier_invoice_fx_recognitions as recognition')
            ->join('vendor_bills as bill', 'bill.id', '=', 'recognition.vendor_bill_id')
            ->where(['recognition.company_id' => $companyId, 'bill.company_id' => $companyId])
            ->when($branchId !== null, fn ($query) => $query->where('bill.branch_id', $branchId))
            ->whereDate('bill.invoice_date', '<=', $asOfDate)
            ->whereIn('bill.status', ['approved', 'partially_paid', 'paid', 'overdue'])
            ->whereColumn('recognition.transaction_currency', '<>', 'recognition.functional_currency')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('vendor_bill_lines as service_line')->whereColumn('service_line.vendor_bill_id', 'bill.id')->whereNotNull('service_line.service_acceptance_line_id'))
            ->orderBy('bill.id')
            ->get(['recognition.*', 'bill.invoice_number', 'bill.invoice_date']);

        $sources = [];
        foreach ($recognitions as $recognition) {
            $recognitionId = $this->positiveInt($recognition->id ?? null);
            $billId = $this->positiveInt($recognition->vendor_bill_id ?? null);
            $settlements = $this->db->table('supplier_payment_fx_settlements as settlement')
                ->join('ap_payments as payment', 'payment.id', '=', 'settlement.ap_payment_id')
                ->where(['settlement.company_id' => $companyId, 'settlement.supplier_invoice_fx_recognition_id' => $recognitionId, 'payment.status' => 'approved'])
                ->whereDate('payment.payment_date', '<=', $asOfDate)
                ->orderBy('payment.payment_date')->orderBy('settlement.id')
                ->get(['settlement.id', 'settlement.ap_payment_allocation_id', 'settlement.foreign_settled_minor', 'settlement.released_carrying_minor', 'settlement.checksum', 'payment.payment_date']);

            $missingEvidence = $this->db->table('ap_payment_allocations as allocation')
                ->join('ap_payments as payment', 'payment.id', '=', 'allocation.payment_id')
                ->leftJoin('supplier_payment_fx_settlements as settlement', 'settlement.ap_payment_allocation_id', '=', 'allocation.id')
                ->where(['allocation.vendor_bill_id' => $billId, 'payment.company_id' => $companyId, 'payment.status' => 'approved'])
                ->whereDate('payment.payment_date', '<=', $asOfDate)->whereNull('settlement.id')->exists();
            if ($missingEvidence) {
                throw ValidationException::withMessages(['settlement' => 'AP_FX_REVALUATION_SETTLEMENT_EVIDENCE_INCOMPLETE']);
            }

            $foreignSettled = $settlements->sum(fn (object $row): int => $this->nonNegativeInt($row->foreign_settled_minor ?? null));
            $released = $settlements->sum(fn (object $row): int => $this->nonNegativeInt($row->released_carrying_minor ?? null));
            $foreignOriginal = $this->nonNegativeInt($recognition->transaction_amount_minor ?? null);
            $functionalOriginal = $this->nonNegativeInt($recognition->functional_amount_minor ?? null);
            if ($foreignSettled > $foreignOriginal || $released > $functionalOriginal) {
                throw ValidationException::withMessages(['settlement' => 'AP_FX_REVALUATION_SETTLEMENT_EVIDENCE_INVALID']);
            }
            $outstanding = $foreignOriginal - $foreignSettled;
            if ($outstanding === 0) {
                continue;
            }

            $priorAdjustment = (int) $this->db->table('finance_fx_revaluation_lines as line')
                ->join('finance_fx_revaluation_runs as run', 'run.id', '=', 'line.fx_revaluation_run_id')
                ->where(['line.company_id' => $companyId, 'line.source_type' => 'vendor_bill', 'line.source_id' => $billId, 'run.status' => 'posted'])
                ->whereDate('run.as_of_date', '<', $asOfDate)->sum('line.fx_difference_minor');
            $carrying = $functionalOriginal - $released + $priorAdjustment;
            if ($carrying < 0) {
                throw ValidationException::withMessages(['carrying' => 'AP_FX_REVALUATION_CARRYING_BASIS_INVALID']);
            }

            $sources[] = new FxRevaluableSource(
                'vendor_bill', $billId, $companyId,
                $recognition->branch_id === null ? null : $this->positiveInt($recognition->branch_id),
                $this->currency($recognition->transaction_currency ?? null), $outstanding,
                $this->currency($recognition->functional_currency ?? null), $carrying, 'liability',
                $this->positiveInt($recognition->fx_rate_snapshot_id ?? null), $this->checksum($recognition->checksum ?? null),
                ['valuation_date' => $asOfDate, 'invoice_number' => $this->text($recognition->invoice_number ?? null), 'invoice_date' => $this->text($recognition->invoice_date ?? null), 'original_foreign_minor' => $foreignOriginal, 'original_functional_minor' => $functionalOriginal, 'settled_foreign_minor' => $foreignSettled, 'released_carrying_minor' => $released, 'prior_active_revaluation_minor' => $priorAdjustment, 'settlements' => $settlements->map(fn (object $row): array => ['settlement_id' => $this->positiveInt($row->id ?? null), 'allocation_id' => $this->positiveInt($row->ap_payment_allocation_id ?? null), 'date' => $this->text($row->payment_date ?? null), 'foreign_minor' => $this->nonNegativeInt($row->foreign_settled_minor ?? null), 'released_minor' => $this->nonNegativeInt($row->released_carrying_minor ?? null), 'checksum' => $this->checksum($row->checksum ?? null)])->all()],
            );
        }

        return $sources;
    }

    private function positiveInt(mixed $value): int
    {
        $integer = $this->nonNegativeInt($value);

        return $integer > 0 ? $integer : throw new \RuntimeException('Invalid positive AP FX evidence.');
    }

    private function nonNegativeInt(mixed $value): int
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 ? (int) $value : throw new \RuntimeException('Invalid AP FX integer evidence.');
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper($this->text($value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : throw new \RuntimeException('Invalid AP FX currency evidence.');
    }

    private function checksum(mixed $value): string
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : throw new \RuntimeException('Invalid AP FX checksum evidence.');
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid AP FX text evidence.');
    }
}
