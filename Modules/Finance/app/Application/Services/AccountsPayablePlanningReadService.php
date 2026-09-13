<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** @phpstan-type ApFilters array{as_of_date:string,vendor_id:?int,branch_id:?int,cost_center_id:?int,due_bucket:?string,payment_status:?string,currency:string,sort:string,direction:string} */
final readonly class AccountsPayablePlanningReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  ApFilters  $filters
     * @return array<string,mixed>
     */
    public function read(int $companyId, array $filters): array
    {
        $asOf = CarbonImmutable::parse($filters['as_of_date'])->startOfDay();
        $allocated = $this->db->table('ap_payment_allocations as allocation')
            ->join('ap_payments as payment', 'payment.id', '=', 'allocation.payment_id')
            ->where('payment.company_id', $companyId)
            ->where('payment.status', 'approved')
            ->whereDate('payment.payment_date', '<=', $asOf->toDateString())
            ->selectRaw('allocation.vendor_bill_id, SUM(allocation.allocated_minor) allocated_minor')
            ->groupBy('allocation.vendor_bill_id');

        $query = $this->db->table('vendor_bills as bill')
            ->join('procurement_vendors as vendor', 'vendor.id', '=', 'bill.vendor_id')
            ->join('branches as branch', 'branch.id', '=', 'bill.branch_id')
            ->leftJoinSub($allocated, 'allocated', 'allocated.vendor_bill_id', '=', 'bill.id')
            ->where('bill.company_id', $companyId)
            ->whereIn('bill.status', ['approved', 'partially_paid', 'paid'])
            ->whereDate('bill.invoice_date', '<=', $asOf->toDateString())
            ->where('bill.currency', $filters['currency'])
            ->when($filters['vendor_id'], fn (Builder $inner, int $id): Builder => $inner->where('bill.vendor_id', $id))
            ->when($filters['branch_id'], fn (Builder $inner, int $id): Builder => $inner->where('bill.branch_id', $id));
        $this->costCenterScope($query, $filters['cost_center_id']);

        $sourceRows = $query->selectRaw('bill.id, bill.invoice_number, bill.invoice_date, bill.due_date, bill.status, bill.total_minor, bill.currency, bill.accounting_journal_id, bill.vendor_id, vendor.code vendor_code, vendor.name vendor_name, bill.branch_id, branch.name branch_name, COALESCE(allocated.allocated_minor,0) paid_minor, bill.total_minor-COALESCE(allocated.allocated_minor,0) outstanding_minor')
            ->get();

        $hasClearingEvidence = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'code' => '1100', 'status' => 'active'])->exists();
        $rows = [];
        $ledgerTotals = ['original' => 0, 'paid' => 0, 'outstanding' => 0];
        foreach ($sourceRows as $source) {
            $outstanding = $this->integer($source->outstanding_minor ?? null);
            $ledgerTotals['original'] += $this->integer($source->total_minor ?? null);
            $ledgerTotals['paid'] += $this->integer($source->paid_minor ?? null);
            $ledgerTotals['outstanding'] += max(0, $outstanding);
            if ($outstanding <= 0) {
                continue;
            }
            $dueDate = is_string($source->due_date ?? null) && $source->due_date !== '' ? CarbonImmutable::parse($source->due_date) : null;
            $bucket = $this->agingBucket($asOf, $dueDate);
            $paymentState = $this->integer($source->paid_minor ?? null) > 0 ? 'PARTIALLY_PAID' : 'UNPAID';
            $readiness = in_array($source->status ?? null, ['approved', 'partially_paid'], true) && is_numeric($source->accounting_journal_id ?? null) && $hasClearingEvidence ? 'READY' : 'UNKNOWN';
            $rows[] = [
                'bill_id' => $this->integer($source->id ?? null),
                'invoice_number' => $this->text($source->invoice_number ?? null),
                'invoice_date' => $this->text($source->invoice_date ?? null),
                'due_date' => $dueDate?->toDateString(),
                'vendor_id' => $this->integer($source->vendor_id ?? null),
                'vendor_code' => $this->text($source->vendor_code ?? null),
                'vendor_name' => $this->text($source->vendor_name ?? null),
                'branch_id' => $this->integer($source->branch_id ?? null),
                'branch_name' => $this->text($source->branch_name ?? null),
                'original' => $this->integer($source->total_minor ?? null),
                'paid' => $this->integer($source->paid_minor ?? null),
                'outstanding' => $outstanding,
                'currency' => $this->text($source->currency ?? null),
                'aging_bucket' => $bucket,
                'days_overdue' => $dueDate !== null && $dueDate->lt($asOf) ? (int) floor($dueDate->diffInDays($asOf)) : 0,
                'days_to_due' => $dueDate !== null && $dueDate->gt($asOf) ? (int) floor($asOf->diffInDays($dueDate)) : 0,
                'payment_status' => $paymentState,
                'readiness' => $readiness,
                'treasury_destination' => $hasClearingEvidence ? 'PAYMENT_CLEARING_CONFIGURED' : 'CONFIGURATION_REQUIRED',
            ];
        }

        $rows = array_values(array_filter($rows, fn (array $row): bool => ($filters['due_bucket'] === null || $row['aging_bucket'] === $filters['due_bucket']) && ($filters['payment_status'] === null || $row['payment_status'] === $filters['payment_status'])));
        $this->sort($rows, $filters['sort'], $filters['direction']);

        $aging = ['NOT_DUE' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_PLUS' => 0, 'DUE_DATE_UNKNOWN' => 0];
        $forecast = ['OVERDUE' => 0, 'DUE_TODAY' => 0, 'NEXT_7' => 0, 'NEXT_30' => 0, 'NEXT_60' => 0, 'NEXT_90' => 0, 'BEYOND_90' => 0, 'UNSCHEDULED_PAYABLE' => 0];
        $readiness = ['READY' => 0, 'BLOCKED' => 0, 'UNKNOWN' => 0];
        $vendors = [];
        foreach ($rows as $row) {
            $amount = $this->integer($row['outstanding']);
            $agingBucket = $this->text($row['aging_bucket']);
            $forecastBucket = $this->forecastBucket($asOf, is_string($row['due_date']) ? CarbonImmutable::parse($row['due_date']) : null);
            $aging[$agingBucket] += $amount;
            $forecast[$forecastBucket] += $amount;
            $readiness[$this->text($row['readiness'])]++;
            $vendorId = $this->integer($row['vendor_id']);
            $vendors[$vendorId] ??= ['vendor_id' => $vendorId, 'vendor_code' => $this->text($row['vendor_code']), 'vendor_name' => $this->text($row['vendor_name']), 'outstanding' => 0, 'overdue' => 0, 'due_next_7' => 0, 'due_next_30' => 0, 'unscheduled' => 0];
            $vendors[$vendorId]['outstanding'] += $amount;
            $vendors[$vendorId]['overdue'] += $forecastBucket === 'OVERDUE' ? $amount : 0;
            $vendors[$vendorId]['due_next_7'] += in_array($forecastBucket, ['DUE_TODAY', 'NEXT_7'], true) ? $amount : 0;
            $vendors[$vendorId]['due_next_30'] += in_array($forecastBucket, ['DUE_TODAY', 'NEXT_7', 'NEXT_30'], true) ? $amount : 0;
            $vendors[$vendorId]['unscheduled'] += $forecastBucket === 'UNSCHEDULED_PAYABLE' ? $amount : 0;
        }

        $hasRowFilter = $filters['due_bucket'] !== null || $filters['payment_status'] !== null;
        $original = $hasRowFilter ? array_sum(array_column($rows, 'original')) : $ledgerTotals['original'];
        $paid = $hasRowFilter ? array_sum(array_column($rows, 'paid')) : $ledgerTotals['paid'];
        $outstanding = $hasRowFilter ? array_sum(array_column($rows, 'outstanding')) : $ledgerTotals['outstanding'];
        $gl = $this->apGeneralLedger($companyId, $asOf, $filters['branch_id'], $filters['currency']);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => ['original' => $original, 'paid' => $paid, 'outstanding' => $outstanding],
            'aging' => $aging,
            'forecast' => $forecast,
            'vendors' => array_values($vendors),
            'readiness' => $readiness,
            'reconciliation' => ['subledger' => $outstanding, 'gl_2100' => $filters['vendor_id'] === null && $filters['cost_center_id'] === null && $filters['due_bucket'] === null && $filters['payment_status'] === null ? $gl : null, 'difference' => $filters['vendor_id'] === null && $filters['cost_center_id'] === null && $filters['due_bucket'] === null && $filters['payment_status'] === null ? $outstanding - $gl : null, 'scope' => 'PROCUREMENT_VENDOR_BILL_AND_AP_PAYMENT_ONLY'],
            'cash_evidence' => ['available_cash' => null, 'coverage' => null, 'status' => 'NOT_ENOUGH_EVIDENCE', 'reason' => 'Treasury has movement and clearing evidence but no verified opening cash balance.'],
            'limitations' => ['allocation_reversal' => 'NOT_IMPLEMENTED_IN_OWNER_DOMAIN', 'forecast' => 'AP_OUTFLOW_ONLY_NOT_FULL_CASH_FLOW_FORECAST'],
        ];
    }

    private function agingBucket(CarbonImmutable $asOf, ?CarbonImmutable $due): string
    {
        if ($due === null) {
            return 'DUE_DATE_UNKNOWN';
        }
        if ($due->gte($asOf)) {
            return 'NOT_DUE';
        }
        $days = $due->diffInDays($asOf);

        return $days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_PLUS'));
    }

    private function forecastBucket(CarbonImmutable $asOf, ?CarbonImmutable $due): string
    {
        if ($due === null) {
            return 'UNSCHEDULED_PAYABLE';
        }
        $days = (int) floor($asOf->diffInDays($due, false));
        if ($days < 0) {
            return 'OVERDUE';
        }

        return match (true) {
            $days === 0 => 'DUE_TODAY',
            $days <= 7 => 'NEXT_7',
            $days <= 30 => 'NEXT_30',
            $days <= 60 => 'NEXT_60',
            $days <= 90 => 'NEXT_90',
            default => 'BEYOND_90',
        };
    }

    private function apGeneralLedger(int $companyId, CarbonImmutable $asOf, ?int $branchId, string $currency): int
    {
        return $this->integer($this->db->table('payroll_accounting_journal_lines as line')
            ->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->where('journal.company_id', $companyId)
            ->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereIn('journal.source_type', ['vendor_bill', 'ap_payment'])
            ->where('journal.currency', $currency)
            ->whereDate('journal.posting_date', '<=', $asOf->toDateString())
            ->where('line.account_code_snapshot', '2100')
            ->when($branchId, fn (Builder $inner, int $id): Builder => $inner->where('journal.branch_id', $id))
            ->sum($this->db->raw('CAST(line.credit_minor AS SIGNED)-CAST(line.debit_minor AS SIGNED)')));
    }

    private function costCenterScope(Builder $query, ?int $costCenterId): void
    {
        $query->when($costCenterId, function (Builder $inner, int $id): void {
            $inner->whereExists(fn (Builder $lines): Builder => $lines->selectRaw('1')->from('vendor_bill_lines as scoped_line')->join('purchase_order_lines as po_line', 'po_line.id', '=', 'scoped_line.purchase_order_line_id')->whereColumn('scoped_line.vendor_bill_id', 'bill.id')->where('po_line.cost_center_id', $id));
        });
    }

    /** @param list<array<string,mixed>> $rows */
    private function sort(array &$rows, string $sort, string $direction): void
    {
        usort($rows, function (array $left, array $right) use ($sort, $direction): int {
            $a = $left[$sort] ?? 0;
            $b = $right[$sort] ?? 0;
            $comparison = is_string($a) && is_string($b) ? strcmp($a, $b) : ($this->integer($a) <=> $this->integer($b));

            return $direction === 'asc' ? $comparison : -$comparison;
        });
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
