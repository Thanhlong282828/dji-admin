<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Tax\Application\Services\CreditNoteTaxReversalService;

final readonly class CustomerCreditNoteService
{
    public function __construct(
        private ConnectionInterface $db,
        private AccountingPostingPolicyResolver $policies,
        private SourceAccountingPostingService $accounting,
        private CreditNoteTaxReversalService $taxReversals,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(int $companyId, User $actor, array $data): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $key = $this->text($data, 'idempotency_key');
            $existing = $this->db->table('customer_credit_notes')->where(['company_id' => $companyId, 'idempotency_key' => $key])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $invoice = $this->invoice($companyId, $this->positive($data, 'customer_invoice_id'), true);
            $existing = $this->db->table('customer_credit_notes')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $lineData = $data['lines'] ?? null;
            if (! is_array($lineData) || $lineData === []) {
                throw ValidationException::withMessages(['lines' => 'CUSTOMER_CREDIT_NOTE_LINES_REQUIRED']);
            }
            $lines = [];
            $net = $tax = $total = 0;
            foreach (array_values($lineData) as $input) {
                if (! is_array($input)) {
                    throw ValidationException::withMessages(['lines' => 'CUSTOMER_CREDIT_NOTE_LINE_INVALID']);
                }
                $source = $this->invoiceLine($this->id($invoice), $this->arrayPositive($input, 'customer_invoice_line_id'));
                $lineNet = $this->arrayNonNegative($input, 'net_minor');
                $lineTax = $this->arrayNonNegative($input, 'tax_minor');
                $lineTotal = $this->arrayPositive($input, 'total_minor');
                if ($lineNet + $lineTax !== $lineTotal) {
                    throw ValidationException::withMessages(['lines' => 'CUSTOMER_CREDIT_NOTE_LINE_TOTAL_INVALID']);
                }
                $quantity = $this->arrayNullablePositive($input, 'quantity');
                $this->assertLineRemainder($this->id($source), $source, $lineNet, $lineTax, $lineTotal, $quantity);
                $lines[] = ['source' => $source, 'quantity' => $quantity, 'net' => $lineNet, 'tax' => $lineTax, 'total' => $lineTotal, 'description' => $this->arrayText($input, 'description')];
                $net += $lineNet;
                $tax += $lineTax;
                $total += $lineTotal;
            }
            if ($total > $this->remainingInvoiceCredit($this->id($invoice))) {
                throw ValidationException::withMessages(['total_minor' => 'CUSTOMER_CREDIT_NOTE_EXCEEDS_REMAINING']);
            }
            $id = (int) $this->db->table('customer_credit_notes')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $this->fieldInt($invoice, 'branch_id'), 'customer_id' => $this->fieldInt($invoice, 'customer_id'),
                'customer_invoice_id' => $this->id($invoice), 'credit_note_number' => $this->text($data, 'credit_note_number'),
                'effective_date' => $this->text($data, 'effective_date'), 'currency' => $this->fieldText($invoice, 'currency'), 'reason' => $this->text($data, 'reason'),
                'status' => 'draft', 'net_minor' => $net, 'tax_minor' => $tax, 'total_minor' => $total, 'idempotency_key' => $key,
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                $this->db->table('customer_credit_note_lines')->insert([
                    'customer_credit_note_id' => $id, 'customer_invoice_line_id' => $this->id($line['source']), 'quantity' => $line['quantity'],
                    'description' => $line['description'], 'net_minor' => $line['net'], 'tax_minor' => $line['tax'], 'total_minor' => $line['total'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            activity('customer-credit-note')->causedBy($actor)->event('created')->withProperties(['company_id' => $companyId, 'credit_note_id' => $id, 'customer_invoice_id' => $this->id($invoice), 'total_minor' => $total])->log('Customer credit note created');

            return $id;
        }, 3);
    }

    public function submit(int $companyId, int $id, User $actor): int
    {
        return $this->transition($companyId, $id, $actor, 'draft', 'submitted', 'submitted');
    }

    public function approve(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $note = $this->note($companyId, $id, true);
            if ($this->fieldText($note, 'status') === 'approved') {
                return $id;
            }
            if ($this->fieldText($note, 'status') !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_CREDIT_NOTE_TRANSITION_INVALID']);
            }
            if ($this->fieldInt($note, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }
            $this->db->table('customer_credit_notes')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
            activity('customer-credit-note')->causedBy($actor)->event('approved')->withProperties(['company_id' => $companyId, 'credit_note_id' => $id])->log('Customer credit note approved');

            return $id;
        }, 3);
    }

    public function post(int $companyId, int $id, User $actor): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor): int {
            $note = $this->note($companyId, $id, true);
            $invoice = $this->invoice($companyId, $this->fieldInt($note, 'customer_invoice_id'), true);
            if ($this->fieldText($note, 'status') === 'posted') {
                return $id;
            }
            if ($this->fieldText($note, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_CREDIT_NOTE_TRANSITION_INVALID']);
            }
            $total = $this->fieldInt($note, 'total_minor');
            if ($total > $this->remainingInvoiceCredit($this->id($invoice), $id)) {
                throw ValidationException::withMessages(['total_minor' => 'CUSTOMER_CREDIT_NOTE_EXCEEDS_REMAINING']);
            }
            foreach ($this->db->table('customer_credit_note_lines')->where('customer_credit_note_id', $id)->lockForUpdate()->get() as $line) {
                $source = $this->invoiceLine($this->id($invoice), $this->fieldInt($line, 'customer_invoice_line_id'));
                $this->assertLineRemainder($this->id($source), $source, $this->fieldInt($line, 'net_minor'), $this->fieldInt($line, 'tax_minor'), $this->fieldInt($line, 'total_minor'), $this->nullableFieldInt($line, 'quantity'), $id);
            }
            $allocated = (int) $this->db->table('customer_collection_allocations')->where('customer_invoice_id', $this->id($invoice))->sum('allocated_minor');
            $priorCredits = $this->postedCreditTotal($this->id($invoice), $id);
            $outstanding = max($this->fieldInt($invoice, 'total_minor') - $allocated - $priorCredits, 0);
            $arReduction = min($total, $outstanding);
            $liability = $total - $arReduction;
            $policy = $this->policies->resolve($companyId, 'CUSTOMER_INVOICE_CREDIT_NOTE', null, $this->fieldText($note, 'effective_date'), $this->fieldText($note, 'currency'));
            $amounts = ['REVENUE' => $this->fieldInt($note, 'net_minor'), 'TAX_PAYABLE' => $this->fieldInt($note, 'tax_minor'), 'RECEIVABLE' => $arReduction, 'CUSTOMER_CREDIT_LIABILITY' => $liability];
            $expectedSides = ['REVENUE' => 'debit', 'TAX_PAYABLE' => 'debit', 'RECEIVABLE' => 'credit', 'CUSTOMER_CREDIT_LIABILITY' => 'credit'];
            $lines = [];
            foreach ($policy['lines'] as $policyLine) {
                $role = strtoupper($policyLine['line_role']);
                if (! array_key_exists($role, $amounts) || $policyLine['entry_side'] !== $expectedSides[$role]) {
                    throw ValidationException::withMessages(['accounting_policy' => 'CUSTOMER_CREDIT_NOTE_POLICY_ROLE_INVALID']);
                }
                $amount = $amounts[$role];
                unset($amounts[$role]);
                if ($amount > 0) {
                    $lines[] = ['account_code' => $policyLine['account_code'], 'debit' => $policyLine['entry_side'] === 'debit' ? $amount : 0, 'credit' => $policyLine['entry_side'] === 'credit' ? $amount : 0, 'description' => 'Customer credit note '.$role];
                }
            }
            foreach ($amounts as $role => $amount) {
                if ($amount > 0) {
                    throw ValidationException::withMessages(['accounting_policy' => 'CUSTOMER_CREDIT_NOTE_POLICY_ROLE_MISSING: '.$role]);
                }
            }
            $journal = $this->accounting->postCustomerCreditNote($companyId, $this->fieldInt($note, 'branch_id'), $id, $this->fieldText($note, 'effective_date'), $this->fieldText($note, 'currency'), $lines, $actor->id, $policy['policy_id'], $policy['version']);
            $this->taxReversals->record($companyId, $this->fieldInt($note, 'branch_id'), $id, $this->id($invoice), $this->fieldText($note, 'effective_date'), $this->fieldText($note, 'currency'), $this->fieldInt($note, 'net_minor'), $this->fieldInt($note, 'tax_minor'), $journal);
            $this->db->table('customer_credit_notes')->where('id', $id)->update(['status' => 'posted', 'ar_reduction_minor' => $arReduction, 'customer_credit_liability_minor' => $liability, 'accounting_policy_id' => $policy['policy_id'], 'accounting_policy_version' => $policy['version'], 'accounting_journal_id' => $journal, 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_at' => now()]);
            activity('customer-credit-note')->causedBy($actor)->event('posted')->withProperties(['company_id' => $companyId, 'credit_note_id' => $id, 'ar_reduction_minor' => $arReduction, 'customer_credit_liability_minor' => $liability, 'journal_id' => $journal])->log('Customer credit note posted');

            return $id;
        }, 3);
    }

    private function transition(int $companyId, int $id, User $actor, string $from, string $to, string $event): int
    {
        $this->assertSchema();

        return $this->db->transaction(function () use ($companyId, $id, $actor, $from, $to, $event): int {
            $note = $this->note($companyId, $id, true);
            if ($this->fieldText($note, 'status') === $to) {
                return $id;
            }
            if ($this->fieldText($note, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'CUSTOMER_CREDIT_NOTE_TRANSITION_INVALID']);
            }
            $this->db->table('customer_credit_notes')->where('id', $id)->update(['status' => $to, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'updated_at' => now()]);
            activity('customer-credit-note')->causedBy($actor)->event($event)->withProperties(['company_id' => $companyId, 'credit_note_id' => $id])->log('Customer credit note '.$event);

            return $id;
        }, 3);
    }

    private function assertLineRemainder(int $sourceId, object $source, int $net, int $tax, int $total, ?int $quantity, ?int $exclude = null): void
    {
        $posted = $this->db->table('customer_credit_note_lines as l')->join('customer_credit_notes as n', 'n.id', '=', 'l.customer_credit_note_id')->where(['l.customer_invoice_line_id' => $sourceId, 'n.status' => 'posted'])->when($exclude !== null, fn (Builder $q): Builder => $q->where('n.id', '<>', $exclude));
        if ($net > max($this->fieldInt($source, 'line_total_minor') - $this->fieldInt($source, 'tax_minor') - (int) (clone $posted)->sum('l.net_minor'), 0)
            || $tax > max($this->fieldInt($source, 'tax_minor') - (int) (clone $posted)->sum('l.tax_minor'), 0)
            || $total > max($this->fieldInt($source, 'line_total_minor') - (int) (clone $posted)->sum('l.total_minor'), 0)) {
            throw ValidationException::withMessages(['lines' => 'CUSTOMER_CREDIT_NOTE_LINE_EXCEEDS_REMAINING']);
        }
        if ($quantity !== null && $quantity > max($this->fieldInt($source, 'quantity') - (int) (clone $posted)->whereNotNull('l.quantity')->sum('l.quantity'), 0)) {
            throw ValidationException::withMessages(['quantity' => 'CUSTOMER_CREDIT_NOTE_QUANTITY_EXCEEDS_REMAINING']);
        }
    }

    private function remainingInvoiceCredit(int $invoiceId, ?int $exclude = null): int
    {
        $invoice = $this->db->table('customer_invoices')->select('total_minor')->where('id', $invoiceId)->firstOrFail();
        $total = $this->fieldInt($invoice, 'total_minor');

        return max($total - $this->postedCreditTotal($invoiceId, $exclude), 0);
    }

    private function postedCreditTotal(int $invoiceId, ?int $exclude = null): int
    {
        return (int) $this->db->table('customer_credit_notes')->where(['customer_invoice_id' => $invoiceId, 'status' => 'posted'])->when($exclude !== null, fn (Builder $q): Builder => $q->where('id', '<>', $exclude))->sum('total_minor');
    }

    private function invoice(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('customer_invoices')->where(['id' => $id, 'company_id' => $companyId])->whereIn('status', ['issued', 'partially_paid', 'paid']);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function invoiceLine(int $invoiceId, int $id): object
    {
        return $this->db->table('customer_invoice_lines')->where(['id' => $id, 'customer_invoice_id' => $invoiceId])->lockForUpdate()->firstOrFail();
    }

    private function note(int $companyId, int $id, bool $lock): object
    {
        $query = $this->db->table('customer_credit_notes')->where(['id' => $id, 'company_id' => $companyId]);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function assertSchema(): void
    {
        if (! Schema::hasTable('customer_credit_notes')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$key => 'required']);
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $v = $data[$key] ?? null;

        return is_int($v) && $v > 0 ? $v : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayText(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$key => 'required']);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayPositive(array $data, string $key): int
    {
        $v = $data[$key] ?? null;

        return is_int($v) && $v > 0 ? $v : throw ValidationException::withMessages([$key => 'positive integer required']);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayNonNegative(array $data, string $key): int
    {
        $v = $data[$key] ?? null;

        return is_int($v) && $v >= 0 ? $v : throw ValidationException::withMessages([$key => 'non-negative integer required']);
    }

    /** @param array<mixed,mixed> $data */
    private function arrayNullablePositive(array $data, string $key): ?int
    {
        $v = $data[$key] ?? null;

        return $v === null || $v === '' ? null : (is_int($v) && $v > 0 ? $v : throw ValidationException::withMessages([$key => 'positive integer required']));
    }

    private function id(object $row): int
    {
        return $this->fieldInt($row, 'id');
    }

    private function fieldInt(object $row, string $key): int
    {
        $v = $row->{$key} ?? null;

        return is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : throw new \RuntimeException('Invalid persisted integer.'));
    }

    private function nullableFieldInt(object $row, string $key): ?int
    {
        $v = $row->{$key} ?? null;

        return $v === null ? null : $this->fieldInt($row, $key);
    }

    private function fieldText(object $row, string $key): string
    {
        $v = $row->{$key} ?? null;

        return is_string($v) && $v !== '' ? $v : throw new \RuntimeException('Invalid persisted text.');
    }
}
