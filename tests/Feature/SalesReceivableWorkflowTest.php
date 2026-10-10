<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\CustomerCreditNoteService;
use Modules\Commerce\Application\Services\CustomerRefundService;
use Modules\Commerce\Application\Services\SalesReceivableService;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FinancialStatementReadService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Tests\TestCase;

final class SalesReceivableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_to_ar_collection_treasury_and_accounting_is_canonical_and_idempotent(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant, $location] = $this->runtime();
        $service = app(SalesReceivableService::class);
        $quotation = $service->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'reference' => 'QT-SALES-001', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Thiết bị bán doanh nghiệp', 'quantity' => 4, 'unit_price_minor' => 500, 'discount_minor' => 100]);
        $this->selfApprovalIsBlocked($service, $company, $maker, 'quotation.accept', ['id' => $quotation]);
        $service->acceptQuotation($company, $checker, ['id' => $quotation]);

        $order = $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-SALES-001', 'order_date' => '2026-09-02']);
        self::assertSame($order, $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-SALES-RETRY', 'order_date' => '2026-09-02']));
        $service->confirmSalesOrder($company, $maker, ['id' => $order]);
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));

        $line = (int) DB::table('sales_order_lines')->where('sales_order_id', $order)->value('id');
        $fulfillment = $service->fulfillSalesOrder($company, $maker, ['sales_order_line_id' => $line, 'inventory_location_id' => $location, 'quantity' => 4, 'reference' => 'FUL-SALES-001', 'fulfilled_on' => '2026-09-03']);
        self::assertSame(6, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(400, (int) DB::table('sales_fulfillments')->where('id', $fulfillment)->value('total_cogs_minor'));
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'sales-fulfillment-'.$fulfillment)->count());

        $invoice = $service->createCustomerInvoice($company, $maker, ['sales_order_id' => $order, 'invoice_number' => 'CI-SALES-001', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04']);
        $this->selfApprovalIsBlocked($service, $company, $maker, 'invoice.issue', ['id' => $invoice]);
        $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]);
        self::assertSame('configuration_required', DB::table('customer_invoices')->where('id', $invoice)->value('tax_status'));
        self::assertDatabaseHas('customer_invoice_fx_recognitions', ['customer_invoice_id' => $invoice, 'transaction_currency' => 'VND', 'functional_currency' => 'VND', 'functional_amount_minor' => 1900, 'fx_rate_snapshot_id' => null]);

        $first = $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-001', 'collection_date' => '2026-09-10', 'amount_minor' => 700, 'method' => 'bank_transfer']);
        self::assertSame($first, $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-001', 'collection_date' => '2026-09-10', 'amount_minor' => 700, 'method' => 'bank_transfer']));
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-SALES-002', 'collection_date' => '2026-09-15', 'amount_minor' => 1200, 'method' => 'bank_transfer']);

        self::assertSame('paid', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
        self::assertSame(1900, (int) DB::table('customer_collection_allocations')->where('customer_invoice_id', $invoice)->sum('allocated_minor'));
        self::assertSame(2, DB::table('customer_collections')->where('company_id', $company)->count());
        self::assertSame(2, DB::table('treasury_transactions')->where(['company_id' => $company, 'source_type' => 'customer_collection', 'direction' => 'inflow'])->count());
        self::assertSame(0, (int) DB::table('payroll_accounting_journals')->where('company_id', $company)->selectRaw('SUM(total_debit_minor-total_credit_minor) difference')->value('difference'));
        $statements = app(FinancialStatementReadService::class)->report($company, null, null, null, 1);
        self::assertSame(0, $statements['totals']['difference']);
        self::assertSame(1900, $statements['cash_flow']['customer_collection_inflow']);
        self::assertSame(1900, $statements['cash_flow']['operating_inflow']);
        self::assertSame(0, $statements['cash_flow']['unclassified']);
        self::assertSame(1500, $statements['income_statement']['gross_profit']);
    }

    public function test_cross_company_product_variant_is_rejected(): void
    {
        [$maker, , $company, $branch, $customer] = $this->runtime();
        [, , , , , $foreignVariant] = $this->runtime('OTHER');

        $this->expectException(RecordNotFoundException::class);
        app(SalesReceivableService::class)->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $foreignVariant, 'reference' => 'QT-CROSS', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Cross tenant', 'quantity' => 1, 'unit_price_minor' => 500, 'discount_minor' => 0]);
    }

    public function test_foreign_invoice_issue_recognizes_spot_rate_and_posts_functional_currency_journal(): void
    {
        [$maker, $checker, $company, $branch, $customer] = $this->runtime('ARFX-ISSUE');
        $invoice = $this->draftInvoice($company, $branch, $customer, $maker, 'USD', 10_000, 'CI-USD-001');
        $this->activeSpotRate($company, $maker, $checker, 'USD', 'VND', '24690.500000000000');

        $service = app(SalesReceivableService::class);
        self::assertSame($invoice, $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]));
        self::assertSame($invoice, $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]));

        $recognition = DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->firstOrFail();
        self::assertSame(10_000, (int) $recognition->transaction_amount_minor);
        self::assertSame(2_469_050, (int) $recognition->functional_amount_minor);
        self::assertNotNull($recognition->fx_rate_snapshot_id);
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->firstOrFail();
        self::assertSame('VND', $journal->currency);
        self::assertSame(2_469_050, (int) $journal->total_debit_minor);
        self::assertSame((int) $journal->total_debit_minor, (int) $journal->total_credit_minor);
        self::assertSame(1, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->count());
        self::assertSame('USD', DB::table('customer_invoices')->where('id', $invoice)->value('currency'));
    }

    public function test_foreign_invoice_issue_without_spot_rate_rolls_back_all_evidence_and_state(): void
    {
        [$maker, $checker, $company, $branch, $customer] = $this->runtime('ARFX-NORATE');
        $invoice = $this->draftInvoice($company, $branch, $customer, $maker, 'USD', 10_000, 'CI-USD-NORATE');

        $this->assertValidation(fn () => app(SalesReceivableService::class)->issueCustomerInvoice($company, $checker, ['id' => $invoice]), 'rate');

        self::assertSame('draft', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
        self::assertSame(0, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());
        self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'customer_invoice_recognition', 'source_id' => $invoice])->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->count());
    }

    public function test_invoice_issue_without_functional_currency_rolls_back_and_currency_lineage_is_exact(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant] = $this->runtime('ARFX-NOFUNC');
        DB::table('companies')->where('id', $company)->update(['functional_currency_code' => null]);
        $service = app(SalesReceivableService::class);
        $quotation = $service->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'currency' => 'USD', 'reference' => 'QT-USD-LINEAGE', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Foreign lineage', 'quantity' => 1, 'unit_price_minor' => 10_000, 'discount_minor' => 0]);
        $service->acceptQuotation($company, $checker, ['id' => $quotation]);
        $order = $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-USD-LINEAGE', 'order_date' => '2026-09-02']);
        self::assertSame('USD', DB::table('sales_quotations')->where('id', $quotation)->value('currency'));
        self::assertSame('USD', DB::table('sales_orders')->where('id', $order)->value('currency'));

        $invoice = $this->draftInvoice($company, $branch, $customer, $maker, 'USD', 10_000, 'CI-USD-NOFUNC');
        $this->assertValidation(fn () => $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]), 'functional_currency');
        self::assertSame('draft', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
        self::assertSame(0, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->count());
    }

    public function test_quotation_rejects_unsupported_currency(): void
    {
        [$maker, , $company, $branch, $customer, $variant] = $this->runtime('ARFX-BAD-CURRENCY');

        $this->assertValidation(fn () => app(SalesReceivableService::class)->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'currency' => 'XYZ', 'reference' => 'QT-BAD-CURRENCY', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Unsupported', 'quantity' => 1, 'unit_price_minor' => 100, 'discount_minor' => 0]), 'currency');
        self::assertSame(0, DB::table('sales_quotations')->where(['company_id' => $company, 'reference' => 'QT-BAD-CURRENCY'])->count());
    }

    public function test_customer_invoice_vat_posts_three_balanced_lines_and_immutable_evidence(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant, $location] = $this->runtime('VAT');
        $this->vatPolicy($company, $maker, $checker, 'VAT_OUTPUT', 'output', '2180', 'VAT Payable');
        $service = app(SalesReceivableService::class);
        $quotation = $service->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'reference' => 'QT-VAT-001', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'VAT source', 'quantity' => 2, 'unit_price_minor' => 5_000_000, 'discount_minor' => 0]);
        $service->acceptQuotation($company, $checker, ['id' => $quotation]);
        $order = $service->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-VAT-001', 'order_date' => '2026-09-02']);
        $service->confirmSalesOrder($company, $maker, ['id' => $order]);
        $line = (int) DB::table('sales_order_lines')->where('sales_order_id', $order)->value('id');
        $service->fulfillSalesOrder($company, $maker, ['sales_order_line_id' => $line, 'inventory_location_id' => $location, 'quantity' => 2, 'reference' => 'FUL-VAT-001', 'fulfilled_on' => '2026-09-03']);
        $invoice = $service->createCustomerInvoice($company, $maker, ['sales_order_id' => $order, 'invoice_number' => 'CI-VAT-001', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04', 'tax_category_code' => 'VAT_OUTPUT']);
        $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]);
        self::assertSame($invoice, $service->issueCustomerInvoice($company, $checker, ['id' => $invoice]));

        $stored = DB::table('customer_invoices')->where('id', $invoice)->firstOrFail();
        self::assertSame(10_000_000, (int) $stored->subtotal_minor);
        self::assertSame(1_000_000, (int) $stored->tax_minor);
        self::assertSame(11_000_000, (int) $stored->total_minor);
        self::assertSame('configured', $stored->tax_status);
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->firstOrFail();
        self::assertSame(11_000_000, (int) $journal->total_debit_minor);
        self::assertSame(11_000_000, (int) $journal->total_credit_minor);
        self::assertSame(3, (int) $journal->line_count);
        self::assertSame(1, DB::table('tax_transaction_evidence')->where(['company_id' => $company, 'branch_id' => $branch, 'source_type' => 'customer_invoice', 'source_id' => $invoice, 'accounting_journal_id' => $journal->id])->count());

        $this->creditNotePolicy($company, $maker, $checker);
        $invoiceLine = DB::table('customer_invoice_lines')->where('customer_invoice_id', $invoice)->firstOrFail();
        $originalEvidence = DB::table('tax_transaction_evidence')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->firstOrFail();
        $credit = app(CustomerCreditNoteService::class);
        $note = $credit->create($company, $maker, ['customer_invoice_id' => $invoice, 'credit_note_number' => 'CN-VAT-001', 'effective_date' => '2026-09-20', 'reason' => 'VAT reversal', 'idempotency_key' => 'credit-note-vat-001', 'lines' => [['customer_invoice_line_id' => (int) $invoiceLine->id, 'quantity' => 1, 'net_minor' => 5_000_000, 'tax_minor' => 500_000, 'total_minor' => 5_500_000, 'description' => 'Partial VAT reversal']]]);
        $credit->submit($company, $note, $maker);
        $credit->approve($company, $note, $checker);
        $credit->post($company, $note, $checker);
        self::assertSame($note, $credit->post($company, $note, $checker));

        $reversal = DB::table('tax_transaction_evidence')->where(['company_id' => $company, 'source_type' => 'customer_credit_note', 'source_id' => $note])->firstOrFail();
        self::assertSame((int) $originalEvidence->tax_policy_id, (int) $reversal->tax_policy_id);
        self::assertSame((int) $originalEvidence->policy_version, (int) $reversal->policy_version);
        self::assertSame(5_000_000, (int) $reversal->taxable_amount_minor);
        self::assertSame(500_000, (int) $reversal->tax_amount_minor);
        self::assertSame(1, DB::table('tax_transaction_evidence')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
        self::assertEquals($originalEvidence, DB::table('tax_transaction_evidence')->where('id', $originalEvidence->id)->firstOrFail());
        $mapping = json_decode((string) $reversal->mapping_snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame((int) $originalEvidence->id, $mapping['reversal_of_tax_evidence_id']);
        self::assertSame('vat_reversal', $mapping['event']);
    }

    public function test_customer_credit_note_corrects_ar_and_preserves_cash_inventory_and_original_evidence(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant, $location] = $this->runtime('CREDIT');
        $sales = app(SalesReceivableService::class);
        $quotation = $sales->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'reference' => 'QT-CREDIT-001', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Credit note source', 'quantity' => 4, 'unit_price_minor' => 500, 'discount_minor' => 100]);
        $sales->acceptQuotation($company, $checker, ['id' => $quotation]);
        $order = $sales->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-CREDIT-001', 'order_date' => '2026-09-02']);
        $sales->confirmSalesOrder($company, $maker, ['id' => $order]);
        $orderLine = (int) DB::table('sales_order_lines')->where('sales_order_id', $order)->value('id');
        $sales->fulfillSalesOrder($company, $maker, ['sales_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'quantity' => 4, 'reference' => 'FUL-CREDIT-001', 'fulfilled_on' => '2026-09-03']);
        $invoice = $sales->createCustomerInvoice($company, $maker, ['sales_order_id' => $order, 'invoice_number' => 'CI-CREDIT-001', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04']);
        $sales->issueCustomerInvoice($company, $checker, ['id' => $invoice]);
        $sales->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-CREDIT-001', 'collection_date' => '2026-09-10', 'amount_minor' => 1500, 'method' => 'bank_transfer']);

        $this->creditNotePolicy($company, $maker, $checker);
        $invoiceLine = DB::table('customer_invoice_lines')->where('customer_invoice_id', $invoice)->firstOrFail();
        $originalInvoice = DB::table('customer_invoices')->where('id', $invoice)->firstOrFail();
        $originalJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->firstOrFail();
        $treasuryBefore = DB::table('treasury_transactions')->count();
        $movementsBefore = DB::table('inventory_movements')->count();
        $credit = app(CustomerCreditNoteService::class);
        $payload = ['customer_invoice_id' => $invoice, 'credit_note_number' => 'CN-CREDIT-001', 'effective_date' => '2026-09-20', 'reason' => 'Commercial correction', 'idempotency_key' => 'credit-note-001', 'lines' => [['customer_invoice_line_id' => (int) $invoiceLine->id, 'quantity' => 2, 'net_minor' => 1000, 'tax_minor' => 0, 'total_minor' => 1000, 'description' => 'Partial credit']]];
        $note = $credit->create($company, $maker, $payload);
        self::assertSame($note, $credit->create($company, $maker, $payload));
        $credit->submit($company, $note, $maker);
        $credit->approve($company, $note, $checker);
        $journal = $credit->post($company, $note, $checker);
        self::assertSame($note, $credit->post($company, $note, $checker));

        $stored = DB::table('customer_credit_notes')->where('id', $note)->firstOrFail();
        self::assertSame(400, (int) $stored->ar_reduction_minor);
        self::assertSame(600, (int) $stored->customer_credit_liability_minor);
        self::assertSame('posted', $stored->status);
        self::assertNotNull($stored->accounting_policy_id);
        self::assertSame(1000, (int) DB::table('payroll_accounting_journals')->where('id', $stored->accounting_journal_id)->value('total_debit_minor'));
        self::assertSame(1000, (int) DB::table('payroll_accounting_journals')->where('id', $stored->accounting_journal_id)->value('total_credit_minor'));
        self::assertSame($treasuryBefore, DB::table('treasury_transactions')->count());
        self::assertSame($movementsBefore, DB::table('inventory_movements')->count());
        self::assertEquals($originalInvoice, DB::table('customer_invoices')->where('id', $invoice)->firstOrFail());
        self::assertEquals($originalJournal, DB::table('payroll_accounting_journals')->where('id', $originalJournal->id)->firstOrFail());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_credit_note', 'source_id' => $note])->count());
    }

    public function test_customer_refund_settles_credit_liability_through_real_bank_treasury_and_balanced_journal(): void
    {
        [$maker, $checker, $company, $branch, $customer, $variant, $location] = $this->runtime('REFUND');
        $sales = app(SalesReceivableService::class);
        $quotation = $sales->createQuotation($company, $maker, ['branch_id' => $branch, 'customer_id' => $customer, 'product_variant_id' => $variant, 'reference' => 'QT-REFUND-001', 'quotation_date' => '2026-09-01', 'valid_until' => '2026-09-30', 'description' => 'Refund source', 'quantity' => 4, 'unit_price_minor' => 500, 'discount_minor' => 100]);
        $sales->acceptQuotation($company, $checker, ['id' => $quotation]);
        $order = $sales->convertQuotationToSalesOrder($company, $maker, ['quotation_id' => $quotation, 'reference' => 'SO-REFUND-001', 'order_date' => '2026-09-02']);
        $sales->confirmSalesOrder($company, $maker, ['id' => $order]);
        $orderLine = (int) DB::table('sales_order_lines')->where('sales_order_id', $order)->value('id');
        $sales->fulfillSalesOrder($company, $maker, ['sales_order_line_id' => $orderLine, 'inventory_location_id' => $location, 'quantity' => 4, 'reference' => 'FUL-REFUND-001', 'fulfilled_on' => '2026-09-03']);
        $invoice = $sales->createCustomerInvoice($company, $maker, ['sales_order_id' => $order, 'invoice_number' => 'CI-REFUND-001', 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04']);
        $sales->issueCustomerInvoice($company, $checker, ['id' => $invoice]);
        $collection = $sales->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'COL-REFUND-001', 'collection_date' => '2026-09-10', 'amount_minor' => 1900, 'method' => 'bank_transfer']);
        $this->creditNotePolicy($company, $maker, $checker);
        $invoiceLine = DB::table('customer_invoice_lines')->where('customer_invoice_id', $invoice)->firstOrFail();
        $credit = app(CustomerCreditNoteService::class);
        $note = $credit->create($company, $maker, ['customer_invoice_id' => $invoice, 'credit_note_number' => 'CN-REFUND-001', 'effective_date' => '2026-09-20', 'reason' => 'Refundable customer credit', 'idempotency_key' => 'credit-refund-001', 'lines' => [['customer_invoice_line_id' => (int) $invoiceLine->id, 'quantity' => 2, 'net_minor' => 1000, 'tax_minor' => 0, 'total_minor' => 1000, 'description' => 'Refundable credit']]]);
        $credit->submit($company, $note, $maker);
        $credit->approve($company, $note, $checker);
        $credit->post($company, $note, $checker);
        self::assertSame(1000, (int) DB::table('customer_credit_notes')->where('id', $note)->value('customer_credit_liability_minor'));

        $bank = $this->refundPolicyAndAccount($company, $branch, $maker, $checker);
        $invoiceBefore = DB::table('customer_invoices')->where('id', $invoice)->firstOrFail();
        $creditBefore = DB::table('customer_credit_notes')->where('id', $note)->firstOrFail();
        $collectionBefore = DB::table('customer_collections')->where('id', $collection)->firstOrFail();
        $refunds = app(CustomerRefundService::class);
        $partialPayload = ['financial_account_id' => $bank, 'reference' => 'REF-400', 'refund_date' => '2026-09-21', 'amount_minor' => 400, 'idempotency_key' => 'refund-partial-001', 'allocations' => [['customer_credit_note_id' => $note, 'allocated_minor' => 400]]];
        $partial = $refunds->create($company, $maker, $partialPayload);
        self::assertSame($partial, $refunds->create($company, $maker, $partialPayload));
        $refunds->submit($company, $partial, $maker);
        $refunds->approve($company, $partial, $checker);
        $refunds->post($company, $partial, $checker);
        self::assertSame(600, 1000 - (int) DB::table('customer_refund_allocations as allocation')->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')->where(['allocation.customer_credit_note_id' => $note, 'refund.status' => 'posted'])->sum('allocation.allocated_minor'));

        $full = $refunds->create($company, $maker, ['financial_account_id' => $bank, 'reference' => 'REF-600', 'refund_date' => '2026-09-22', 'amount_minor' => 600, 'idempotency_key' => 'refund-full-001', 'allocations' => [['customer_credit_note_id' => $note, 'allocated_minor' => 600]]]);
        $refunds->submit($company, $full, $maker);
        $refunds->approve($company, $full, $checker);
        $refunds->post($company, $full, $checker);
        self::assertSame(0, 1000 - (int) DB::table('customer_refund_allocations')->where('customer_credit_note_id', $note)->sum('allocated_minor'));
        $this->assertValidation(fn () => $refunds->create($company, $maker, ['financial_account_id' => $bank, 'reference' => 'REF-OVER', 'refund_date' => '2026-09-23', 'amount_minor' => 1, 'idempotency_key' => 'refund-over-001', 'allocations' => [['customer_credit_note_id' => $note, 'allocated_minor' => 1]]]), 'allocations');

        foreach ([$partial, $full] as $refund) {
            $stored = DB::table('customer_refunds')->where('id', $refund)->firstOrFail();
            self::assertSame('posted', $stored->status);
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_refund', 'source_id' => $refund])->count());
            self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'customer_refund', 'source_id' => $refund, 'direction' => 'outflow', 'financial_account_id' => $bank])->count());
            self::assertSame((int) DB::table('payroll_accounting_journals')->where('id', $stored->accounting_journal_id)->value('total_debit_minor'), (int) DB::table('payroll_accounting_journals')->where('id', $stored->accounting_journal_id)->value('total_credit_minor'));
        }
        self::assertSame(2, DB::table('customer_refunds')->where('company_id', $company)->count());
        self::assertEquals($invoiceBefore, DB::table('customer_invoices')->where('id', $invoice)->firstOrFail());
        self::assertEquals($creditBefore, DB::table('customer_credit_notes')->where('id', $note)->firstOrFail());
        self::assertEquals($collectionBefore, DB::table('customer_collections')->where('id', $collection)->firstOrFail());
    }

    private function creditNotePolicy(int $company, User $maker, User $checker): void
    {
        $accounts = [
            'REVENUE' => $this->account($company, $maker, '4099', 'Credit revenue', 'revenue'),
            'TAX_PAYABLE' => (int) (DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2180'])->value('id') ?: $this->account($company, $maker, '2299', 'Credit tax', 'liability')),
            'RECEIVABLE' => $this->account($company, $maker, '1199', 'Credit receivable', 'asset'),
            'CUSTOMER_CREDIT_LIABILITY' => $this->account($company, $maker, '2399', 'Customer credit', 'liability'),
        ];
        $policy = app(AccountingPostingPolicyService::class);
        $id = $policy->create($company, $maker, ['policy_key' => 'CUSTOMER-CREDIT-NOTE', 'source_type' => 'customer_credit_note', 'business_event' => 'CUSTOMER_INVOICE_CREDIT_NOTE', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'REVENUE', 'account_id' => $accounts['REVENUE']],
            ['entry_side' => 'debit', 'line_role' => 'TAX_PAYABLE', 'account_id' => $accounts['TAX_PAYABLE']],
            ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $accounts['RECEIVABLE']],
            ['entry_side' => 'credit', 'line_role' => 'CUSTOMER_CREDIT_LIABILITY', 'account_id' => $accounts['CUSTOMER_CREDIT_LIABILITY']],
        ]]);
        $policy->submit($company, $id, $maker);
        $policy->approve($company, $id, $checker);
    }

    private function refundPolicyAndAccount(int $company, int $branch, User $maker, User $checker): int
    {
        $cash = $this->account($company, $maker, '1111', 'Customer refund bank', 'asset');
        $liability = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '2399'])->value('id');
        $bank = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $cash, 'code' => 'REFUND-BANK', 'name' => 'Customer Refund Bank', 'type' => 'bank', 'currency' => 'VND', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $policy = app(AccountingPostingPolicyService::class);
        $id = $policy->create($company, $maker, ['policy_key' => 'CUSTOMER-REFUND', 'source_type' => 'customer_refund', 'business_event' => 'CUSTOMER_REFUND', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'CUSTOMER_CREDIT_LIABILITY', 'account_id' => $liability],
            ['entry_side' => 'credit', 'line_role' => 'CASH', 'account_id' => $cash],
        ]]);
        $policy->submit($company, $id, $maker);
        $policy->approve($company, $id, $checker);

        return $bank;
    }

    private function assertValidation(callable $callback, string $key): void
    {
        try {
            $callback();
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($key, $exception->errors());
        }
    }

    private function draftInvoice(int $company, int $branch, int $customer, User $maker, string $currency, int $amount, string $number): int
    {
        return (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'sales_order_id' => null, 'invoice_number' => $number, 'invoice_date' => '2026-09-04', 'due_date' => '2026-10-04', 'currency' => $currency, 'subtotal_minor' => $amount, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => $amount, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function activeSpotRate(int $company, User $maker, User $checker, string $base, string $quote, string $rate): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => $rate, 'rate_date' => '2026-09-04', 'rate_type' => 'spot', 'source_reference' => 'AR-FX-ISSUE-'.$company, 'effective_from' => '2026-09-04', 'effective_to' => null, 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }

    private function account(int $company, User $creator, string $code, string $name, string $type): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => in_array($type, ['liability', 'revenue'], true) ? 'credit' : 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function vatPolicy(int $company, User $maker, User $checker, string $categoryCode, string $direction, string $taxAccountCode, string $taxAccountName): void
    {
        $receivable = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '1150'])->value('id');
        $revenue = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '4000'])->value('id');
        $taxAccount = $this->account($company, $maker, $taxAccountCode, $taxAccountName, 'liability');
        $policy = app(AccountingPostingPolicyService::class);
        $accountingPolicy = $policy->create($company, $maker, ['policy_key' => 'VAT-INVOICE', 'source_type' => 'customer_invoice', 'business_event' => 'CUSTOMER_INVOICE_VAT', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [
            ['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable],
            ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $revenue],
            ['entry_side' => 'credit', 'line_role' => 'TAX_PAYABLE', 'account_id' => $taxAccount],
        ]]);
        $policy->submit($company, $accountingPolicy, $maker);
        $policy->approve($company, $accountingPolicy, $checker);
        $category = (int) DB::table('tax_categories')->insertGetId(['company_id' => $company, 'code' => $categoryCode, 'name' => $categoryCode, 'direction' => $direction, 'country_code' => 'VN', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vat_policies')->insert(['company_id' => $company, 'tax_category_id' => $category, 'policy_key' => 'VAT-10', 'version' => 1, 'country_code' => 'VN', 'currency' => 'VND', 'rate_basis_points' => 1_000, 'effective_from' => '2026-01-01', 'effective_to' => null, 'tax_account_id' => $taxAccount, 'accounting_posting_policy_id' => $accountingPolicy, 'status' => 'approved', 'created_by' => $maker->id, 'submitted_by' => $maker->id, 'submitted_at' => now(), 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $data */
    private function selfApprovalIsBlocked(SalesReceivableService $service, int $company, User $maker, string $action, array $data): void
    {
        try {
            match ($action) {
                'quotation.accept' => $service->acceptQuotation($company, $maker, $data),
                'invoice.issue' => $service->issueCustomerInvoice($company, $maker, $data),
                default => self::fail('Unsupported maker-checker test intent.'),
            };
            self::fail('Maker-checker must block self approval.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
        }
    }

    /** @return array{User,User,int,int,int,int,int} */
    private function runtime(string $suffix = 'MAIN'): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'SALES-'.$suffix, 'name' => 'Sales Company '.$suffix, 'currency_code' => 'VND', 'functional_currency_code' => 'VND', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$suffix, 'name' => 'Head Office '.$suffix, 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách hàng '.$suffix, 'phone' => '090'.str_pad((string) $company, 7, '0', STR_PAD_LEFT), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Sales Store '.$suffix, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Sales Store '.$suffix, 'slug' => 'sales-store-'.strtolower($suffix), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'SALES-P-'.$suffix, 'name' => 'Thiết bị '.$suffix, 'slug' => 'thiet-bi-'.strtolower($suffix), 'base_price_minor' => 500, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'SALES-SKU-'.$suffix, 'name' => 'Mặc định', 'price_minor' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'WH-'.$suffix, 'name' => 'Kho '.$suffix, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 1000, 'average_unit_cost_minor' => 100, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $posting = app(SourceAccountingPostingService::class);
        $policy = app(AccountingPostingPolicyService::class);
        $receivable = $posting->accountEvidence($company, '1150', 'VND', $maker->id)['id'];
        $revenue = $posting->accountEvidence($company, '4000', 'VND', $maker->id)['id'];
        $cash = $posting->accountEvidence($company, '1100', 'VND', $maker->id)['id'];
        $cogs = $posting->accountEvidence($company, '5000', 'VND', $maker->id)['id'];
        $inventory = $posting->accountEvidence($company, '1200', 'VND', $maker->id)['id'];
        foreach ([
            ['CUSTOMER-INVOICE-'.$suffix, 'customer_invoice', 'CUSTOMER_INVOICE_ISSUED', [['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $revenue]]],
            ['CUSTOMER-COLLECTION-'.$suffix, 'customer_collection', 'CUSTOMER_COLLECTION', [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable]]],
            ['SALES-COGS-'.$suffix, 'sales_fulfillment', 'SALES_COGS', [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $cogs], ['entry_side' => 'credit', 'line_role' => 'INVENTORY', 'account_id' => $inventory]]],
        ] as [$key, $source, $event, $lines]) {
            $policyId = $policy->create($company, $maker, ['policy_key' => $key, 'source_type' => $source, 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
            $policy->submit($company, $policyId, $maker);
            $policy->approve($company, $policyId, $checker);
        }

        return [$maker, $checker, $company, $branch, $customer, $variant, $location];
    }
}
