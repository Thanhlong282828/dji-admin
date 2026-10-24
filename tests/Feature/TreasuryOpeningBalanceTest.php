<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\AccountsPayablePlanningReadService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Finance\Application\Services\TreasuryCashPositionReadService;
use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;
use Modules\Finance\Application\Services\TreasuryService;
use Tests\TestCase;

final class TreasuryOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_zero_lifecycle_policy_and_cash_classification(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime('OPENING');
        [$bank, $clearing] = $this->accounts($company, $branch, $maker);
        $read = app(TreasuryCashPositionReadService::class);
        self::assertSame('NOT_ENOUGH_EVIDENCE', $read->read($company, '2026-01-01')['available_cash']['VND']['status']);

        $this->policy($company, $bank, $maker, $checker, 'VND', 'bank');
        $opening = app(TreasuryOpeningBalanceService::class);
        $id = $opening->create($company, $maker, ['treasury_financial_account_id' => $bank, 'supersedes_opening_balance_id' => null, 'currency' => 'VND', 'effective_date' => '2026-01-01', 'opening_amount_minor' => 0, 'reference' => 'OPEN-ZERO', 'source_note' => 'Verified zero bank statement', 'idempotency_key' => 'opening-zero']);
        self::assertSame($id, $opening->create($company, $maker, ['treasury_financial_account_id' => $bank, 'supersedes_opening_balance_id' => null, 'currency' => 'VND', 'effective_date' => '2026-01-01', 'opening_amount_minor' => 0, 'reference' => 'OPEN-ZERO', 'source_note' => null, 'idempotency_key' => 'opening-zero']));
        $opening->submit($company, $id, $maker);
        $this->assertValidation(fn () => $opening->approve($company, $id, $maker), 'approval');
        $opening->approve($company, $id, $checker);
        self::assertSame($id, $opening->post($company, $id, $checker));
        self::assertSame($id, $opening->post($company, $id, $checker));
        self::assertSame('posted', DB::table('treasury_opening_balances')->where('id', $id)->value('status'));
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_opening_balance', 'source_id' => $id])->firstOrFail();
        self::assertSame(0, (int) $journal->total_debit_minor);
        self::assertSame(0, (int) $journal->total_credit_minor);
        self::assertNotNull(DB::table('treasury_opening_balances')->where('id', $id)->value('accounting_posting_policy_id'));

        $position = $read->read($company, '2026-01-01');
        self::assertSame('SUPPORTED', $position['available_cash']['VND']['status']);
        self::assertSame(0, $position['available_cash']['VND']['available_cash_minor']);
        self::assertSame(1, $position['available_cash']['VND']['account_count']);
        self::assertSame('payment_clearing', collect($position['accounts'])->firstWhere('account_id', $clearing)['type']);
        $noAp = app(AccountsPayablePlanningReadService::class)->read($company, $this->filters('2026-01-01', $branch));
        self::assertSame('NO_AP_DUE', $noAp['cash_evidence']['status']);
        self::assertNull($noAp['cash_evidence']['coverage_basis_points']);
        $this->assertValidation(fn () => $opening->create($company, $maker, ['treasury_financial_account_id' => $bank, 'supersedes_opening_balance_id' => null, 'currency' => 'USD', 'effective_date' => '2026-02-01', 'opening_amount_minor' => 1, 'reference' => 'WRONG-CURRENCY', 'source_note' => null, 'idempotency_key' => 'wrong-currency']), 'currency');
    }

    public function test_historical_as_of_movements_reconcile_and_ap_coverage_uses_same_currency(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime('COVERAGE');
        [$bank] = $this->accounts($company, $branch, $maker);
        $this->policy($company, $bank, $maker, $checker, 'VND', 'bank');
        $opening = app(TreasuryOpeningBalanceService::class);
        $id = $opening->create($company, $maker, ['treasury_financial_account_id' => $bank, 'supersedes_opening_balance_id' => null, 'currency' => 'VND', 'effective_date' => '2026-01-01', 'opening_amount_minor' => 100_000_000, 'reference' => 'OPEN-100M', 'source_note' => null, 'idempotency_key' => 'opening-100m']);
        $opening->submit($company, $id, $maker);
        $opening->approve($company, $id, $checker);
        $opening->post($company, $id, $checker);
        $accounting = app(SourceAccountingPostingService::class);
        $treasury = app(TreasuryService::class);
        $outJournal = $accounting->post($company, $branch, 'cash_test_out', 1, 'cash_test_out', '2026-02-01', 'VND', [['account_code' => '5100', 'debit' => 20_000_000, 'credit' => 0, 'description' => 'Outflow'], ['account_code' => '1110', 'debit' => 0, 'credit' => 20_000_000, 'description' => 'Bank']], $maker->id);
        $treasury->recordSourceToAccount($company, $branch, $bank, 'cash_test_out', 1, $outJournal, 'OUT-20M', 20_000_000, '2026-02-01 12:00:00', $maker->id, 'outflow');
        $inJournal = $accounting->post($company, $branch, 'cash_test_in', 1, 'cash_test_in', '2026-03-01', 'VND', [['account_code' => '1110', 'debit' => 10_000_000, 'credit' => 0, 'description' => 'Bank'], ['account_code' => '4000', 'debit' => 0, 'credit' => 10_000_000, 'description' => 'Inflow']], $maker->id);
        $treasury->recordSourceToAccount($company, $branch, $bank, 'cash_test_in', 1, $inJournal, 'IN-10M', 10_000_000, '2026-03-01 12:00:00', $maker->id, 'inflow');
        $read = app(TreasuryCashPositionReadService::class);
        self::assertSame(100_000_000, $read->read($company, '2026-01-15')['available_cash']['VND']['available_cash_minor']);
        self::assertSame(80_000_000, $read->read($company, '2026-02-15')['available_cash']['VND']['available_cash_minor']);
        self::assertSame(90_000_000, $read->read($company, '2026-03-15')['available_cash']['VND']['available_cash_minor']);
        $correction = $opening->create($company, $maker, ['treasury_financial_account_id' => $bank, 'supersedes_opening_balance_id' => $id, 'currency' => 'VND', 'effective_date' => '2026-04-01', 'opening_amount_minor' => 95_000_000, 'reference' => 'OPEN-CORRECTION', 'source_note' => 'Append-only correction', 'idempotency_key' => 'opening-correction']);
        $opening->submit($company, $correction, $maker);
        $opening->approve($company, $correction, $checker);
        $opening->post($company, $correction, $checker);
        self::assertSame(90_000_000, $read->read($company, '2026-03-31')['available_cash']['VND']['available_cash_minor']);
        self::assertSame(95_000_000, $read->read($company, '2026-04-01')['available_cash']['VND']['available_cash_minor']);
        self::assertSame(5_000_000, (int) DB::table('treasury_opening_balances')->where('id', $correction)->value('posting_adjustment_minor'));

        $vendor = (int) DB::table('procurement_vendors')->insertGetId(['company_id' => $company, 'code' => 'COVERAGE-V', 'name' => 'Coverage Vendor', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $request = (int) DB::table('purchase_requests')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'requester_id' => $maker->id, 'reference' => 'COVERAGE-PR', 'requested_date' => '2026-03-01', 'reason' => 'Coverage fixture', 'status' => 'approved', 'reviewed_by' => $checker->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $order = (int) DB::table('purchase_orders')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'COVERAGE-PO', 'currency' => 'VND', 'order_date' => '2026-03-01', 'status' => 'approved', 'subtotal_minor' => 30_000_000, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $billJournal = $accounting->post($company, $branch, 'vendor_bill', 999, 'vendor_bill', '2026-03-10', 'VND', [['account_code' => '5100', 'debit' => 30_000_000, 'credit' => 0, 'description' => 'Expense'], ['account_code' => '2100', 'debit' => 0, 'credit' => 30_000_000, 'description' => 'AP']], $maker->id);
        DB::table('vendor_bills')->insert(['company_id' => $company, 'branch_id' => $branch, 'vendor_id' => $vendor, 'purchase_order_id' => $order, 'invoice_number' => 'COVERAGE-BILL', 'invoice_date' => '2026-03-10', 'due_date' => '2026-03-20', 'currency' => 'VND', 'subtotal_minor' => 30_000_000, 'tax_minor' => 0, 'total_minor' => 30_000_000, 'status' => 'approved', 'accounting_journal_id' => $billJournal, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $planning = app(AccountsPayablePlanningReadService::class)->read($company, $this->filters('2026-03-15', $branch));
        self::assertSame(30_000_000, $planning['cash_evidence']['denominator_minor']);
        self::assertSame(90_000_000, $planning['cash_evidence']['available_cash']);
        self::assertSame(30_000, $planning['cash_evidence']['coverage_basis_points']);
        self::assertSame('SUPPORTED', $planning['cash_evidence']['status']);
    }

    /** @return array{User,User,int,int} */
    private function runtime(string $code): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code.' Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);

        return [$maker, $checker, $company, $branch];
    }

    /** @return array{int,int} */
    private function accounts(int $company, int $branch, User $actor): array
    {
        $bankLedger = $this->ledger($company, '1110', 'Operating Bank', 'asset', 'debit', $actor);
        $clearingLedger = $this->ledger($company, '1100', 'Payment Clearing', 'asset', 'debit', $actor);
        $this->ledger($company, '3000', 'Opening Equity', 'equity', 'credit', $actor);
        foreach ([['2100', 'Accounts Payable', 'liability', 'credit'], ['5100', 'Operating Expense', 'expense', 'debit'], ['4000', 'Sales Revenue', 'revenue', 'credit']] as [$code, $name, $type, $normal]) {
            $this->ledger($company, $code, $name, $type, $normal, $actor);
        }
        $bank = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $bankLedger, 'code' => 'BANK-HQ', 'name' => 'Operating Bank', 'type' => 'bank', 'currency' => 'VND', 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $clearing = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $clearingLedger, 'code' => 'CLEAR-HQ', 'name' => 'Payment Clearing', 'type' => 'payment_clearing', 'currency' => 'VND', 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$bank, $clearing];
    }

    private function ledger(int $company, string $code, string $name, string $type, string $normal, User $actor): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function policy(int $company, int $bank, User $maker, User $checker, string $currency, string $classification): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $cash = (int) DB::table('treasury_financial_accounts')->where('id', $bank)->value('accounting_account_id');
        $equity = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '3000'])->value('id');
        $id = $service->create($company, $maker, ['policy_key' => 'TREASURY-OPENING-'.$classification, 'source_type' => 'treasury_opening_balance', 'business_event' => 'TREASURY_OPENING_BALANCE', 'classification' => $classification, 'version' => 1, 'currency' => $currency, 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $cash], ['entry_side' => 'credit', 'line_role' => 'OPENING_EQUITY', 'account_id' => $equity]]]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
    }

    /** @return array<string,mixed> */
    private function filters(string $date, int $branch): array
    {
        return ['as_of_date' => $date, 'vendor_id' => null, 'branch_id' => $branch, 'cost_center_id' => null, 'due_bucket' => null, 'payment_status' => null, 'currency' => 'VND', 'sort' => 'due_date', 'direction' => 'asc'];
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
}
