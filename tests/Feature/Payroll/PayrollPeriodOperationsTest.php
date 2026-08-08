<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Payroll\Domain\Events\PayrollPeriodChanged;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PayrollPeriodOperationsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['payroll-period.view', 'payroll-period.create', 'payroll-period.update', 'payroll-period.open', 'payroll-period.lock', 'payroll-period.close', 'payroll-period.delete', 'payroll-input.generate'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_create_is_validated_idempotent_audited_and_company_scoped(): void
    {
        Event::fake([PayrollPeriodChanged::class]);
        [$company,$user] = $this->actor('PER-A');
        $user->givePermissionTo($this->permissions);
        $payload = ['company_id' => $company, 'code' => '2026-07', 'name' => 'Payroll July 2026', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'pay_date' => '2026-08-05', 'status' => 'draft', 'note' => 'Kỳ lương vận hành', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($user)->post(route('admin.payroll.periods.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('admin.payroll.periods.store'), $payload)->assertRedirect();
        self::assertSame(1, DB::table('payroll_periods')->where('company_id', $company)->count());
        $period = $this->integer(DB::table('payroll_periods')->where('company_id', $company)->value('id'));
        self::assertSame(1, DB::table('payroll_period_histories')->where('payroll_period_id', $period)->where('action', 'created')->count());
        Event::assertDispatchedTimes(PayrollPeriodChanged::class, 1);
        $this->actingAs($user)->post(route('admin.payroll.periods.store'), array_merge($payload, ['idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('code');
        $this->actingAs($user)->post(route('admin.payroll.periods.store'), array_merge($payload, ['code' => 'BAD-DATE', 'idempotency_key' => (string) Str::uuid(), 'end_date' => '2026-06-30']))->assertSessionHasErrors('end_date');
    }

    public function test_lifecycle_dependencies_and_input_options_are_operational(): void
    {
        [$company,$user] = $this->actor('PER-B');
        $user->givePermissionTo($this->permissions);
        $period = $this->period($company, '2026-07', 'draft');
        $this->actingAs($user)->get(route('admin.payroll.inputs.create', ['company_id' => $company]))->assertOk()->assertSee('Chưa có Payroll Period đang mở')->assertSee('+ Tạo Payroll Period');
        $this->actingAs($user)->post(route('admin.payroll.periods.open', ['period' => $period]), ['company_id' => $company])->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', ['id' => $period, 'status' => 'collecting']);
        $this->actingAs($user)->get(route('admin.payroll.inputs.create', ['company_id' => $company]))->assertOk()->assertSee('2026-07')->assertDontSee('Chưa có Payroll Period đang mở');
        $this->actingAs($user)->post(route('admin.payroll.inputs.store'), ['company_id' => $company, 'payroll_period_id' => $period, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseHas('payroll_input_snapshots', ['company_id' => $company, 'payroll_period_id' => $period]);
        $this->actingAs($user)->post(route('admin.payroll.periods.lock', ['period' => $period]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($user)->post(route('admin.payroll.periods.close', ['period' => $period]), ['company_id' => $company])->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', ['id' => $period, 'status' => 'processed']);
        $this->actingAs($user)->get(route('admin.payroll.inputs.create', ['company_id' => $company]))->assertOk()->assertDontSee('2026-07 —');
        $this->actingAs($user)->post(route('admin.payroll.periods.open', ['period' => $period]), ['company_id' => $company])->assertForbidden();

        $draft = $this->period($company, '2026-08', 'draft');
        $this->actingAs($user)->put(route('admin.payroll.periods.update', ['period' => $draft]), ['company_id' => $company, 'code' => '2026-08-X', 'name' => 'August Revised', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'pay_date' => '2026-09-05'])->assertRedirect();
        DB::table('payroll_input_snapshots')->insert(['company_id' => $company, 'payroll_period_id' => $draft, 'snapshot_version' => 1, 'status' => 'draft', 'generated_by' => $user->id, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->put(route('admin.payroll.periods.update', ['period' => $draft]), ['company_id' => $company, 'code' => 'NO', 'name' => 'No', 'start_date' => '2026-08-02', 'end_date' => '2026-08-31', 'pay_date' => '2026-09-05'])->assertSessionHasErrors('period');
        $this->actingAs($user)->delete(route('admin.payroll.periods.destroy', ['period' => $draft]), ['company_id' => $company])->assertSessionHasErrors('period');
    }

    public function test_authorization_overlap_and_foreign_period_are_blocked(): void
    {
        [$companyA,$userA] = $this->actor('PER-C');
        [$companyB,$userB] = $this->actor('PER-D');
        $userA->givePermissionTo($this->permissions);
        $userB->givePermissionTo($this->permissions);
        $open = $this->period($companyA, 'OPEN-A', 'collecting');
        $foreign = $this->period($companyB, 'FOREIGN', 'draft');
        $this->actingAs($userA)->get(route('admin.payroll.periods.show', ['period' => $foreign, 'company_id' => $companyA]))->assertNotFound();
        $this->actingAs($userA)->get(route('admin.payroll.periods.show', ['period' => $foreign, 'company_id' => $companyB]))->assertForbidden();
        $overlap = $this->period($companyA, 'OVERLAP', 'draft', '2026-07-15', '2026-08-15');
        $this->actingAs($userA)->post(route('admin.payroll.periods.open', ['period' => $overlap]), ['company_id' => $companyA])->assertSessionHasErrors('start_date');
        $this->actingAs($userA)->get(route('admin.payroll.periods.index', ['company_id' => $companyA]))->assertOk()->assertSee('OPEN-A')->assertDontSee('FOREIGN');
        $withoutPermission = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $companyA, 'user_id' => $withoutPermission->id]);
        $this->actingAs($withoutPermission)->get(route('admin.payroll.periods.index', ['company_id' => $companyA]))->assertForbidden();
    }

    /** @return array{int,User} */
    private function actor(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return [$company->id, $user];
    }

    private function period(int $company, string $code, string $status, string $start = '2026-07-01', string $end = '2026-07-31'): int
    {
        return (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company, 'code' => $code, 'name' => 'Payroll '.$code, 'period_start' => $start, 'period_end' => $end, 'cutoff_at' => $end.' 23:59:59', 'payment_date' => '2026-09-05', 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
