<?php

namespace Modules\Commerce\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class CustomerRefundReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        if (! Schema::hasTable('customer_refunds') || ! Schema::hasTable('customer_refund_allocations')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'metrics' => [], 'liabilities' => collect(), 'refunds' => collect(), 'allocations' => collect(), 'accounts' => collect()];
        }
        $posted = $this->db->table('customer_refund_allocations as allocation')
            ->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')
            ->where('refund.status', 'posted')->selectRaw('allocation.customer_credit_note_id, SUM(allocation.allocated_minor) refunded_minor')->groupBy('allocation.customer_credit_note_id');
        $pending = $this->db->table('customer_refund_allocations as allocation')
            ->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')
            ->whereIn('refund.status', ['draft', 'submitted', 'approved'])->selectRaw('allocation.customer_credit_note_id, SUM(allocation.allocated_minor) pending_minor')->groupBy('allocation.customer_credit_note_id');
        $liabilities = $this->db->table('customer_credit_notes as note')
            ->join('customer_invoices as invoice', 'invoice.id', '=', 'note.customer_invoice_id')
            ->join('commerce_customers as customer', 'customer.id', '=', 'note.customer_id')
            ->leftJoinSub($posted, 'posted_refund', 'posted_refund.customer_credit_note_id', '=', 'note.id')
            ->leftJoinSub($pending, 'pending_refund', 'pending_refund.customer_credit_note_id', '=', 'note.id')
            ->where(['note.company_id' => $companyId, 'note.status' => 'posted'])->where('note.customer_credit_liability_minor', '>', 0)
            ->orderByDesc('note.effective_date')->get([
                'note.id', 'note.branch_id', 'note.customer_id', 'note.credit_note_number', 'note.effective_date', 'note.currency', 'note.customer_credit_liability_minor',
                'invoice.id as customer_invoice_id', 'invoice.invoice_number', 'customer.full_name as customer_name',
                DB::raw('COALESCE(posted_refund.refunded_minor,0) as refunded_minor'),
                DB::raw('COALESCE(pending_refund.pending_minor,0) as pending_minor'),
                DB::raw('GREATEST(note.customer_credit_liability_minor-COALESCE(posted_refund.refunded_minor,0)-COALESCE(pending_refund.pending_minor,0),0) as available_to_reserve_minor'),
                DB::raw('GREATEST(note.customer_credit_liability_minor-COALESCE(posted_refund.refunded_minor,0),0) as economic_available_minor'),
            ]);
        $refunds = $this->db->table('customer_refunds as refund')
            ->join('commerce_customers as customer', 'customer.id', '=', 'refund.customer_id')
            ->join('treasury_financial_accounts as account', 'account.id', '=', 'refund.financial_account_id')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'refund.accounting_journal_id')
            ->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'refund.treasury_transaction_id')
            ->where('refund.company_id', $companyId)->orderByDesc('refund.id')
            ->get(['refund.*', 'customer.full_name as customer_name', 'account.code as account_code', 'account.name as account_name', 'journal.journal_number', 'treasury.reference as treasury_reference']);
        $allocations = $this->db->table('customer_refund_allocations as allocation')
            ->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')
            ->join('customer_credit_notes as note', 'note.id', '=', 'allocation.customer_credit_note_id')
            ->where('refund.company_id', $companyId)->get(['allocation.*', 'refund.reference as refund_reference', 'refund.status as refund_status', 'note.credit_note_number']);
        $original = $this->aggregateMinor($liabilities->sum('customer_credit_liability_minor'));
        $refunded = $this->aggregateMinor($liabilities->sum('refunded_minor'));
        $pendingAmount = $this->aggregateMinor($liabilities->sum('pending_minor'));

        return [
            'schema_status' => 'READY',
            'metrics' => ['original_minor' => $original, 'available_minor' => max($original - $refunded, 0), 'pending_minor' => $pendingAmount, 'refunded_minor' => $refunded, 'refund_count' => $refunds->count()],
            'liabilities' => $liabilities, 'refunds' => $refunds, 'allocations' => $allocations,
            'accounts' => $this->db->table('treasury_financial_accounts')->where(['company_id' => $companyId, 'status' => 'active'])->whereIn('type', ['cash', 'bank'])->orderBy('code')->get(['id', 'branch_id', 'code', 'name', 'currency']),
        ];
    }

    private function aggregateMinor(mixed $value): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
    }
}
