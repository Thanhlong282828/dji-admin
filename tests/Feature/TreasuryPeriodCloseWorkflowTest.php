<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPeriodCloseService;
use Modules\Finance\Application\Services\ApprovalInboxService;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Finance\Application\Services\TreasuryService;
use Tests\TestCase;

final class TreasuryPeriodCloseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_payment_posts_one_treasury_source_reconciles_and_period_close_uses_maker_checker(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $finance = app(FinanceOperationsService::class);
        $treasury = app(TreasuryService::class);
        $periods = app(AccountingPeriodCloseService::class);

        $category = $finance->mutate('expense-category.create', $company, $maker, ['code' => 'OPS', 'name' => 'Operations']);
        $expense = $finance->mutate('expense.create', $company, $maker, ['branch_id' => $branch, 'expense_category_id' => $category, 'reference' => 'LOCAL-ERP-20260901-EXP', 'amount_minor' => 4_500_000, 'expense_date' => '2026-09-01', 'description' => 'Chi phí vận hành có chứng từ']);
        $finance->mutate('expense.transition', $company, $maker, ['id' => $expense, 'to' => 'submitted']);
        $finance->mutate('expense.transition', $company, $checker, ['id' => $expense, 'to' => 'approved']);
        $finance->mutate('expense.transition', $company, $checker, ['id' => $expense, 'to' => 'paid']);

        self::assertSame(1, DB::table('treasury_transactions')->where(['company_id' => $company, 'source_type' => 'finance_expense_payment', 'source_id' => $expense])->count());
        $transaction = DB::table('treasury_transactions')->where('source_id', $expense)->firstOrFail();
        $session = $treasury->mutate('reconciliation.create', $company, $checker, ['financial_account_id' => (int) $transaction->financial_account_id, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30']);
        $line = $treasury->mutate('statement.create', $company, $checker, ['session_id' => $session, 'external_reference' => 'BANK-LOCAL-ERP-20260901-EXP', 'occurred_at' => '2026-09-01 12:00:00', 'amount_minor' => -4_500_000]);
        $treasury->mutate('statement.match', $company, $checker, ['line_id' => $line, 'transaction_id' => (int) $transaction->id]);
        $treasury->mutate('reconciliation.complete', $company, $checker, ['session_id' => $session]);
        self::assertSame('reconciled', DB::table('treasury_transactions')->where('id', $transaction->id)->value('status'));

        $periodId = (int) DB::table('accounting_periods')->where(['company_id' => $company, 'year' => 2026, 'month' => 9])->value('id');
        $requestId = $periods->request($company, $periodId, 'close', $maker, 'Hoàn tất đối soát tháng 09');
        self::assertSame(0, DB::table('accounting_period_close_checks')->where(['close_request_id' => $requestId, 'status' => 'blocked'])->count());
        $inbox = app(ApprovalInboxService::class)->read($company, $checker->id);
        self::assertTrue(collect($inbox['pending'])->contains(fn (array $item): bool => $item['type'] === 'Accounting Period Close'));

        try {
            $periods->decide($company, $requestId, true, $maker, null);
            self::fail('Maker must not approve own close request.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('actor', $exception->errors());
        }
        $periods->decide($company, $requestId, true, $checker, null);
        self::assertSame('closed', DB::table('accounting_periods')->where('id', $periodId)->value('status'));
    }

    /** @return array{User,User,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'TREASURY', 'name' => 'Treasury Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'Trụ sở', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);

        return [$maker, $checker, $company, $branch];
    }
}
