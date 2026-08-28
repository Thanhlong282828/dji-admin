<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class EmployeePortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_employee_portal_is_authorized_and_strictly_self_scoped(): void
    {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => 'PORTAL', 'name' => 'Portal Company',
            'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND',
            'currency' => 'VND', 'status' => 'active', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('employee.portal.view');
        Employee::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'PORTAL-SELF',
            'full_name' => 'Nhân viên Portal', 'nationality' => 'VN', 'join_date' => '2026-01-01',
            'employment_status' => 'active', 'employment_type' => 'full_time',
        ]);
        Employee::query()->create([
            'company_id' => $company->id, 'employee_code' => 'PORTAL-OTHER', 'full_name' => 'Nhân viên Không Được Lộ',
            'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time',
        ]);

        $this->actingAs($user)->get('/employee-portal')->assertOk()
            ->assertSeeText('Nhân viên Portal')
            ->assertDontSeeText('Nhân viên Không Được Lộ');
    }

    public function test_portal_requires_permission_and_linked_employee(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/employee-portal')->assertForbidden();
        $user->givePermissionTo('employee.portal.view');
        $this->actingAs($user)->get('/employee-portal')->assertNotFound();
    }
}
