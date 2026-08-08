<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PayrollPaymentBatchFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.payment.view', 'payroll.payment.account.manage', 'payroll.payment.account.verify', 'payroll.payment.batch.create', 'payroll.payment.batch.review', 'payroll.payment.batch.approve', 'payroll.payment.batch.release', 'payroll.payment.batch.download'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }

    public function test_payment_account_is_encrypted_masked_effective_and_verified(): void
    {
        [$company,$maker,,$employee] = $this->fixture('ACC');
        $maker->givePermissionTo(['payroll.payment.view', 'payroll.payment.account.manage', 'payroll.payment.account.verify']);
        $payload = $this->accountPayload($company, $employee, '=001234567890');
        $this->actingAs($maker)->post(route('admin.payroll.payment-accounts.store', ['employee' => $employee]), $payload)->assertRedirect();
        $account = DB::table('employee_payment_accounts')->where('employee_id', $employee)->first();
        self::assertNotNull($account);
        self::assertNotSame('=001234567890', $account->account_number_encrypted);
        self::assertSame('7890', $account->account_number_last4);
        self::assertSame('=001234567890', Crypt::decryptString($this->string($account->account_number_encrypted)));
        $this->actingAs($maker)->get(route('admin.payroll.payment-accounts.show', ['employee' => $employee, 'company_id' => $company]))->assertOk()->assertSee('••••7890')->assertDontSee('=001234567890');
        $this->actingAs($maker)->post(route('admin.payroll.payment-accounts.verify', ['account' => $account->id]), ['company_id' => $company])->assertRedirect();
        $this->assertDatabaseHas('employee_payment_accounts', ['id' => $account->id, 'verification_status' => 'verified', 'verified_by' => $maker->id]);
        $this->actingAs($maker)->post(route('admin.payroll.payment-accounts.store', ['employee' => $employee]), $payload)->assertSessionHasErrors('effective_from');
    }

    public function test_finalized_net_after_tax_snapshot_maker_checker_and_private_release(): void
    {
        [$company,$maker,$checker,$employee,$run,$item,$net] = $this->fixture('FLOW');
        $maker->givePermissionTo(['payroll.payment.account.manage', 'payroll.payment.account.verify', 'payroll.payment.batch.create', 'payroll.payment.batch.review', 'payroll.payment.batch.approve', 'payroll.payment.view']);
        $checker->givePermissionTo(['payroll.payment.batch.approve', 'payroll.payment.batch.release', 'payroll.payment.batch.download', 'payroll.payment.view']);
        $this->actingAs($maker)->post(route('admin.payroll.payment-accounts.store', ['employee' => $employee]), $this->accountPayload($company, $employee, '1234567890'));
        $accountId = $this->integer(DB::table('employee_payment_accounts')->where('employee_id', $employee)->value('id'));
        $this->actingAs($maker)->post(route('admin.payroll.payment-accounts.verify', ['account' => $accountId]), ['company_id' => $company]);
        $key = (string) Str::uuid();
        $this->actingAs($maker)->post(route('admin.payroll.payment-batches.store'), ['company_id' => $company, 'run_id' => $run, 'idempotency_key' => $key])->assertRedirect();
        $batch = $this->integer(DB::table('payroll_payment_batches')->where('payroll_run_id', $run)->value('id'));
        $this->assertDatabaseHas('payroll_payment_batch_items', ['payment_batch_id' => $batch, 'payroll_item_id' => $item, 'calculation_version' => 1, 'amount' => $net]);
        self::assertSame($net, $this->integer(DB::table('payroll_payment_batches')->where('id', $batch)->value('total_amount')));
        DB::table('employee_payment_accounts')->where('id', $accountId)->update(['account_number_encrypted' => Crypt::encryptString('9999999999'), 'account_number_last4' => '9999']);
        DB::table('employees')->where('id', $employee)->update(['full_name' => 'Live Name Changed']);
        $this->actingAs($maker)->post(route('admin.payroll.payment-batches.validate', ['batch' => $batch]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.payroll.payment-batches.review', ['batch' => $batch]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.payroll.payment-batches.approve', ['batch' => $batch]), ['company_id' => $company])->assertSessionHasErrors('actor');
        $this->actingAs($checker)->post(route('admin.payroll.payment-batches.approve', ['batch' => $batch]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.payroll.payment-batches.release', ['batch' => $batch]), ['company_id' => $company])->assertRedirect();
        $released = DB::table('payroll_payment_batches')->where('id', $batch)->first();
        self::assertNotNull($released);
        self::assertSame('released', $released->status);
        self::assertIsString($released->storage_path);
        Storage::disk('local')->assertExists($released->storage_path);
        $csv = Storage::disk('local')->get($released->storage_path);
        self::assertIsString($csv);
        self::assertStringContainsString('1234567890', $csv);
        self::assertStringNotContainsString('9999999999', $csv);
        self::assertStringContainsString("'=", $csv);
        self::assertSame(hash('sha256', $csv), $released->checksum);
        $releasedPath = $this->string($released->storage_path);
        $releasedChecksum = $this->string($released->checksum);
        $this->actingAs($checker)->post(route('admin.payroll.payment-batches.release', ['batch' => $batch]), ['company_id' => $company])->assertRedirect();
        $retriedRelease = DB::table('payroll_payment_batches')->where('id', $batch)->first();
        self::assertNotNull($retriedRelease);
        self::assertSame($releasedPath, $retriedRelease->storage_path);
        self::assertSame($releasedChecksum, $retriedRelease->checksum);
        self::assertSame(1, DB::table('payroll_payment_access_logs')->where('payment_batch_id', $batch)->where('action', 'release_file')->count());
        $this->actingAs($checker)->get(route('admin.payroll.payment-batches.download', ['batch' => $batch, 'company_id' => $company]))->assertOk()->assertHeader('cache-control', 'no-store, private');
        self::assertSame(1, DB::table('payroll_payment_access_logs')->where('payment_batch_id', $batch)->where('action', 'download_file')->count());
        $this->actingAs($maker)->post(route('admin.payroll.payment-batches.store'), ['company_id' => $company, 'run_id' => $run, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        self::assertSame(1, DB::table('payroll_payment_batches')->where('payroll_run_id', $run)->count());
        self::assertSame(1, DB::table('payroll_payment_batch_items')->where('payroll_item_id', $item)->count());
    }

    public function test_auth_tenant_and_non_finalized_run_are_blocked(): void
    {
        [$company,$maker,,$employee,$run] = $this->fixture('SEC');
        $url = route('admin.payroll.payment-batches.store');
        $payload = ['company_id' => $company, 'run_id' => $run, 'idempotency_key' => (string) Str::uuid()];
        $this->post($url, $payload)->assertRedirect('/login');
        $this->actingAs($maker)->post($url, $payload)->assertForbidden();
        $maker->givePermissionTo('payroll.payment.batch.create');
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'approved']);
        $this->actingAs($maker)->post($url, $payload)->assertSessionHasErrors('run');
        $foreign = User::factory()->create();
        $foreign->givePermissionTo('payroll.payment.batch.create');
        $this->actingAs($foreign)->post($url, $payload)->assertForbidden();
        self::assertSame(0, DB::table('payroll_payment_batches')->count());
    }

    /** @return array{int,User,User,int,int,int,int} */
    private function fixture(string $token): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'BANK-'.$token.'-'.Str::upper(Str::random(3)), 'name' => 'Schema Bank '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        foreach ([$maker, $checker] as $u) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $u->id]);
        }$employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'PAY-'.$token, 'full_name' => 'Beneficiary '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'BANK-'.$token, 'name' => 'Bank Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $maker->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC-'.$token, 'name' => 'Basic', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $snapshot, 'RUN-'.$token, 'VND', (string) Str::uuid(), $maker->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $checker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $checker->id);
        $item = DB::table('payroll_items')->where('payroll_run_id', $run->id)->first();
        self::assertNotNull($item);

        return [$company->id, $maker, $checker, $employee->id, $run->id, $this->integer($item->id), $this->integer($item->net_after_personal_income_tax_minor)];
    }

    /** @return array<string,mixed> */
    private function accountPayload(int $company, int $employee, string $number): array
    {
        return ['company_id' => $company, 'employee_id' => $employee, 'payment_method' => 'bank_transfer', 'bank_code' => 'SCHEMA-BANK', 'bank_name' => 'Schema Internal Bank', 'branch_code' => 'HCM', 'account_number' => $number, 'account_holder_name' => '=BENEFICIARY '.$employee, 'currency' => 'VND', 'is_primary' => 1, 'effective_from' => '2026-01-01', 'status' => 'active'];
    }

    private function integer(mixed $v): int
    {
        if (is_int($v)) {
            return $v;
        }if (is_string($v) && ctype_digit($v)) {
            return (int) $v;
        }self::fail('Expected integer.');
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        self::fail('Expected string.');
    }
}
