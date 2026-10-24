<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Spatie\Permission\Models\Role;
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

    public function test_system_admin_cannot_assign_super_admin_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'CEILING', 'name' => 'Ceiling Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'CEIL-001', 'full_name' => 'Target User', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $actor = User::factory()->create();
        $actor->assignRole(Role::findByName('System Admin', 'web'));

        $this->expectException(ValidationException::class);
        app(EmployeeAccountService::class)->save($company->id, $employee->id, ['email' => 'target@example.test', 'password' => 'LocalAccount123!', 'role' => 'Super Admin', 'access_enabled' => true, 'verified' => true], $actor->id);
    }

    public function test_account_branch_access_sync_is_company_scoped(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'BRANCH-A', 'name' => 'Branch A', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $foreign = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'BRANCH-B', 'name' => 'Branch B', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $branchA = DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'A1', 'name' => 'A1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $branchA2 = DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'A2', 'name' => 'A2', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $foreignBranch = DB::table('branches')->insertGetId(['company_id' => $foreign->id, 'code' => 'B1', 'name' => 'B1', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'BR-001', 'full_name' => 'Branch User', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $service = app(EmployeeAccountService::class);
        $user = $service->save($company->id, $employee->id, ['email' => 'branch.user@example.test', 'password' => 'LocalAccount123!', 'role' => 'Employee', 'access_enabled' => true, 'verified' => true, 'branch_ids' => [$branchA]]);
        DB::table('branch_user_access')->insert(['branch_id' => $foreignBranch, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $service->save($company->id, $employee->id, ['email' => $user->email, 'role' => 'Employee', 'access_enabled' => true, 'verified' => true, 'branch_ids' => [$branchA2]]);

        $this->assertDatabaseMissing('branch_user_access', ['branch_id' => $branchA, 'user_id' => $user->id]);
        $this->assertDatabaseHas('branch_user_access', ['branch_id' => $branchA2, 'user_id' => $user->id]);
        $this->assertDatabaseHas('branch_user_access', ['branch_id' => $foreignBranch, 'user_id' => $user->id]);
    }
}
