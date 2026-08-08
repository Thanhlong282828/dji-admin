<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PayrollEndToEndOperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['payroll-period.view', 'payroll-period.create', 'payroll-period.update', 'payroll-period.open', 'payroll-period.lock', 'payroll-period.close', 'payroll-period.delete', 'payroll-input.generate', 'payroll-input.validate', 'payroll-input.create-version', 'payroll.run.view', 'payroll.run.create', 'payroll.run.calculate', 'payroll.run.submit', 'payroll.run.approve', 'payroll.run.reject', 'payroll.run.finalize', 'payroll.item.view', 'payroll.export'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_complete_http_workflow_uses_exact_snapshot_and_finalizes_immutably(): void
    {
        [$company,$maker,$approver] = $this->fixture();
        $maker->givePermissionTo(array_values(array_diff($this->permissions, ['payroll.run.approve', 'payroll.run.finalize'])));
        $approver->givePermissionTo(['payroll.run.view', 'payroll.run.approve', 'payroll.run.reject', 'payroll.run.finalize', 'payroll.item.view']);
        $periodKey = (string) Str::uuid();
        $this->actingAs($maker)->post(route('admin.payroll.periods.store'), ['company_id' => $company, 'code' => '2027-01', 'name' => 'Payroll January 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31', 'pay_date' => '2027-02-05', 'status' => 'draft', 'idempotency_key' => $periodKey])->assertRedirect();
        $period = $this->id(DB::table('payroll_periods')->where('company_id', $company)->where('code', '2027-01')->value('id'));
        $this->actingAs($maker)->post(route('admin.payroll.periods.open', ['period' => $period]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->get(route('admin.payroll.inputs.create', ['company_id' => $company]))->assertOk()->assertSee('2027-01');
        $this->actingAs($maker)->post(route('admin.payroll.inputs.store'), ['company_id' => $company, 'payroll_period_id' => $period, 'idempotency_key' => (string) Str::uuid(), 'note' => 'E2E exact approved sources'])->assertRedirect();
        $snapshot = $this->id(DB::table('payroll_input_snapshots')->where('company_id', $company)->where('payroll_period_id', $period)->value('id'));
        $this->assertDatabaseHas('payroll_input_snapshots', ['id' => $snapshot, 'status' => 'ready', 'employee_count' => 2]);
        $this->actingAs($maker)->post(route('admin.payroll.inputs.validate', ['snapshot' => $snapshot]), ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $persistedWorked = $this->id(DB::table('payroll_employee_inputs')->where('payroll_input_snapshot_id', $snapshot)->sum('worked_minutes'));
        DB::table('attendance_records')->where('company_id', $company)->update(['worked_minutes' => 1]);
        $this->actingAs($maker)->post(route('admin.payroll.inputs.create-run', ['snapshot' => $snapshot]), ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $run = $this->id(DB::table('payroll_runs')->where('company_id', $company)->value('id'));
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'payroll_input_snapshot_id' => $snapshot, 'status' => 'draft']);
        $this->mutate($maker, $company, $run, 'calculate')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'calculated', 'employee_count' => 2, 'calculation_version' => 1]);
        self::assertSame(2, DB::table('payroll_items')->where('payroll_run_id', $run)->count());
        self::assertSame($persistedWorked, $this->id(DB::table('payroll_items')->where('payroll_run_id', $run)->sum('worked_minutes')));
        $item = $this->id(DB::table('payroll_items')->where('payroll_run_id', $run)->value('id'));
        $this->actingAs($maker)->get(route('admin.payroll.runs.items.show', ['run' => $run, 'item' => $item, 'company_id' => $company]))->assertOk()->assertSee('Foundation Gross')->assertSee('Calculation V1');
        $this->assertAggregates($run);
        $this->mutate($maker, $company, $run, 'submit')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'submitted', 'submitted_by' => $maker->id]);
        $this->mutate($approver, $company, $run, 'approve')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'approved', 'approved_by' => $approver->id]);
        $this->mutate($approver, $company, $run, 'finalize')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'finalized', 'finalized_by' => $approver->id]);
        $itemCount = DB::table('payroll_items')->where('payroll_run_id', $run)->count();
        $breakdownCount = DB::table('payroll_item_components')->where('payroll_run_id', $run)->count();
        $this->mutate($maker, $company, $run, 'recalculate')->assertForbidden();
        self::assertSame($itemCount, DB::table('payroll_items')->where('payroll_run_id', $run)->count());
        self::assertSame($breakdownCount, DB::table('payroll_item_components')->where('payroll_run_id', $run)->count());
        self::assertSame(['created', 'calculated', 'submitted', 'approved', 'finalized'], DB::table('payroll_run_histories')->where('payroll_run_id', $run)->orderBy('id')->pluck('action')->all());
        self::assertGreaterThanOrEqual(1, DB::table('notifications')->where('notifiable_id', $approver->id)->count());
        self::assertGreaterThanOrEqual(1, DB::table('notification_deliveries')->where('company_id', $company)->where('entity_id', $run)->count());
        $this->actingAs($approver)->get(route('admin.payroll.runs.show', ['run' => $run, 'company_id' => $company]))->assertOk()->assertSee('FINALIZED')->assertSee('Snapshot #'.$snapshot)->assertSee('Không có action hợp lệ');
    }

    public function test_invalid_transitions_blocked_snapshot_and_tenant_ids_are_rejected(): void
    {
        [$a,$maker] = $this->fixture('NEG-A');
        [$b,$foreign] = $this->fixture('NEG-B');
        $maker->givePermissionTo($this->permissions);
        $foreign->givePermissionTo($this->permissions);
        $period = $this->period($a, 'NEG-DRAFT', 'draft');
        $this->actingAs($maker)->post(route('admin.payroll.inputs.store'), ['company_id' => $a, 'payroll_period_id' => $period, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors();
        $foreignRun = $this->draftRun($b, $foreign->id);
        $this->actingAs($maker)->get(route('admin.payroll.runs.show', ['run' => $foreignRun, 'company_id' => $a]))->assertNotFound();
        $this->actingAs($maker)->post(route('admin.payroll.runs.mutate', ['run' => $foreignRun, 'action' => 'calculate']), ['company_id' => $b, 'version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $draftRun = $this->draftRun($a, $maker->id);
        $this->mutate($maker, $a, $draftRun, 'submit')->assertForbidden();
        $this->mutate($maker, $a, $draftRun, 'approve')->assertForbidden();
        $this->mutate($maker, $a, $draftRun, 'finalize')->assertForbidden();
    }

    public function test_submitted_run_can_be_rejected_with_required_reason(): void
    {
        [$company,$actor] = $this->fixture('REJECT');
        $actor->givePermissionTo(['payroll.run.view', 'payroll.run.reject']);
        $run = $this->draftRun($company, $actor->id);
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'submitted', 'version' => 2, 'submitted_by' => $actor->id, 'submitted_at' => now()]);
        $this->mutate($actor, $company, $run, 'reject')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'rejected', 'rejected_by' => $actor->id]);
        $this->assertDatabaseHas('payroll_run_histories', ['payroll_run_id' => $run, 'action' => 'rejected', 'safe_reason' => 'Configuration requires correction']);
    }

    /** @return TestResponse<Response> */
    private function mutate(User $user, int $company, int $run, string $action): TestResponse
    {
        $version = $this->id(DB::table('payroll_runs')->where('id', $run)->value('version'));

        return $this->actingAs($user)->post(route('admin.payroll.runs.mutate', ['run' => $run, 'action' => $action]), ['company_id' => $company, 'version' => $version, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Configuration requires correction']);
    }

    private function assertAggregates(int $run): void
    {
        $r = DB::table('payroll_runs')->where('id', $run)->first();
        self::assertNotNull($r);
        self::assertSame($this->id($r->employee_count), DB::table('payroll_items')->where('payroll_run_id', $run)->count());
        self::assertSame($this->id($r->gross_total_minor), $this->id(DB::table('payroll_items')->where('payroll_run_id', $run)->sum('gross_amount_minor')));
        self::assertSame($this->id($r->deduction_total_minor), $this->id(DB::table('payroll_items')->where('payroll_run_id', $run)->sum('deduction_amount_minor')));
        self::assertSame($this->id($r->net_total_minor), $this->id(DB::table('payroll_items')->where('payroll_run_id', $run)->sum('net_amount_minor')));
    }

    /** @return array{int,User,User} */
    private function fixture(string $token = 'E2E'): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $token.'-'.Str::upper(Str::random(4)), 'name' => 'Schema Payroll '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $approver = User::factory()->create();
        foreach ([$maker, $approver] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $unit = (int) DB::table('organization_units')->insertGetId(['company_id' => $company->id, 'type' => 'department', 'code' => 'PAY-'.$token, 'name' => 'Payroll Operations', 'headcount_limit' => 10, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $position = (int) DB::table('job_positions')->insertGetId(['company_id' => $company->id, 'organization_unit_id' => $unit, 'code' => 'PAY-SPEC-'.$token, 'title' => 'Payroll Specialist', 'approved_headcount' => 10, 'budgeted_cost' => 0, 'is_critical' => true, 'created_at' => now(), 'updated_at' => now()]);
        $employees = [];
        foreach ([1, 2] as $index) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $index === 1 ? $maker->id : $approver->id, 'employee_code' => $token.'-00'.$index, 'full_name' => 'Payroll Employee '.$index, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            $employees[] = $employee->id;
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $unit, 'job_position_id' => $position, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employment_contracts')->insert(['employee_id' => $employee->id, 'contract_number' => $token.'-CON-'.$index, 'type' => 'indefinite', 'starts_at' => '2026-01-01', 'base_salary' => 0, 'currency' => 'VND', 'terms' => '{}', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2027-01-02', 'scheduled_minutes' => 480, 'worked_minutes' => 450, 'break_minutes' => 0, 'late_minutes' => 30, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $service = app(PayrollConfigurationService::class);
        $component = $service->createComponent($company->id, ['code' => 'BASIC', 'name' => 'Basic Foundation', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'default_value_minor' => 5000000, 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'status' => 'active'], $maker->id);
        foreach ($employees as $employee) {
            $service->assign($company->id, ['employee_id' => $employee, 'salary_component_id' => $component->id, 'amount_minor' => 5000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid()], $maker->id);
        }

        return [$company->id, $maker, $approver];
    }

    private function period(int $company, string $code, string $status): int
    {
        return (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company, 'code' => $code, 'name' => $code, 'period_start' => '2027-01-01', 'period_end' => '2027-01-31', 'cutoff_at' => '2027-01-31 23:59:59', 'payment_date' => '2027-02-05', 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function draftRun(int $company, int $actor): int
    {
        $period = $this->period($company, 'RUN-'.Str::upper(Str::random(5)), 'review');
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'ready', 'generated_by' => $actor, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('payroll_runs')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'payroll_period_id' => $period, 'payroll_input_snapshot_id' => $snapshot, 'code' => 'RUN-NEG-'.Str::upper(Str::random(4)), 'run_number' => 'RUN-NEG-'.Str::upper(Str::random(4)), 'period_start' => '2027-01-01', 'period_end' => '2027-01-31', 'status' => 'draft', 'currency_code' => 'VND', 'formula_version' => '{}', 'created_by' => $actor, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function id(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
