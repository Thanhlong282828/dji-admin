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

final class EmployeeCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_unauthorized_user_are_blocked(): void
    {
        $company = $this->company('A');
        $this->get('/admin/employees?company_id='.$company->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/employees?company_id='.$company->id)->assertForbidden();
    }

    public function test_create_encrypts_identity_and_duplicate_code_is_rejected(): void
    {
        $company = $this->company('A');
        $user = $this->authorized($company, ['employee.view', 'employee.create']);
        $this->actingAs($user)->post('/admin/employees', $this->payload($company))->assertRedirect();
        $row = DB::table('employees')->where('employee_code', 'SC-E01')->first();
        self::assertNotSame('DEMO123456', (string) $row->identity_number);
        self::assertSame(64, strlen((string) $row->identity_number_hash));
        $this->actingAs($user)->post('/admin/employees', $this->payload($company))->assertSessionHasErrors('employee_code');
    }

    public function test_cross_company_and_manager_cycle_are_blocked(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $user = $this->authorized($a, ['employee.view', 'employee.update']);
        $one = $this->employee($a, 'A1');
        $two = $this->employee($a, 'A2', $one->id);
        $foreign = $this->employee($b, 'B1');
        $this->actingAs($user)->get('/admin/employees/'.$foreign->id.'?company_id='.$b->id)->assertForbidden();
        $response = $this->actingAs($user)->put('/admin/employees/'.$one->id, $this->payload($a, ['employee_code' => 'A1', 'manager_id' => $two->id]));
        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['id' => $one->id, 'manager_id' => null]);
    }

    private function payload(Company $company, array $extra = []): array
    {
        return [...['company_id' => $company->id, 'employee_code' => 'SC-E01', 'full_name' => 'Nguyễn An', 'work_email' => 'nguyen.an@schema.demo', 'nationality' => 'VN', 'identity_number' => 'DEMO123456', 'join_date' => '2026-01-01', 'official_date' => '2026-03-01', 'employment_status' => 'active', 'employment_type' => 'full_time'], ...$extra];
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'EMP-'.$code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function authorized(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    private function employee(Company $company, string $code, ?int $manager = null): Employee
    {
        return Employee::query()->create(['company_id' => $company->id, 'employee_code' => $code, 'full_name' => $code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'manager_id' => $manager]);
    }
}
