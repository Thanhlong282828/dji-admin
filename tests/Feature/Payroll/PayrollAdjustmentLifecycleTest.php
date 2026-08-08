<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PayrollAdjustmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_http_lifecycle_cumulative_delta_maker_checker_and_immutability(): void
    {
        [$company,$maker,$checker,$run,$item,$reason] = $this->fixture('FLOW');
        $maker->givePermissionTo($this->permissions);
        $checker->givePermissionTo($this->permissions);
        $before = (array) DB::table('payroll_items')->where('id', $item)->first();
        $key = (string) Str::uuid();
        $payload = ['company_id' => $company, 'source_payroll_item_id' => $item, 'adjustment_reason_id' => $reason, 'adjustment_type' => 'earning_correction', 'requested_effective_date' => '2026-07-31', 'description' => 'Approved correction evidence', 'idempotency_key' => $key];
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect();
        $id = $this->integer(DB::table('payroll_adjustment_requests')->value('id'));
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect();
        self::assertSame(1, DB::table('payroll_adjustment_requests')->count());
        $correct = ['company_id' => $company, 'corrected_gross_minor' => 5_100_000, 'corrected_employee_insurance_minor' => 0, 'corrected_employer_insurance_minor' => 0, 'corrected_personal_income_tax_minor' => 0, 'corrected_net_minor' => 5_100_000];
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'calculate']), $correct)->assertRedirect();
        foreach (['validate', 'submit', 'review'] as $action) {
            $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => $action]), ['company_id' => $company])->assertRedirect();
        }
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'approve']), ['company_id' => $company])->assertSessionHasErrors('approval');
        $this->actingAs($checker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'approve']), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'apply']), ['company_id' => $company])->assertRedirect();
        self::assertSame(100_000, $this->integer(DB::table('payroll_adjustment_runs')->where('adjustment_request_id', $id)->value('delta_net_minor')));
        self::assertSame('eligible', DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $id)->where('intent_type', 'payment_eligible')->value('status'));
        self::assertSame(1, DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $id)->count());
        self::assertEquals($before, (array) DB::table('payroll_items')->where('id', $item)->first());
        self::assertSame('finalized', DB::table('payroll_runs')->where('id', $run)->value('status'));
        $reason2 = (int) DB::table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => 'SECOND', 'name' => 'Second approved correction', 'category' => 'earning_correction', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $payload['adjustment_reason_id'] = $reason2;
        $payload['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect();
        $second = $this->integer(DB::table('payroll_adjustment_requests')->max('id'));
        $correct['corrected_gross_minor'] = 5_150_000;
        $correct['corrected_net_minor'] = 5_150_000;
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $second, 'action' => 'calculate']), $correct)->assertRedirect();
        self::assertSame(50_000, $this->integer(DB::table('payroll_adjustment_components')->where('adjustment_request_id', $second)->where('component_code', 'NET')->value('delta_amount_minor')));
    }

    public function test_auth_tenant_non_finalized_and_negative_recovery_controls(): void
    {
        [$company,$maker,$checker,$run,$item,$reason] = $this->fixture('SEC');
        $payload = ['company_id' => $company, 'source_payroll_item_id' => $item, 'adjustment_reason_id' => $reason, 'adjustment_type' => 'deduction_correction', 'requested_effective_date' => '2026-07-31', 'description' => 'Controlled correction', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect('/login');
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertForbidden();
        $maker->givePermissionTo($this->permissions);
        $checker->givePermissionTo($this->permissions);
        $foreign = User::factory()->create();
        $foreign->givePermissionTo($this->permissions);
        $this->actingAs($foreign)->post(route('admin.payroll.adjustments.store'), $payload)->assertForbidden();
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'approved']);
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertSessionHasErrors('source_payroll_item_id');
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'finalized']);
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect();
        $id = $this->integer(DB::table('payroll_adjustment_requests')->value('id'));
        $correct = ['company_id' => $company, 'corrected_gross_minor' => 4_900_000, 'corrected_employee_insurance_minor' => 0, 'corrected_employer_insurance_minor' => 0, 'corrected_personal_income_tax_minor' => 0, 'corrected_net_minor' => 4_900_000];
        $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'calculate']), $correct)->assertRedirect();
        foreach (['validate', 'submit', 'review'] as $a) {
            $this->actingAs($maker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => $a]), ['company_id' => $company])->assertRedirect();
        }$this->actingAs($checker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'approve']), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'apply']), ['company_id' => $company])->assertRedirect();
        self::assertSame(100_000, $this->integer(DB::table('payroll_adjustment_recoveries')->where('adjustment_request_id', $id)->value('remaining_amount_minor')));
        self::assertSame(0, DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $id)->where('intent_type', 'payment_eligible')->count());
    }

    /** @return array{int,User,User,int,int,int} */
    private function fixture(string $token): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ADJ-'.$token, 'name' => 'Schema Adjustment '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        foreach ([$maker, $checker] as $u) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $u->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'ADJ-'.$token, 'full_name' => 'Adjustment Employee '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'ADJ-'.$token, 'name' => 'Historical Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $maker->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC-'.$token, 'name' => 'Basic salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5_000_000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $snapshot, 'RUN-'.$token, 'VND', (string) Str::uuid(), $maker->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $checker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $checker->id);
        $item = $this->integer(DB::table('payroll_items')->where('payroll_run_id', $run->id)->value('id'));
        $reason = (int) DB::table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'code' => 'CORR-'.$token, 'name' => 'Approved payroll correction', 'category' => 'earning_correction', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company->id, $maker, $checker, $run->id, $item, $reason];
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        self::fail('Expected integer database value.');
    }
}
