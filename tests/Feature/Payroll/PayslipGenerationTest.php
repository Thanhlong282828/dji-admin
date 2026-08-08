<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

final class PayslipGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.payslip.view', 'payroll.payslip.generate', 'payroll.payslip.publish', 'payroll.payslip.download', 'payroll.payslip.revoke', 'payroll.payslip.regenerate'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }

    public function test_official_pdf_uses_an_immutable_finalized_calculation_snapshot(): void
    {
        [$company, $user, $run, $item, $employee] = $this->finalizedRun('PDF');
        $user->givePermissionTo(['payroll.payslip.view', 'payroll.payslip.generate', 'payroll.payslip.download']);
        $key = (string) Str::uuid();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'company_id' => $company, 'status' => 'finalized']);
        $this->assertDatabaseHas('payroll_items', ['id' => $item, 'company_id' => $company, 'payroll_run_id' => $run]);

        $response = $this->actingAs($user)->post(route('admin.payroll.payslips.generate-item', ['run' => $run, 'item' => $item]), ['company_id' => $company, 'idempotency_key' => $key]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $payslip = DB::table('payroll_payslips')->where('payroll_item_id', $item)->first();
        self::assertNotNull($payslip);
        $response->assertRedirect(route('admin.payroll.payslips.show', ['payslip' => $payslip->id, 'company_id' => $company]));
        self::assertSame('generated', $payslip->status);
        self::assertSame('application/pdf', $payslip->mime_type);
        self::assertIsString($payslip->storage_path);
        Storage::disk('local')->assertExists($payslip->storage_path);
        $bytes = Storage::disk('local')->get($payslip->storage_path);
        self::assertIsString($bytes);
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertSame(hash('sha256', $bytes), $payslip->checksum);
        self::assertSame(strlen($bytes), $this->integer($payslip->file_size));

        $before = DB::table('payroll_payslip_snapshots')->where('payroll_payslip_id', $payslip->id)->value('snapshot_payload');
        DB::table('employees')->where('id', $employee)->update(['full_name' => 'Live profile changed']);
        DB::table('payroll_item_components')->where('payroll_item_id', $item)->update(['amount_minor' => 999999]);
        $this->actingAs($user)->post(route('admin.payroll.payslips.generate-item', ['run' => $run, 'item' => $item]), ['company_id' => $company, 'idempotency_key' => $key])->assertRedirect();
        self::assertSame(1, DB::table('payroll_payslips')->where('payroll_item_id', $item)->count());
        self::assertSame($before, DB::table('payroll_payslip_snapshots')->where('payroll_payslip_id', $payslip->id)->value('snapshot_payload'));
    }

    public function test_publish_download_revoke_and_integrity_controls_are_enforced(): void
    {
        [$company, $user, $run, $item] = $this->finalizedRun('LIFE');
        $user->givePermissionTo(Permission::query()->where('name', 'like', 'payroll.payslip.%')->get());
        $this->actingAs($user)->post(route('admin.payroll.payslips.generate-item', ['run' => $run, 'item' => $item]), ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $id = $this->integer(DB::table('payroll_payslips')->where('payroll_item_id', $item)->value('id'));

        $this->actingAs($user)->post(route('admin.payroll.payslips.publish', ['payslip' => $id]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($user)->post(route('admin.payroll.payslips.publish', ['payslip' => $id]), ['company_id' => $company])->assertRedirect();
        $this->assertDatabaseHas('payroll_payslips', ['id' => $id, 'status' => 'published', 'delivery_status' => 'delivered']);
        self::assertSame(1, DB::table('payroll_payslip_access_logs')->where('payroll_payslip_id', $id)->where('action', 'publish')->count());
        $this->actingAs($user)->get(route('admin.payroll.payslips.download', ['payslip' => $id, 'company_id' => $company]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->post(route('admin.payroll.payslips.revoke', ['payslip' => $id]), ['company_id' => $company])->assertSessionHasErrors('reason');
        $this->actingAs($user)->post(route('admin.payroll.payslips.revoke', ['payslip' => $id]), ['company_id' => $company, 'reason' => 'Corrected payroll document required'])->assertRedirect();
        $this->actingAs($user)->get(route('admin.payroll.payslips.download', ['payslip' => $id, 'company_id' => $company]))->assertForbidden();
    }

    public function test_generation_requires_auth_permission_company_access_and_finalized_source(): void
    {
        [$company, $user, $run, $item] = $this->finalizedRun('AUTH');
        $url = route('admin.payroll.payslips.generate-item', ['run' => $run, 'item' => $item]);
        $payload = ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()];
        $this->post($url, $payload)->assertRedirect('/login');
        $this->actingAs($user)->post($url, $payload)->assertForbidden();

        $foreign = User::factory()->create();
        $foreign->givePermissionTo('payroll.payslip.generate');
        $this->actingAs($foreign)->post($url, $payload)->assertForbidden();

        $user->givePermissionTo('payroll.payslip.generate');
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'approved']);
        $this->actingAs($user)->post($url, $payload)->assertSessionHasErrors('run');
        self::assertSame(0, DB::table('payroll_payslips')->count());
    }

    /** @return array{int, User, int, int, int} */
    private function finalizedRun(string $token): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PS-'.$token.'-'.Str::upper(Str::random(4)), 'name' => 'Schema Payslip '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $approver = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'PS-'.$token, 'full_name' => 'Payslip Employee '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'PS-'.$token, 'name' => 'Payslip Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $input = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $user->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $input, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC-'.$token, 'name' => 'Basic Salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $input, 'RUN-'.$token, 'VND', (string) Str::uuid(), $user->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $user->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $user->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $approver->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $approver->id);
        $item = DB::table('payroll_items')->where('payroll_run_id', $run->id)->value('id');
        self::assertIsNumeric($item);

        return [$company->id, $user, $run->id, (int) $item, $employee->id];
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        self::fail('Expected an integer database value.');
    }
}
