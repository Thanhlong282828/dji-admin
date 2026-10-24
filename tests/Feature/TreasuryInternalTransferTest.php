<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\FinancialStatementReadService;
use Modules\Finance\Application\Services\TreasuryCashPositionReadService;
use Modules\Finance\Application\Services\TreasuryInternalTransferService;
use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class TreasuryInternalTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_currency_transfer_posts_two_legs_balanced_journal_and_preserves_company_cash(): void
    {
        [$maker, $checker, $company, $branch, $bank, $cash, $clearing] = $this->runtime('TRANSFER');
        $this->opening($company, $branch, $bank, 'bank', 100_000_000, $maker, $checker);
        $this->opening($company, $branch, $cash, 'cash', 50_000_000, $maker, $checker);
        $this->transferPolicy($company, $bank, $cash, $maker, $checker);

        $service = app(TreasuryInternalTransferService::class);
        $data = $this->payload($bank, $cash, 'transfer-main');
        $id = $service->create($company, $maker, $data);
        self::assertSame($id, $service->create($company, $maker, $data));
        self::assertSame(2, DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $id)->count());
        $service->submit($company, $id, $maker);
        $this->assertValidation(fn () => $service->approve($company, $id, $maker), 'approval');
        $service->approve($company, $id, $checker);
        self::assertSame($id, $service->post($company, $id, $checker));
        self::assertSame($id, $service->post($company, $id, $checker));

        $transfer = DB::table('treasury_internal_transfers')->where('id', $id)->firstOrFail();
        self::assertSame('posted', $transfer->status);
        self::assertSame(2, DB::table('treasury_transactions')->where('source_type', 'treasury_internal_transfer_leg')->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['financial_account_id' => $bank, 'direction' => 'outflow', 'amount_minor' => 30_000_000])->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['financial_account_id' => $cash, 'direction' => 'inflow', 'amount_minor' => 30_000_000])->count());
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_internal_transfer', 'source_id' => $id])->firstOrFail();
        self::assertSame(30_000_000, (int) $journal->total_debit_minor);
        self::assertSame(30_000_000, (int) $journal->total_credit_minor);
        self::assertSame(1, DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $journal->id, 'account_id' => $this->ledgerId($cash), 'debit_minor' => 30_000_000])->count());
        self::assertSame(1, DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $journal->id, 'account_id' => $this->ledgerId($bank), 'credit_minor' => 30_000_000])->count());

        $position = app(TreasuryCashPositionReadService::class)->read($company, '2026-06-30');
        self::assertSame(70_000_000, collect($position['accounts'])->firstWhere('account_id', $bank)['balance_minor']);
        self::assertSame(80_000_000, collect($position['accounts'])->firstWhere('account_id', $cash)['balance_minor']);
        self::assertSame(150_000_000, $position['available_cash']['VND']['available_cash_minor']);
        self::assertSame('payment_clearing', collect($position['accounts'])->firstWhere('account_id', $clearing)['type']);

        $cashFlow = app(FinancialStatementReadService::class)->report($company, '2026-06-01', '2026-06-30', null, 1, $branch)['cash_flow'];
        self::assertSame(0, $cashFlow['operating_inflow']);
        self::assertSame(0, $cashFlow['operating_outflow']);
        self::assertSame(0, $cashFlow['unclassified']);
        self::assertSame(0, $cashFlow['net_movement']);
    }

    public function test_account_currency_clearing_branch_tenant_and_period_controls_are_enforced(): void
    {
        [$maker, $checker, $company, $branch, $bank, $cash, $clearing] = $this->runtime('GUARDS');
        $service = app(TreasuryInternalTransferService::class);
        $this->assertValidation(fn () => $service->create($company, $maker, $this->payload($bank, $bank, 'same')), 'destination_treasury_account_id');
        $this->assertValidation(fn () => $service->create($company, $maker, $this->payload($bank, $clearing, 'clearing')), 'treasury_account');
        DB::table('treasury_financial_accounts')->where('id', $cash)->update(['currency' => 'USD']);
        $this->assertValidation(fn () => $service->create($company, $maker, $this->payload($bank, $cash, 'fx')), 'currency');
        DB::table('treasury_financial_accounts')->where('id', $cash)->update(['currency' => 'VND', 'branch_id' => null]);
        $this->assertValidation(fn () => $service->create($company, $maker, $this->payload($bank, $cash, 'branch')), 'destination_treasury_account_id');

        DB::table('treasury_financial_accounts')->where('id', $cash)->update(['branch_id' => $branch]);
        $foreignCompany = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignLedger = $this->ledger($foreignCompany, '1199', 'Foreign cash', 'asset', 'debit', $maker);
        $foreign = (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $foreignCompany, 'branch_id' => null, 'accounting_account_id' => $foreignLedger, 'code' => 'FOREIGN', 'name' => 'Foreign', 'type' => 'cash', 'currency' => 'VND', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $service->create($company, $maker, $this->payload($bank, $foreign, 'tenant')), 'treasury_account');

        $this->transferPolicy($company, $bank, $cash, $maker, $checker);
        $id = $service->create($company, $maker, $this->payload($bank, $cash, 'closed'));
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
        DB::table('accounting_periods')->where(['company_id' => $company, 'year' => 2026, 'month' => 6])->update(['status' => 'closed']);
        $this->assertValidation(fn () => $service->post($company, $id, $checker), 'period');
        self::assertSame('approved', DB::table('treasury_internal_transfers')->where('id', $id)->value('status'));
        self::assertSame(0, DB::table('treasury_transactions')->where('source_type', 'treasury_internal_transfer_leg')->count());
    }

    public function test_workspace_create_action_is_permission_filtered(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$maker, $viewer, $company] = $this->runtime('UI');
        $maker->givePermissionTo(Permission::findByName('treasury.view'), Permission::findByName('treasury-internal-transfer.create'));
        $viewer->givePermissionTo(Permission::findByName('treasury.view'));
        $url = route('admin.finance.treasury', ['company_id' => $company]);
        $store = route('admin.finance.treasury.internal-transfers.store');
        $this->actingAs($maker)->get($url)->assertOk()->assertSee('Internal Transfer')->assertSee($store, false);
        $this->actingAs($viewer)->get($url)->assertOk()->assertSee('Internal Transfer')->assertDontSee($store, false);
    }

    public function test_posted_transfer_reversal_is_append_only_balanced_idempotent_and_cash_neutral(): void
    {
        [$maker, $checker, $company, $branch, $bank, $cash] = $this->runtime('REVERSAL');
        $this->opening($company, $branch, $bank, 'bank', 100_000_000, $maker, $checker);
        $this->opening($company, $branch, $cash, 'cash', 50_000_000, $maker, $checker);
        $this->transferPolicy($company, $bank, $cash, $maker, $checker);
        $service = app(TreasuryInternalTransferService::class);
        $original = $service->create($company, $maker, $this->payload($bank, $cash, 'reversal-original'));
        $service->submit($company, $original, $maker);
        $service->approve($company, $original, $checker);
        $service->post($company, $original, $checker);
        $originalHeader = DB::table('treasury_internal_transfers')->where('id', $original)->firstOrFail();
        $originalLegs = DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $original)->orderBy('id')->get()->toArray();
        $originalJournal = DB::table('payroll_accounting_journals')->where('id', $originalHeader->accounting_journal_id)->firstOrFail();
        $originalLines = DB::table('payroll_accounting_journal_lines')->where('journal_id', $originalJournal->id)->orderBy('line_number')->get()->toArray();

        $payload = ['occurred_at' => '2026-06-20 10:00:00', 'reference' => 'REV-ORIGINAL', 'reason' => 'Sai tài khoản đích', 'idempotency_key' => 'reversal-once'];
        $reversal = $service->reverse($company, $original, $maker, $payload);
        self::assertSame($reversal, $service->reverse($company, $original, $maker, $payload));
        self::assertSame($reversal, $service->reverse($company, $original, $maker, [...$payload, 'idempotency_key' => 'different-key']));
        $reverseHeader = DB::table('treasury_internal_transfers')->where('id', $reversal)->firstOrFail();
        self::assertSame($original, (int) $reverseHeader->reversal_of_transfer_id);
        self::assertSame((int) $originalHeader->destination_treasury_account_id, (int) $reverseHeader->source_treasury_account_id);
        self::assertSame((int) $originalHeader->source_treasury_account_id, (int) $reverseHeader->destination_treasury_account_id);
        $this->assertValidation(fn () => $service->reverse($company, $reversal, $maker, [...$payload, 'idempotency_key' => 'reverse-a-reversal']), 'transfer');
        $this->assertValidation(fn () => $service->approve($company, $reversal, $maker), 'status');
        $service->submit($company, $reversal, $maker);
        $this->assertValidation(fn () => $service->approve($company, $reversal, $maker), 'approval');
        $service->approve($company, $reversal, $checker);
        $service->post($company, $reversal, $checker);
        $service->post($company, $reversal, $checker);

        self::assertSame('posted', DB::table('treasury_internal_transfers')->where('id', $original)->value('status'));
        self::assertEquals($originalLegs, DB::table('treasury_internal_transfer_legs')->where('treasury_internal_transfer_id', $original)->orderBy('id')->get()->toArray());
        self::assertEquals($originalLines, DB::table('payroll_accounting_journal_lines')->where('journal_id', $originalJournal->id)->orderBy('line_number')->get()->toArray());
        self::assertSame(4, DB::table('treasury_transactions')->where('source_type', 'treasury_internal_transfer_leg')->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['financial_account_id' => $cash, 'direction' => 'outflow', 'amount_minor' => 30_000_000])->count());
        self::assertSame(1, DB::table('treasury_transactions')->where(['financial_account_id' => $bank, 'direction' => 'inflow', 'amount_minor' => 30_000_000])->count());
        $reversalJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'treasury_internal_transfer', 'source_id' => $reversal])->firstOrFail();
        self::assertSame((int) $originalJournal->id, (int) $reversalJournal->reversal_of_journal_id);
        self::assertSame(1, DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $reversalJournal->id, 'account_id' => $this->ledgerId($bank), 'debit_minor' => 30_000_000])->count());
        self::assertSame(1, DB::table('payroll_accounting_journal_lines')->where(['journal_id' => $reversalJournal->id, 'account_id' => $this->ledgerId($cash), 'credit_minor' => 30_000_000])->count());
        $position = app(TreasuryCashPositionReadService::class)->read($company, '2026-06-30');
        self::assertSame(100_000_000, collect($position['accounts'])->firstWhere('account_id', $bank)['balance_minor']);
        self::assertSame(50_000_000, collect($position['accounts'])->firstWhere('account_id', $cash)['balance_minor']);
        self::assertSame(150_000_000, $position['available_cash']['VND']['available_cash_minor']);
        $cashFlow = app(FinancialStatementReadService::class)->report($company, '2026-06-01', '2026-06-30', null, 1, $branch)['cash_flow'];
        self::assertSame(0, $cashFlow['net_movement']);
    }

    public function test_reversal_route_requires_explicit_permission_and_hides_ineligible_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$maker, $checker, $company, $branch, $bank, $cash] = $this->runtime('REV-UI');
        $maker->givePermissionTo('treasury.view', 'treasury-internal-transfer.reverse');
        $checker->givePermissionTo('treasury.view');
        $this->transferPolicy($company, $bank, $cash, $maker, $checker);
        $service = app(TreasuryInternalTransferService::class);
        $original = $service->create($company, $maker, $this->payload($bank, $cash, 'ui-original'));
        $service->submit($company, $original, $maker);
        $service->approve($company, $original, $checker);
        $service->post($company, $original, $checker);
        $url = route('admin.finance.treasury', ['company_id' => $company]);
        $reverseUrl = route('admin.finance.treasury.internal-transfers.reverse', ['transfer' => $original]);
        $this->actingAs($maker)->get($url)->assertOk()->assertSee($reverseUrl, false);
        $this->actingAs($checker)->get($url)->assertOk()->assertDontSee($reverseUrl, false);
        $this->actingAs($checker)->post($reverseUrl, ['company_id' => $company])->assertForbidden();
        $this->actingAs($maker)->post($reverseUrl, ['company_id' => $company, 'occurred_at' => '2026-06-20 10:00:00', 'reference' => 'REV-UI', 'reason' => 'Correction', 'idempotency_key' => 'rev-ui'])->assertRedirect();
        $this->actingAs($maker)->get($url)->assertOk()->assertDontSee($reverseUrl, false);
    }

    /** @return array{User,User,int,int,int,int,int} */
    private function runtime(string $code): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code.' Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('accounting_periods')->insert(['company_id' => $company, 'year' => 2026, 'month' => 6, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $bankLedger = $this->ledger($company, '1110', 'Bank A', 'asset', 'debit', $maker);
        $cashLedger = $this->ledger($company, '1111', 'Cash B', 'asset', 'debit', $maker);
        $clearingLedger = $this->ledger($company, '1100', 'Clearing', 'asset', 'debit', $maker);
        $this->ledger($company, '3000', 'Opening Equity', 'equity', 'credit', $maker);
        $bank = $this->treasuryAccount($company, $branch, $bankLedger, 'BANK-A', 'bank', $maker);
        $cash = $this->treasuryAccount($company, $branch, $cashLedger, 'CASH-B', 'cash', $maker);
        $clearing = $this->treasuryAccount($company, $branch, $clearingLedger, 'CLEAR', 'payment_clearing', $maker);

        return [$maker, $checker, $company, $branch, $bank, $cash, $clearing];
    }

    /** @return array<string,mixed> */
    private function payload(int $source, int $destination, string $key): array
    {
        return ['source_treasury_account_id' => $source, 'destination_treasury_account_id' => $destination, 'currency' => 'VND', 'amount_minor' => 30_000_000, 'occurred_at' => '2026-06-15 10:00:00', 'reference' => strtoupper($key), 'reason' => 'Treasury rebalance', 'idempotency_key' => $key];
    }

    private function opening(int $company, int $branch, int $account, string $classification, int $amount, User $maker, User $checker): void
    {
        $policy = app(AccountingPostingPolicyService::class);
        $id = $policy->create($company, $maker, ['policy_key' => 'OPEN-'.$classification, 'source_type' => 'treasury_opening_balance', 'business_event' => 'TREASURY_OPENING_BALANCE', 'classification' => $classification, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'CASH', 'account_id' => $this->ledgerId($account)], ['entry_side' => 'credit', 'line_role' => 'OPENING_EQUITY', 'account_id' => (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '3000'])->value('id')]]]);
        $policy->submit($company, $id, $maker);
        $policy->approve($company, $id, $checker);
        $service = app(TreasuryOpeningBalanceService::class);
        $opening = $service->create($company, $maker, ['treasury_financial_account_id' => $account, 'supersedes_opening_balance_id' => null, 'currency' => 'VND', 'effective_date' => '2026-01-01', 'opening_amount_minor' => $amount, 'reference' => 'OPEN-'.$classification, 'source_note' => null, 'idempotency_key' => 'open-'.$classification]);
        $service->submit($company, $opening, $maker);
        $service->approve($company, $opening, $checker);
        $service->post($company, $opening, $checker);
    }

    private function transferPolicy(int $company, int $source, int $destination, User $maker, User $checker): void
    {
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, ['policy_key' => 'TREASURY-TRANSFER', 'source_type' => 'treasury_internal_transfer', 'business_event' => 'TREASURY_INTERNAL_TRANSFER', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'DESTINATION_CASH', 'account_id' => $this->ledgerId($destination)], ['entry_side' => 'credit', 'line_role' => 'SOURCE_CASH', 'account_id' => $this->ledgerId($source)]]]);
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
    }

    private function ledger(int $company, string $code, string $name, string $type, string $normal, User $actor): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function treasuryAccount(int $company, int $branch, int $ledger, string $code, string $type, User $actor): int
    {
        return (int) DB::table('treasury_financial_accounts')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'accounting_account_id' => $ledger, 'code' => $code, 'name' => $code, 'type' => $type, 'currency' => 'VND', 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ledgerId(int $account): int
    {
        return (int) DB::table('treasury_financial_accounts')->where('id', $account)->value('accounting_account_id');
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
