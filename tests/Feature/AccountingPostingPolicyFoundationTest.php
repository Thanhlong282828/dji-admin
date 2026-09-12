<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Tests\TestCase;

final class AccountingPostingPolicyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_dated_versions_resolve_historically_and_activation_is_idempotent(): void
    {
        [$maker, $checker, $company, $expense, $accrual] = $this->runtime();
        $service = app(AccountingPostingPolicyService::class);
        $first = $service->create($company, $maker, $this->policy($expense, $accrual, 1, '2026-01-01', '2026-06-30'));
        $service->submit($company, $first, $maker);
        self::assertSame($first, $service->approve($company, $first, $checker));
        self::assertSame($first, $service->approve($company, $first, $checker));
        $second = $service->create($company, $maker, $this->policy($expense, $accrual, 2, '2026-07-01'));
        $service->submit($company, $second, $maker);
        $service->approve($company, $second, $checker);

        $resolver = app(AccountingPostingPolicyResolver::class);
        self::assertSame(1, $resolver->resolve($company, 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', '2026-06-15', 'VND')['version']);
        $resolved = $resolver->resolve($company, 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', '2026-08-15', 'VND');
        self::assertSame(2, $resolved['version']);
        self::assertSame(['EXPENSE', 'ACCRUAL'], array_column($resolved['lines'], 'line_role'));
    }

    public function test_resolver_fails_closed_for_missing_and_ambiguous_policy(): void
    {
        [$maker, $checker, $company, $expense, $accrual] = $this->runtime();
        $resolver = app(AccountingPostingPolicyResolver::class);
        $this->assertPolicyError(fn () => $resolver->resolve($company, 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', '2026-01-01', 'VND'), 'ACCOUNTING_POSTING_POLICY_MISSING');
        $service = app(AccountingPostingPolicyService::class);
        $id = $service->create($company, $maker, $this->policy($expense, $accrual, 1, '2026-01-01'));
        $service->submit($company, $id, $maker);
        $service->approve($company, $id, $checker);
        $copy = DB::table('accounting_posting_policies')->where('id', $id)->firstOrFail();
        $values = [
            'company_id' => $copy->company_id, 'policy_key' => 'SERVICE-ACCEPTANCE-AMBIGUOUS', 'source_type' => $copy->source_type,
            'business_event' => $copy->business_event, 'classification' => $copy->classification, 'version' => 2, 'currency' => $copy->currency,
            'status' => $copy->status, 'effective_from' => $copy->effective_from, 'effective_to' => $copy->effective_to, 'note' => $copy->note,
            'created_by' => $copy->created_by, 'submitted_by' => $copy->submitted_by, 'submitted_at' => $copy->submitted_at,
            'approved_by' => $copy->approved_by, 'approved_at' => $copy->approved_at, 'created_at' => now(), 'updated_at' => now(),
        ];
        $second = (int) DB::table('accounting_posting_policies')->insertGetId($values);
        foreach (DB::table('accounting_posting_policy_lines')->where('accounting_posting_policy_id', $id)->get() as $line) {
            $lineValues = get_object_vars($line);
            unset($lineValues['id']);
            $lineValues['accounting_posting_policy_id'] = $second;
            DB::table('accounting_posting_policy_lines')->insert($lineValues);
        }
        $this->assertPolicyError(fn () => $resolver->resolve($company, 'SERVICE_ACCEPTANCE_APPROVED', 'operating_expense', '2026-02-01', 'VND'), 'ACCOUNTING_POSTING_POLICY_AMBIGUOUS');
    }

    public function test_maker_checker_and_overlap_are_enforced(): void
    {
        [$maker, $checker, $company, $expense, $accrual] = $this->runtime();
        $service = app(AccountingPostingPolicyService::class);
        $first = $service->create($company, $maker, $this->policy($expense, $accrual, 1, '2026-01-01'));
        $service->submit($company, $first, $maker);
        $this->assertPolicyError(fn () => $service->approve($company, $first, $maker), 'MAKER_CHECKER_REQUIRED', 'approval');
        $service->approve($company, $first, $checker);
        $second = $service->create($company, $maker, [...$this->policy($expense, $accrual, 2, '2026-06-01'), 'policy_key' => 'SERVICE-ACCEPTANCE-V2']);
        $service->submit($company, $second, $maker);
        $this->assertPolicyError(fn () => $service->approve($company, $second, $checker), 'ACCOUNTING_POSTING_POLICY_OVERLAP', 'effective_from');
    }

    public function test_account_scope_status_posting_type_and_goods_grni_are_validated(): void
    {
        [$maker, , $company, $expense, $accrual] = $this->runtime();
        $service = app(AccountingPostingPolicyService::class);
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignAccount = $this->account($foreign, $maker, '2160', 'Foreign accrual', 'liability');
        $this->assertPolicyError(fn () => $service->create($company, $maker, $this->policy($expense, $foreignAccount, 1, '2026-01-01')), 'ACCOUNTING_POSTING_POLICY_ACCOUNT_INVALID', 'account_id');
        DB::table('accounting_accounts')->where('id', $accrual)->update(['allow_posting' => false]);
        $this->assertPolicyError(fn () => $service->create($company, $maker, $this->policy($expense, $accrual, 2, '2026-01-01')), 'ACCOUNTING_POSTING_POLICY_ACCOUNT_INVALID', 'account_id');
        DB::table('accounting_accounts')->where('id', $accrual)->update(['allow_posting' => true, 'status' => 'inactive']);
        $this->assertPolicyError(fn () => $service->create($company, $maker, $this->policy($expense, $accrual, 3, '2026-01-01')), 'ACCOUNTING_POSTING_POLICY_ACCOUNT_INVALID', 'account_id');
        DB::table('accounting_accounts')->where('id', $accrual)->update(['status' => 'active']);
        $this->assertPolicyError(fn () => $service->create($company, $maker, $this->policy($expense, $expense, 4, '2026-01-01')), 'ACCOUNTING_POSTING_POLICY_ACCOUNT_TYPE_INVALID', 'account_id');
        $grni = $this->account($company, $maker, '2150', 'Goods Received Not Invoiced', 'liability');
        $this->assertPolicyError(fn () => $service->create($company, $maker, $this->policy($expense, $grni, 5, '2026-01-01')), 'GOODS_GRNI_FORBIDDEN_FOR_SERVICE', 'account_id');
    }

    /** @return array{User,User,int,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'POLICY', 'name' => 'Policy Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $company, $this->account($company, $maker, '5100', 'Operating Expense', 'expense'), $this->account($company, $maker, '2160', 'Service Received Not Invoiced', 'liability')];
    }

    private function account(int $company, User $creator, string $code, string $name, string $type): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string,mixed> */
    private function policy(int $expense, int $accrual, int $version, string $from, ?string $to = null): array
    {
        return ['policy_key' => 'SERVICE-ACCEPTANCE', 'source_type' => 'service_acceptance', 'business_event' => 'SERVICE_ACCEPTANCE_APPROVED', 'classification' => 'operating_expense', 'version' => $version, 'currency' => 'VND', 'effective_from' => $from, 'effective_to' => $to, 'lines' => [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense, 'requires_cost_center' => true], ['entry_side' => 'credit', 'line_role' => 'ACCRUAL', 'account_id' => $accrual]]];
    }

    private function assertPolicyError(callable $operation, string $message, string $key = 'accounting_policy'): void
    {
        try {
            $operation();
            self::fail('Expected policy validation error.');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()[$key] ?? null;
            self::assertIsArray($messages);
            self::assertSame($message, $messages[0] ?? null);
        }
    }
}
