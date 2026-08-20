<?php

namespace Tests\Feature\Employee;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Tests\TestCase;

final class EmployeeAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_create_login_disable_reenable_and_optional_password_update(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ACCOUNT', 'name' => 'Account Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'ACC-001', 'full_name' => 'Nguyễn Tài Khoản', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $service = app(EmployeeAccountService::class);
        $user = $service->save($company->id, $employee->id, ['email' => 'account.employee@example.test', 'password' => 'LocalAccount123!', 'role' => 'Employee', 'access_enabled' => true, 'verified' => true]);
        self::assertTrue(Hash::check('LocalAccount123!', $user->password));
        self::assertTrue($user->hasRole('Employee'));
        $this->assertDatabaseHas('company_user_access', ['company_id' => $company->id, 'user_id' => $user->id]);
        $this->post('/login', ['email' => $user->email, 'password' => 'LocalAccount123!'])->assertRedirect('/dashboard');
        $this->post('/logout');

        $service->setAccess($company->id, $employee->id, false, 99999);
        $this->post('/login', ['email' => $user->email, 'password' => 'LocalAccount123!'])->assertSessionHasErrors('email');
        $service->setAccess($company->id, $employee->id, true, 99999);
        $originalHash = $user->fresh()?->password;
        $updated = $service->save($company->id, $employee->id, ['email' => 'account.employee@example.test', 'role' => 'Employee', 'access_enabled' => true, 'verified' => true]);
        self::assertSame($originalHash, $updated->password);
        $this->post('/login', ['email' => $updated->email, 'password' => 'LocalAccount123!'])->assertRedirect('/dashboard');
    }
}
