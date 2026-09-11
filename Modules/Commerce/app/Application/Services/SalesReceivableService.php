<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryService;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class SalesReceivableService
{
    public function __construct(private InventoryService $inventory, private SourceAccountingPostingService $accounting, private TreasuryService $treasury) {}

    /** @param array<string,mixed> $d */
    public function createQuotation(int $company, User $actor, array $d): int
    {
        return DB::transaction(function () use ($company, $actor, $d): int {
            $branch = $this->owned('branches', $company, $this->int($d, 'branch_id'));
            $customer = $this->owned('commerce_customers', $company, $this->int($d, 'customer_id'));
            unset($customer);
            $variant = DB::table('product_variants as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->join('stores as s', 's.id', '=', 'p.store_id')
                ->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
                ->where('v.id', $this->int($d, 'product_variant_id'))
                ->where('sp.company_id', $company)
                ->select('v.*')->firstOrFail();
            $qty = $this->positive($d, 'quantity');
            $price = $this->positive($d, 'unit_price_minor');
            $discount = $this->int($d, 'discount_minor', 0);
            $total = $qty * $price - $discount;
            if ($total < 0) {
                throw ValidationException::withMessages(['discount_minor' => 'Discount vượt giá trị line.']);
            } $id = (int) DB::table('sales_quotations')->insertGetId(['company_id' => $company, 'branch_id' => $this->id($branch), 'customer_id' => $this->int($d, 'customer_id'), 'reference' => $this->text($d, 'reference'), 'quotation_date' => $this->text($d, 'quotation_date'), 'valid_until' => $this->text($d, 'valid_until'), 'currency' => 'VND', 'subtotal_minor' => $qty * $price, 'discount_minor' => $discount, 'tax_minor' => 0, 'total_minor' => $total, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sales_quotation_lines')->insert(['quotation_id' => $id, 'product_variant_id' => $this->id($variant), 'description' => $this->text($d, 'description'), 'quantity' => $qty, 'unit_price_minor' => $price, 'discount_minor' => $discount, 'tax_minor' => 0, 'line_total_minor' => $total, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function acceptQuotation(int $company, User $actor, array $d): int
    {
        $id = $this->positive($d, 'id');

        return DB::transaction(function () use ($company, $actor, $id): int {
            $q = DB::table('sales_quotations')->where(['id' => $id, 'company_id' => $company])->lockForUpdate()->firstOrFail();
            if ($this->str($q, 'status') === 'accepted') {
                return $id;
            } if ($this->str($q, 'status') !== 'draft' || $this->id($q, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            } DB::table('sales_quotations')->where('id', $id)->update(['status' => 'accepted', 'approved_by' => $actor->id, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function convertQuotationToSalesOrder(int $company, User $actor, array $d): int
    {
        $qid = $this->positive($d, 'quotation_id');

        return DB::transaction(function () use ($company, $actor, $qid, $d): int {
            $existing = DB::table('sales_orders')->where('quotation_id', $qid)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$q = DB::table('sales_quotations')->where(['id' => $qid, 'company_id' => $company, 'status' => 'accepted'])->lockForUpdate()->firstOrFail();
            $id = (int) DB::table('sales_orders')->insertGetId(['company_id' => $company, 'branch_id' => $this->id($q, 'branch_id'), 'customer_id' => $this->id($q, 'customer_id'), 'quotation_id' => $qid, 'reference' => $this->text($d, 'reference'), 'order_date' => $this->text($d, 'order_date'), 'currency' => $this->str($q, 'currency'), 'subtotal_minor' => $this->id($q, 'subtotal_minor'), 'discount_minor' => $this->id($q, 'discount_minor'), 'tax_minor' => $this->id($q, 'tax_minor'), 'total_minor' => $this->id($q, 'total_minor'), 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('sales_quotation_lines')->where('quotation_id', $qid)->get() as $l) {
                $sku = DB::table('product_variants')->where('id', $this->id($l, 'product_variant_id'))->value('sku');
                DB::table('sales_order_lines')->insert(['sales_order_id' => $id, 'product_variant_id' => $this->id($l, 'product_variant_id'), 'sku_snapshot' => is_string($sku) ? $sku : null, 'description' => $this->str($l, 'description'), 'quantity' => $this->id($l, 'quantity'), 'unit_price_minor' => $this->id($l, 'unit_price_minor'), 'discount_minor' => $this->id($l, 'discount_minor'), 'tax_minor' => 0, 'net_minor' => $this->id($l, 'line_total_minor'), 'created_at' => now(), 'updated_at' => now()]);
            }DB::table('sales_quotations')->where('id', $qid)->update(['status' => 'converted', 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function confirmSalesOrder(int $company, User $actor, array $d): int
    {
        $id = $this->positive($d, 'id');
        $order = DB::table('sales_orders')->where(['id' => $id, 'company_id' => $company])->firstOrFail();
        if ($this->str($order, 'status') === 'confirmed') {
            return $id;
        }if ($this->str($order, 'status') !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Chỉ order draft được confirm.']);
        }DB::table('sales_orders')->where('id', $id)->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** @param array<string,mixed> $d */
    public function fulfillSalesOrder(int $company, User $actor, array $d): int
    {
        $lineId = $this->positive($d, 'sales_order_line_id');

        return DB::transaction(function () use ($company, $actor, $d, $lineId): int {
            $line = DB::table('sales_order_lines as l')->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')->where(['l.id' => $lineId, 'o.company_id' => $company])->whereIn('o.status', ['confirmed', 'partially_fulfilled'])->lockForUpdate()->select(['l.*', 'o.branch_id', 'o.currency'])->firstOrFail();
            $qty = $this->positive($d, 'quantity');
            if ($this->id($line, 'fulfilled_quantity') + $qty > $this->id($line, 'quantity')) {
                throw ValidationException::withMessages(['quantity' => 'Fulfillment vượt Sales Order.']);
            }$location = $this->positive($d, 'inventory_location_id');
            DB::table('inventory_locations')->where(['id' => $location, 'branch_id' => $this->id($line, 'branch_id')])->firstOrFail();
            $balance = DB::table('inventory_balances')->where(['product_variant_id' => $this->id($line, 'product_variant_id'), 'inventory_location_id' => $location])->lockForUpdate()->firstOrFail();
            $unit = $this->id($balance, 'average_unit_cost_minor');
            $ref = $this->text($d, 'reference');
            $existing = DB::table('sales_fulfillments')->where('reference', $ref)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$id = (int) DB::table('sales_fulfillments')->insertGetId(['company_id' => $company, 'sales_order_id' => $this->id($line, 'sales_order_id'), 'sales_order_line_id' => $lineId, 'inventory_location_id' => $location, 'reference' => $ref, 'quantity' => $qty, 'unit_cogs_minor' => $unit, 'total_cogs_minor' => $qty * $unit, 'fulfilled_on' => $this->text($d, 'fulfilled_on'), 'status' => 'fulfilled', 'fulfilled_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->inventory->adjust($this->id($line, 'product_variant_id'), $location, 'decrease', $qty, ['source_type' => 'sales_fulfillment', 'source_id' => $id, 'reference' => $ref, 'idempotency_key' => 'sales-fulfillment-'.$id, 'actor_id' => $actor->id, 'reason' => 'ERP Sales fulfillment', 'unit_cost_minor' => $unit]);
            $movement = DB::table('inventory_movements')->where('idempotency_key', 'sales-fulfillment-'.$id)->value('id');
            DB::table('sales_fulfillments')->where('id', $id)->update(['inventory_movement_id' => $movement, 'updated_at' => now()]);
            DB::table('sales_order_lines')->where('id', $lineId)->update(['fulfilled_quantity' => $this->id($line, 'fulfilled_quantity') + $qty, 'cogs_minor' => $this->id($line, 'cogs_minor') + ($qty * $unit), 'updated_at' => now()]);
            $remaining = DB::table('sales_order_lines')->where('sales_order_id', $this->id($line, 'sales_order_id'))->whereColumn('fulfilled_quantity', '<', 'quantity')->exists();
            DB::table('sales_orders')->where('id', $this->id($line, 'sales_order_id'))->update(['status' => $remaining ? 'partially_fulfilled' : 'fulfilled', 'updated_at' => now()]);
            $this->accounting->post($company, $this->id($line, 'branch_id'), 'sales_fulfillment', $id, 'erp_sales_cogs', $this->text($d, 'fulfilled_on'), $this->str($line, 'currency'), [['account_code' => '5000', 'debit' => $qty * $unit, 'credit' => 0, 'description' => 'ERP Sales COGS'], ['account_code' => '1200', 'debit' => 0, 'credit' => $qty * $unit, 'description' => 'Inventory issued']], $actor->id);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function createCustomerInvoice(int $company, User $actor, array $d): int
    {
        $orderId = $this->positive($d, 'sales_order_id');

        return DB::transaction(function () use ($company, $actor, $d, $orderId): int {
            $existing = DB::table('customer_invoices')->where('sales_order_id', $orderId)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$o = DB::table('sales_orders')->where(['id' => $orderId, 'company_id' => $company, 'status' => 'fulfilled'])->firstOrFail();
            $id = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company, 'branch_id' => $this->id($o, 'branch_id'), 'customer_id' => $this->id($o, 'customer_id'), 'sales_order_id' => $orderId, 'invoice_number' => $this->text($d, 'invoice_number'), 'invoice_date' => $this->text($d, 'invoice_date'), 'due_date' => $this->text($d, 'due_date'), 'currency' => $this->str($o, 'currency'), 'subtotal_minor' => $this->id($o, 'subtotal_minor'), 'discount_minor' => $this->id($o, 'discount_minor'), 'tax_minor' => 0, 'total_minor' => $this->id($o, 'total_minor'), 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('sales_order_lines')->where('sales_order_id', $orderId)->get() as $l) {
                DB::table('customer_invoice_lines')->insert(['customer_invoice_id' => $id, 'sales_order_line_id' => $this->id($l, 'id'), 'product_variant_id' => $this->id($l, 'product_variant_id'), 'description' => $this->str($l, 'description'), 'quantity' => $this->id($l, 'quantity'), 'unit_price_minor' => $this->id($l, 'unit_price_minor'), 'discount_minor' => $this->id($l, 'discount_minor'), 'tax_minor' => 0, 'line_total_minor' => $this->id($l, 'net_minor'), 'cogs_minor' => $this->id($l, 'cogs_minor'), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function issueCustomerInvoice(int $company, User $actor, array $d): int
    {
        $id = $this->positive($d, 'id');

        return DB::transaction(function () use ($company, $actor, $id): int {
            $i = DB::table('customer_invoices')->where(['id' => $id, 'company_id' => $company])->lockForUpdate()->firstOrFail();
            if (in_array($this->str($i, 'status'), ['issued', 'partially_paid', 'paid'], true)) {
                return $id;
            }if ($this->str($i, 'status') !== 'draft' || $this->id($i, 'created_by') === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
            }$amount = $this->id($i, 'total_minor');
            $journal = $this->accounting->post($company, $this->id($i, 'branch_id'), 'customer_invoice', $id, 'erp_sales_invoice', $this->str($i, 'invoice_date'), $this->str($i, 'currency'), [['account_code' => '1150', 'debit' => $amount, 'credit' => 0, 'description' => 'Customer receivable'], ['account_code' => '4000', 'debit' => 0, 'credit' => $amount, 'description' => 'ERP Sales revenue']], $actor->id);
            DB::table('customer_invoices')->where('id', $id)->update(['status' => 'issued', 'accounting_journal_id' => $journal, 'issued_by' => $actor->id, 'issued_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    public function allocateCustomerCollection(int $company, User $actor, array $d): int
    {
        $invoiceId = $this->positive($d, 'customer_invoice_id');

        return DB::transaction(function () use ($company, $actor, $d, $invoiceId): int {
            $i = DB::table('customer_invoices')->where(['id' => $invoiceId, 'company_id' => $company])->whereIn('status', ['issued', 'partially_paid'])->lockForUpdate()->firstOrFail();
            $amount = $this->positive($d, 'amount_minor');
            $outstanding = $this->id($i, 'total_minor') - (int) DB::table('customer_collection_allocations')->where('customer_invoice_id', $invoiceId)->sum('allocated_minor');
            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount_minor' => 'Collection vượt Invoice outstanding.']);
            }$ref = $this->text($d, 'reference');
            $existing = DB::table('customer_collections')->where('reference', $ref)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$id = (int) DB::table('customer_collections')->insertGetId(['company_id' => $company, 'branch_id' => $this->id($i, 'branch_id'), 'customer_id' => $this->id($i, 'customer_id'), 'reference' => $ref, 'collection_date' => $this->text($d, 'collection_date'), 'amount_minor' => $amount, 'allocated_minor' => $amount, 'currency' => $this->str($i, 'currency'), 'method' => $this->text($d, 'method'), 'status' => 'recorded', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('customer_collection_allocations')->insert(['customer_collection_id' => $id, 'customer_invoice_id' => $invoiceId, 'allocated_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            $journal = $this->accounting->post($company, $this->id($i, 'branch_id'), 'customer_collection', $id, 'customer_collection', $this->text($d, 'collection_date'), $this->str($i, 'currency'), [['account_code' => '1100', 'debit' => $amount, 'credit' => 0, 'description' => 'Customer cash collection'], ['account_code' => '1150', 'debit' => 0, 'credit' => $amount, 'description' => 'Settle customer receivable']], $actor->id);
            $treasury = $this->treasury->recordSource($company, $this->id($i, 'branch_id'), 'customer_collection', $id, $journal, $ref, $amount, $this->text($d, 'collection_date').' 12:00:00', $actor->id, 'inflow');
            $paid = $this->id($i, 'paid_minor') + $amount;
            DB::table('customer_invoices')->where('id', $invoiceId)->update(['paid_minor' => $paid, 'status' => $paid === $this->id($i, 'total_minor') ? 'paid' : 'partially_paid', 'updated_at' => now()]);
            DB::table('customer_collections')->where('id', $id)->update(['accounting_journal_id' => $journal, 'treasury_transaction_id' => $treasury, 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function owned(string $table, int $company, int $id): object
    {
        return DB::table($table)->where(['id' => $id, 'company_id' => $company])->firstOrFail();
    }

    /** @param array<string,mixed> $d */
    private function int(array $d, string $k, int $default = -1): int
    {
        $v = $d[$k] ?? $default;

        return is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : throw ValidationException::withMessages([$k => 'Số không hợp lệ.']));
    }

    /** @param array<string,mixed> $d */
    private function positive(array $d, string $k): int
    {
        $v = $this->int($d, $k);

        return $v > 0 ? $v : throw ValidationException::withMessages([$k => 'Phải lớn hơn 0.']);
    }

    /** @param array<string,mixed> $d */
    private function text(array $d, string $k): string
    {
        $v = $d[$k] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$k => 'Bắt buộc.']);
    }

    private function id(object $o, string $k = 'id'): int
    {
        $v = data_get($o, $k);

        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : throw new \RuntimeException('Invalid persisted integer.'));
    }

    private function str(object $o, string $k): string
    {
        $v = data_get($o, $k);

        return is_string($v) ? $v : throw new \RuntimeException('Invalid persisted string.');
    }
}
