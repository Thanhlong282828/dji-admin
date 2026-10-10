<?php

namespace Tests\Feature\Tax;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Tax\Application\Commands\ActivateVatPolicyCommand;
use Modules\Tax\Application\Commands\ApproveVatPolicyCommand;
use Modules\Tax\Application\Commands\CreateVatPolicyCommand;
use Modules\Tax\Application\Commands\ExpireVatPolicyCommand;
use Modules\Tax\Application\Commands\UpdateVatPolicyDraftCommand;
use Tests\TestCase;

final class VatPolicyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_versioned_maker_checker_lifecycle_is_immutable_and_overlap_safe(): void
    {
        [$maker, $checker, $company, $category, $taxAccount, $postingPolicy] = $this->runtime();
        $bus = app(CommandBusInterface::class);
        $data = ['policy_key' => 'VAT-OUTPUT', 'tax_category_id' => $category, 'country_code' => 'VN', 'currency' => 'VND', 'rate_basis_points' => 1_000, 'effective_from' => '2026-01-01', 'effective_to' => null, 'tax_account_id' => $taxAccount, 'accounting_posting_policy_id' => $postingPolicy];

        $policy = $bus->dispatch(new CreateVatPolicyCommand($company, $maker, $data));
        self::assertIsInt($policy);
        $bus->dispatch(new UpdateVatPolicyDraftCommand($company, $policy, $maker, $data + ['rate_basis_points' => 800]));
        try {
            $bus->dispatch(new ApproveVatPolicyCommand($company, $policy, $maker));
            self::fail('Maker must not approve its own VAT policy.');
        } catch (ValidationException $exception) {
            self::assertSame('MAKER_CHECKER_REQUIRED', $exception->errors()['approval'][0]);
        }
        $bus->dispatch(new ApproveVatPolicyCommand($company, $policy, $checker));
        self::assertSame('submitted', DB::table('vat_policies')->where('id', $policy)->value('status'));
        $bus->dispatch(new ActivateVatPolicyCommand($company, $policy, $checker));
        self::assertSame('approved', DB::table('vat_policies')->where('id', $policy)->value('status'));
        try {
            $bus->dispatch(new UpdateVatPolicyDraftCommand($company, $policy, $maker, $data));
            self::fail('Approved VAT policy must be immutable.');
        } catch (ValidationException $exception) {
            self::assertSame('VAT_POLICY_APPROVED_IMMUTABLE', $exception->errors()['status'][0]);
        }

        $versionTwo = $bus->dispatch(new CreateVatPolicyCommand($company, $maker, $data));
        self::assertSame(2, (int) DB::table('vat_policies')->where('id', $versionTwo)->value('version'));
        $bus->dispatch(new ApproveVatPolicyCommand($company, $versionTwo, $checker));
        try {
            $bus->dispatch(new ActivateVatPolicyCommand($company, $versionTwo, $checker));
            self::fail('Overlapping VAT policy must not activate.');
        } catch (ValidationException $exception) {
            self::assertSame('VAT_POLICY_EFFECTIVE_PERIOD_OVERLAP', $exception->errors()['effective_from'][0]);
        }
        $bus->dispatch(new ExpireVatPolicyCommand($company, $policy, $checker));
        $bus->dispatch(new ActivateVatPolicyCommand($company, $versionTwo, $checker));
        self::assertSame('approved', DB::table('vat_policies')->where('id', $versionTwo)->value('status'));
    }

    /** @return array{User,User,int,int,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'VAT-LC', 'name' => 'VAT Lifecycle', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$maker, $checker] as $actor) {
            DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $receivable = $this->account($company, $maker->id, '1150', 'Receivable', 'asset', 'debit');
        $revenue = $this->account($company, $maker->id, '4000', 'Revenue', 'revenue', 'credit');
        $tax = $this->account($company, $maker->id, '2180', 'VAT Payable', 'liability', 'credit');
        $service = app(AccountingPostingPolicyService::class);
        $posting = $service->create($company, $maker, ['policy_key' => 'INVOICE-VAT', 'source_type' => 'customer_invoice', 'business_event' => 'CUSTOMER_INVOICE', 'classification' => 'VAT_OUTPUT', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $revenue], ['entry_side' => 'credit', 'line_role' => 'TAX_PAYABLE', 'account_id' => $tax]]]);
        $service->submit($company, $posting, $maker);
        $service->approve($company, $posting, $checker);
        $category = (int) DB::table('tax_categories')->insertGetId(['company_id' => $company, 'code' => 'VAT_OUTPUT', 'name' => 'Output VAT', 'direction' => 'output', 'country_code' => 'VN', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$maker, $checker, $company, $category, $tax, $posting];
    }

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2000-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }
}
