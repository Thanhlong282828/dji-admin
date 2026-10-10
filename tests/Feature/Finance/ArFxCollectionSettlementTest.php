<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\SalesReceivableService;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\ArFxRevaluationSourceService;
use Modules\Finance\Application\Services\CustomerInvoiceFxRecognitionService;
use Modules\Finance\Application\Services\FxRateService;
use Modules\Finance\Application\Services\FxRevaluationService;
use Tests\TestCase;

final class ArFxCollectionSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_revaluation_source_is_as_of_and_ignores_later_collection(): void
    {
        [$company, $maker, $checker, $invoice, $branch, $usdAccount] = $this->runtime();
        $this->rate($company, $maker, $checker, '2026-10-20', '25100.000000000000', 'LATER-SETTLEMENT', '2026-10-20');
        app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-LATER', 'collection_date' => '2026-10-20', 'amount_minor' => 10_000, 'method' => 'bank_transfer']);

        $historical = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-10');
        self::assertCount(1, $historical);
        self::assertSame(10_000, $historical[0]->foreignOutstandingMinor);
        self::assertSame(2_469_050, $historical[0]->carryingFunctionalMinor);
        self::assertSame([], $historical[0]->evidence['settlements']);
        self::assertSame([], app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-20'));
    }

    public function test_revaluation_uses_only_partial_settlements_effective_on_or_before_valuation_date(): void
    {
        [$company, $maker, $checker, $invoice, $branch, $usdAccount] = $this->runtime();
        $service = app(SalesReceivableService::class);
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-BEFORE', 'collection_date' => '2026-10-04', 'amount_minor' => 3_333, 'method' => 'bank_transfer']);
        $this->rate($company, $maker, $checker, '2026-10-20', '25100.000000000000', 'AFTER-VALUATION', '2026-10-20');
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-AFTER', 'collection_date' => '2026-10-20', 'amount_minor' => 6_667, 'method' => 'bank_transfer']);

        $source = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-10')[0];
        $released = (int) DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')->where('collection.reference', 'FX-BEFORE')->value('settlement.released_carrying_minor');
        self::assertSame(6_667, $source->foreignOutstandingMinor);
        self::assertSame(2_469_050 - $released, $source->carryingFunctionalMinor);
        self::assertCount(1, $source->evidence['settlements']);
        self::assertSame('2026-10-04', $source->evidence['settlements'][0]['date']);
    }

    public function test_revaluation_requires_direct_closing_rate_and_rejects_spot_or_inverse_only(): void
    {
        [$company, $maker, $checker, , $branch] = $this->runtime();
        $period = $this->period($company, $maker, 2026, 10);
        $sources = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-31');
        $this->closingPair($company, $maker, $checker, 'VND', 'USD', '2026-10-31', '0.000040000000');

        $this->assertValidation(fn () => app(FxRevaluationService::class)->create($company, $branch, $period, '2026-10-31', (string) Str::uuid(), $maker, $sources), 'FX_RATE_NOT_AVAILABLE');
        self::assertSame(0, DB::table('finance_fx_revaluation_runs')->where('company_id', $company)->count());
        self::assertSame(0, DB::table('finance_fx_revaluation_lines')->where('company_id', $company)->count());
    }

    public function test_revaluation_source_discovery_is_company_and_branch_isolated(): void
    {
        [$company, , , $invoice, $branch] = $this->runtime();
        [$otherCompany, , , $otherInvoice, $otherBranch] = $this->runtime();

        $owned = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-31');
        self::assertCount(1, $owned);
        self::assertSame($company, $owned[0]->companyId);
        self::assertSame($invoice, $owned[0]->sourceId);
        self::assertNotSame($otherInvoice, $owned[0]->sourceId);
        self::assertSame([], app(ArFxRevaluationSourceService::class)->eligible($company, $otherBranch, '2026-10-31'));
        self::assertCount(1, app(ArFxRevaluationSourceService::class)->eligible($otherCompany, $otherBranch, '2026-10-31'));
    }

    public function test_next_period_carrying_includes_posted_prior_revaluation_and_excludes_it_after_reversal(): void
    {
        [$company, $maker, $checker, , $branch] = $this->runtime();
        $october = $this->period($company, $maker, 2026, 10);
        $november = $this->period($company, $maker, 2026, 11);
        $this->closingRate($company, $maker, $checker, '2026-10-31', '25000.000000000000');
        $this->closingRate($company, $maker, $checker, '2026-11-30', '26000.000000000000');
        $this->revaluationPolicies($company, $maker, $checker);
        $service = app(FxRevaluationService::class);
        $octoberSource = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-31');
        $run = $service->create($company, $branch, $october, '2026-10-31', (string) Str::uuid(), $maker, $octoberSource);
        $service->transition($company, $run, 'submit', $maker);
        $service->transition($company, $run, 'approve', $checker);
        $service->post($company, $run, $checker);
        $difference = (int) DB::table('finance_fx_revaluation_lines')->where('fx_revaluation_run_id', $run)->value('fx_difference_minor');

        $novemberSource = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-11-30')[0];
        self::assertSame(2_469_050 + $difference, $novemberSource->carryingFunctionalMinor);
        self::assertSame($difference, $novemberSource->evidence['prior_active_revaluation_minor']);

        $service->reverse($company, $run, $checker);
        $afterReversal = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-11-30')[0];
        self::assertSame(2_469_050, $afterReversal->carryingFunctionalMinor);
        self::assertSame(0, $afterReversal->evidence['prior_active_revaluation_minor']);
        self::assertSame(0, DB::table('treasury_transactions')->where('company_id', $company)->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where('source_type', 'fx_revaluation')->whereNotNull('reversal_of_journal_id')->count());
        self::assertSame(2, DB::table('accounting_periods')->where('company_id', $company)->whereIn('id', [$october, $november])->count());
    }

    public function test_open_ar_creates_canonical_revaluation_line_with_closing_snapshot_and_evidence(): void
    {
        [$company, $maker, $checker, $invoice, $branch] = $this->runtime();
        $this->closingRate($company, $maker, $checker, '2026-10-31', '25000.000000000000');
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $maker->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $sources = app(ArFxRevaluationSourceService::class)->eligible($company, $branch, '2026-10-31');
        $run = app(FxRevaluationService::class)->create($company, $branch, $period, '2026-10-31', (string) Str::uuid(), $maker, $sources);

        $line = DB::table('finance_fx_revaluation_lines')->where(['fx_revaluation_run_id' => $run, 'source_type' => 'customer_invoice', 'source_id' => $invoice])->firstOrFail();
        self::assertSame(2_500_000, (int) $line->revalued_functional_minor);
        self::assertSame(30_950, (int) $line->fx_difference_minor);
        self::assertNotNull($line->fx_rate_snapshot_id);
        $mapping = json_decode((string) $line->mapping_snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2026-10-31', $mapping['source_evidence']['valuation_date']);
        self::assertSame(2_469_050, $mapping['source_evidence']['original_functional_minor']);

        try {
            app(FxRevaluationService::class)->create($company, $branch, $period, '2026-10-31', (string) Str::uuid(), $maker, $sources);
            self::fail('A source may only have one active revaluation on a valuation date.');
        } catch (ValidationException $exception) {
            self::assertSame('FX_REVALUATION_ACTIVE_SOURCE_DATE_DUPLICATE', $exception->errors()['source'][0]);
        }
        self::assertSame(1, DB::table('finance_fx_revaluation_runs')->where('company_id', $company)->count());
    }

    public function test_foreign_collection_posts_functional_gain_and_currency_matched_treasury_atomically(): void
    {
        [$company, $maker, $checker, $invoice, $branch, $usdAccount] = $this->runtime();
        $collection = app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, [
            'customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-COL-1',
            'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer',
        ]);

        $settlement = DB::table('customer_collection_allocation_fx_settlements')->firstOrFail();
        self::assertSame(2_500_000, (int) $settlement->settlement_functional_minor);
        self::assertSame(2_469_050, (int) $settlement->released_carrying_minor);
        self::assertSame(30_950, (int) $settlement->realized_fx_minor);
        self::assertNotNull($settlement->settlement_fx_snapshot_id);
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_collection', 'source_id' => $collection])->firstOrFail();
        self::assertSame('VND', $journal->currency);
        self::assertSame(2_500_000, (int) $journal->total_debit_minor);
        self::assertSame(2_500_000, (int) $journal->total_credit_minor);
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'customer_collection', 'source_id' => $collection, 'financial_account_id' => $usdAccount, 'currency' => 'USD', 'amount_minor' => 10_000])->count());
        self::assertSame($collection, app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-COL-1', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']));
        self::assertSame(1, DB::table('customer_collection_allocation_fx_settlements')->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where('source_type', 'customer_collection')->count());
    }

    public function test_partial_then_final_settlement_releases_exact_carrying_basis(): void
    {
        [$company, , $checker, $invoice, , $usdAccount] = $this->runtime();
        $service = app(SalesReceivableService::class);
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-PART-1', 'collection_date' => '2026-10-04', 'amount_minor' => 3_333, 'method' => 'bank_transfer']);
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-PART-2', 'collection_date' => '2026-10-04', 'amount_minor' => 6_667, 'method' => 'bank_transfer']);

        self::assertSame(10_000, (int) DB::table('customer_collection_allocation_fx_settlements')->sum('foreign_settled_minor'));
        self::assertSame(2_469_050, (int) DB::table('customer_collection_allocation_fx_settlements')->sum('released_carrying_minor'));
        self::assertSame('paid', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
    }

    public function test_foreign_collection_failures_leave_no_partial_economic_effect(): void
    {
        [$company, , $checker, $invoice] = $this->runtime();
        $this->assertValidation(fn () => app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'reference' => 'FX-FAIL', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']), 'TREASURY_FOREIGN_COLLECTION_ACCOUNT_REQUIRED');
        self::assertSame(0, DB::table('customer_collections')->count());
        self::assertSame(0, DB::table('customer_collection_allocation_fx_settlements')->count());
        self::assertSame(0, DB::table('treasury_transactions')->count());
    }

    public function test_negative_and_zero_realized_fx_use_the_correct_policy_without_rounding_drift(): void
    {
        [$company, , $checker, $invoice, , $usdAccount] = $this->runtime();
        DB::table('finance_fx_rates')->where(['company_id' => $company, 'rate_date' => '2026-10-04'])->update(['rate_decimal' => '24000.000000000000']);
        $collection = app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-LOSS', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']);
        $settlement = DB::table('customer_collection_allocation_fx_settlements')->firstOrFail();
        self::assertSame(-69_050, (int) $settlement->realized_fx_minor);
        $lossPolicyId = (int) DB::table('accounting_posting_policies')->where(['company_id' => $company, 'business_event' => 'CUSTOMER_COLLECTION_FX_LOSS'])->value('id');
        self::assertSame(3, DB::table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')->where(['journal.source_type' => 'customer_collection', 'journal.source_id' => $collection])->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy_id')) AS UNSIGNED) = ?", [$lossPolicyId])->count());

        [$company2, , $checker2, $invoice2, , $usdAccount2] = $this->runtime();
        DB::table('finance_fx_rates')->where(['company_id' => $company2, 'rate_date' => '2026-10-04'])->update(['rate_decimal' => '24690.500000000000']);
        app(SalesReceivableService::class)->allocateCustomerCollection($company2, $checker2, ['customer_invoice_id' => $invoice2, 'financial_account_id' => $usdAccount2, 'reference' => 'FX-ZERO', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']);
        self::assertSame(0, (int) DB::table('customer_collection_allocation_fx_settlements')->where('company_id', $company2)->value('realized_fx_minor'));
    }

    public function test_missing_rate_policy_and_currency_mismatch_fail_closed(): void
    {
        [$company, $maker, $checker, $invoice, $branch] = $this->runtime();
        DB::table('finance_fx_rates')->where(['company_id' => $company, 'rate_date' => '2026-10-04'])->delete();
        $usdLedger = $this->account($company, $maker->id, '1111', 'Second USD Bank', 'asset', 'debit', 'USD');
        $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $usdLedger, 'code' => 'USD-BANK-2', 'name' => 'USD Bank 2', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => app(SalesReceivableService::class)->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-NO-RATE', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']), 'FX_RATE_NOT_AVAILABLE');
        self::assertSame(0, DB::table('customer_collections')->where('company_id', $company)->count());

        [$company2, , $checker2, $invoice2, , $usdAccount2] = $this->runtime();
        $policyIds = DB::table('accounting_posting_policies')->where(['company_id' => $company2, 'business_event' => 'CUSTOMER_COLLECTION_FX_GAIN'])->pluck('id');
        DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', $policyIds)->delete();
        DB::table('accounting_posting_policies')->whereIn('id', $policyIds)->delete();
        $this->assertValidation(fn () => app(SalesReceivableService::class)->allocateCustomerCollection($company2, $checker2, ['customer_invoice_id' => $invoice2, 'financial_account_id' => $usdAccount2, 'reference' => 'FX-NO-POLICY', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']), 'ACCOUNTING_POSTING_POLICY_MISSING');
        self::assertSame(0, DB::table('customer_collections')->where('company_id', $company2)->count());
    }

    public function test_conflicting_retry_and_treasury_currency_mismatch_are_rejected(): void
    {
        [$company, $maker, $checker, $invoice, $branch, $usdAccount] = $this->runtime();
        $service = app(SalesReceivableService::class);
        $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-RETRY', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']);
        $this->assertValidation(fn () => $service->allocateCustomerCollection($company, $checker, ['customer_invoice_id' => $invoice, 'financial_account_id' => $usdAccount, 'reference' => 'FX-RETRY', 'collection_date' => '2026-10-04', 'amount_minor' => 9_999, 'method' => 'bank_transfer']), 'CUSTOMER_COLLECTION_IDEMPOTENCY_CONFLICT');
        self::assertSame(1, DB::table('customer_collections')->where('company_id', $company)->count());

        [$company2, $maker2, $checker2, $invoice2, $branch2] = $this->runtime();
        $vndLedger = $this->account($company2, $maker2->id, '1112', 'Wrong VND Bank', 'asset', 'debit', 'VND');
        $vndAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company2, 'branch_id' => $branch2, 'accounting_account_id' => $vndLedger, 'code' => 'VND-WRONG', 'name' => 'Wrong VND Bank', 'type' => 'bank', 'currency' => 'VND', 'status' => 'active', 'created_by' => $maker2->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $service->allocateCustomerCollection($company2, $checker2, ['customer_invoice_id' => $invoice2, 'financial_account_id' => $vndAccount, 'reference' => 'FX-WRONG-ACCOUNT', 'collection_date' => '2026-10-04', 'amount_minor' => 10_000, 'method' => 'bank_transfer']), 'TREASURY_FOREIGN_COLLECTION_CURRENCY_MISMATCH');
        self::assertSame(0, DB::table('customer_collections')->where('company_id', $company2)->count());
    }

    /** @return array{int,User,User,int,int,int} */
    private function runtime(): array
    {
        $company = Company::factory()->create(['functional_currency_code' => 'VND']);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'FX-COL', 'name' => 'FX Collection', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company->id, 'full_name' => 'FX Customer', 'phone' => '0900000001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'invoice_number' => 'FX-INV-'.$company->id, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-03', 'currency' => 'USD', 'subtotal_minor' => 10_000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 10_000, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'issued', 'created_by' => $maker->id, 'issued_by' => $checker->id, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->rate($company->id, $maker, $checker, '2026-10-03', '24690.500000000000', 'RECOGNITION', '2026-10-03');
        app(CustomerInvoiceFxRecognitionService::class)->recognize($company->id, $invoice, $maker->id);
        $this->rate($company->id, $maker, $checker, '2026-10-04', '25000.000000000000', 'SETTLEMENT', '2026-10-04');

        $cash = $this->account($company->id, $maker->id, '1100', 'Functional Cash', 'asset', 'debit', 'VND');
        $receivable = $this->account($company->id, $maker->id, '1150', 'AR', 'asset', 'debit', 'VND');
        $gain = $this->account($company->id, $maker->id, '4200', 'Realized FX Gain', 'revenue', 'credit', 'VND');
        $loss = $this->account($company->id, $maker->id, '5400', 'Realized FX Loss', 'expense', 'debit', 'VND');
        $usdLedger = $this->account($company->id, $maker->id, '1110', 'USD Bank', 'asset', 'debit', 'USD');
        $usdAccount = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'accounting_account_id' => $usdLedger, 'code' => 'USD-BANK', 'name' => 'USD Bank', 'type' => 'bank', 'currency' => 'USD', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->policy($company->id, $maker, $checker, 'CUSTOMER_COLLECTION', [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable]]);
        $this->policy($company->id, $maker, $checker, 'CUSTOMER_COLLECTION_FX_GAIN', [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'FX_GAIN', 'account_id' => $gain]]);
        $this->policy($company->id, $maker, $checker, 'CUSTOMER_COLLECTION_FX_LOSS', [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'debit', 'line_role' => 'FX_LOSS', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable]]);

        return [$company->id, $maker, $checker, $invoice, $branch, $usdAccount];
    }

    private function rate(int $company, User $maker, User $checker, string $date, string $rate, string $reference, string $effective): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => 'USD', 'quote_currency' => 'VND', 'rate_decimal' => $rate, 'rate_date' => $date, 'rate_type' => 'spot', 'source_reference' => $reference, 'effective_from' => $effective, 'effective_to' => $effective, 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }

    private function closingRate(int $company, User $maker, User $checker, string $date, string $rate): void
    {
        $this->closingPair($company, $maker, $checker, 'USD', 'VND', $date, $rate);
    }

    private function closingPair(int $company, User $maker, User $checker, string $base, string $quote, string $date, string $rate): void
    {
        $service = app(FxRateService::class);
        $id = $service->create($company, $maker, ['base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => $rate, 'rate_date' => $date, 'rate_type' => 'closing', 'source_reference' => 'AR-CLOSING-'.$base.'-'.$quote.'-'.$date, 'effective_from' => $date, 'effective_to' => $date, 'idempotency_key' => (string) Str::uuid()]);
        $service->transition($company, $id, 'submit', $maker);
        $service->transition($company, $id, 'approve', $checker);
        $service->transition($company, $id, 'activate', $checker);
    }

    private function period(int $company, User $actor, int $year, int $month): int
    {
        $fiscal = DB::table('accounting_fiscal_years')->where(['company_id' => $company, 'name' => 'FY '.$year])->value('id');
        $fiscalId = is_numeric($fiscal) ? (int) $fiscal : (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company, 'name' => 'FY '.$year, 'start_date' => $year.'-01-01', 'end_date' => $year.'-12-31', 'status' => 'open', 'created_by' => $actor->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        return (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company, 'fiscal_year_id' => $fiscalId, 'year' => $year, 'month' => $month, 'start_date' => $start, 'end_date' => $end, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function revaluationPolicies(int $company, User $maker, User $checker): void
    {
        $receivable = $this->account($company, $maker->id, '1159', 'FX AR Revaluation', 'asset', 'debit', 'VND');
        $gain = $this->account($company, $maker->id, '4299', 'Unrealized FX Gain', 'revenue', 'credit', 'VND');
        $loss = $this->account($company, $maker->id, '5499', 'Unrealized FX Loss', 'expense', 'debit', 'VND');
        $service = app(AccountingPostingPolicyService::class);
        foreach ([
            'FX_UNREALIZED_GAIN' => [['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $gain]],
            'FX_UNREALIZED_LOSS' => [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $loss], ['entry_side' => 'credit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable]],
        ] as $event => $lines) {
            $id = $service->create($company, $maker, ['policy_key' => $event, 'source_type' => 'fx_revaluation', 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
            $service->submit($company, $id, $maker);
            $service->approve($company, $id, $checker);
        }
    }

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal, string $currency): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => $currency, 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param list<array{entry_side:string,line_role:string,account_id:int}> $lines */
    private function policy(int $company, User $maker, User $checker, string $event, array $lines): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => $event, 'source_type' => 'customer_collection', 'business_event' => $event, 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => $lines]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
    }

    private function assertValidation(callable $callback, string $message): void
    {
        try {
            $callback();
            self::fail('Expected '.$message);
        } catch (ValidationException $exception) {
            self::assertContains($message, collect($exception->errors())->flatten()->all());
        }
    }
}
