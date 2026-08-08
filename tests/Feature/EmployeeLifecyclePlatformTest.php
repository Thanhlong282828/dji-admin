<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class EmployeeLifecyclePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_full_foundation_lifecycle_is_operable_through_http_and_append_only(): void
    {
        $company = $this->company('LIFE-A');
        $employee = $this->employee($company, 'L001');
        $user = $this->authorized($company, ['hr.lifecycle.view', 'hr.lifecycle.create', 'hr.lifecycle.onboarding', 'hr.lifecycle.terminate']);
        $this->actingAs($user)->post($this->url($employee), $this->payload($company, 'create'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post($this->url($employee).'/start-onboarding', $this->payload($company, 'start'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post($this->url($employee).'/complete-onboarding', $this->payload($company, 'complete'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post($this->url($employee).'/terminate', $this->payload($company, 'terminate', ['reason' => 'Kết thúc hợp đồng']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_lifecycle_events', 4);
        $this->assertDatabaseCount('employment_transitions', 4);
        $this->assertDatabaseCount('employee_lifecycle_outbox', 4);
        $this->assertDatabaseHas('employee_lifecycle_events', ['employee_id' => $employee->id, 'to_state' => 'terminated']);
        self::assertSame(4, DB::table('employment_transitions')->where('employee_id', $employee->id)->distinct('checksum')->count('checksum'));
    }

    public function test_idempotency_and_invalid_transition_do_not_duplicate_history(): void
    {
        $company = $this->company('LIFE-B');
        $employee = $this->employee($company, 'L002');
        $user = $this->authorized($company, ['hr.lifecycle.create', 'hr.lifecycle.onboarding']);
        $payload = $this->payload($company, 'same');
        $this->actingAs($user)->post($this->url($employee), $payload)->assertRedirect();
        $this->actingAs($user)->post($this->url($employee), $payload)->assertRedirect();
        $this->assertDatabaseCount('employee_lifecycle_events', 1);
        $this->actingAs($user)->post($this->url($employee).'/complete-onboarding', $this->payload($company, 'invalid'))->assertSessionHasErrors('state');
        $this->assertDatabaseCount('employment_transitions', 1);
    }

    public function test_permission_and_company_isolation_matrix(): void
    {
        $companyA = $this->company('LIFE-C');
        $companyB = $this->company('LIFE-D');
        $foreign = $this->employee($companyB, 'L003');
        $this->get('/admin/employee-lifecycle?company_id='.$companyA->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/employee-lifecycle?company_id='.$companyA->id)->assertForbidden();
        $viewer = $this->authorized($companyA, ['hr.lifecycle.view']);
        $this->actingAs($viewer)->get('/admin/employee-lifecycle/'.$foreign->id.'?company_id='.$companyA->id)->assertNotFound();
        $this->actingAs($viewer)->post($this->url($foreign).'?company_id='.$companyA->id, $this->payload($companyA, 'forbidden'))->assertForbidden();
    }

    public function test_employee_360_contains_no_payroll_sensitive_fields(): void
    {
        $company = $this->company('LIFE-E');
        $employee = $this->employee($company, 'L004');
        $viewer = $this->authorized($company, ['hr.lifecycle.view']);
        $this->actingAs($viewer)->get('/admin/employee-lifecycle/'.$employee->id.'?company_id='.$company->id)->assertOk()->assertDontSee('base_salary')->assertDontSee('bank_account')->assertDontSee('tax_number');
    }

    private function url(Employee $employee): string
    {
        return '/admin/employee-lifecycle/'.$employee->id;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Company $company, string $key, array $extra = []): array
    {
        return ['company_id' => $company->id, 'idempotency_key' => $key, 'effective_at' => now()->toIso8601String(), ...$extra];
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function employee(Company $company, string $code): Employee
    {
        return Employee::query()->create(['company_id' => $company->id, 'employee_code' => $code, 'full_name' => 'Nguyễn Schema '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
    }

    /** @param list<string> $permissions */
    private function authorized(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
