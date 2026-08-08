<?php

namespace Tests\Support\Payroll;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PayrollBrowserFixtureSeeder
{
    /** @return array{company_id:int,maker_email:string,approver_email:string,password:string} */
    public function run(): array
    {
        if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
            throw new RuntimeException('Browser fixture is restricted to a *_testing database.');
        }
        $token = Str::upper(Str::random(6));
        $password = 'BrowserE2E!2027';
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'BROWSER-'.$token, 'name' => 'Schema Browser Payroll '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::query()->create(['name' => 'Browser Payroll Maker', 'email' => 'maker.'.$token.'@schema.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        $approver = User::query()->create(['name' => 'Browser Payroll Approver', 'email' => 'approver.'.$token.'@schema.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        $permissions = ['payroll-period.view', 'payroll-period.create', 'payroll-period.update', 'payroll-period.open', 'payroll-period.lock', 'payroll-period.close', 'payroll-period.delete', 'payroll-input.generate', 'payroll-input.validate', 'payroll-input.create-version', 'payroll.run.view', 'payroll.run.create', 'payroll.run.calculate', 'payroll.run.submit', 'payroll.run.approve', 'payroll.run.reject', 'payroll.run.finalize', 'payroll.item.view', 'payroll.export'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $maker->givePermissionTo(array_values(array_diff($permissions, ['payroll.run.approve', 'payroll.run.finalize'])));
        $approver->givePermissionTo(['payroll.run.view', 'payroll.run.approve', 'payroll.run.reject', 'payroll.run.finalize', 'payroll.item.view']);
        foreach ([$maker, $approver] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $unit = (int) DB::table('organization_units')->insertGetId(['company_id' => $company->id, 'type' => 'department', 'code' => 'BROWSER-PAY-'.$token, 'name' => 'Browser Payroll Operations', 'headcount_limit' => 10, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $position = (int) DB::table('job_positions')->insertGetId(['company_id' => $company->id, 'organization_unit_id' => $unit, 'code' => 'BROWSER-SPEC-'.$token, 'title' => 'Browser Payroll Specialist', 'approved_headcount' => 10, 'budgeted_cost' => 0, 'is_critical' => true, 'created_at' => now(), 'updated_at' => now()]);
        $employees = [];
        foreach ([1, 2] as $index) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $index === 1 ? $maker->id : $approver->id, 'employee_code' => 'BR-'.$token.'-'.$index, 'full_name' => 'Browser Payroll Employee '.$index, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            $employees[] = $employee->id;
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $unit, 'job_position_id' => $position, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employment_contracts')->insert(['employee_id' => $employee->id, 'contract_number' => 'BR-'.$token.'-CON-'.$index, 'type' => 'indefinite', 'starts_at' => '2026-01-01', 'base_salary' => 0, 'currency' => 'VND', 'terms' => '{}', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2027-03-02', 'scheduled_minutes' => 480, 'worked_minutes' => 450, 'break_minutes' => 0, 'late_minutes' => 30, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        $service = app(PayrollConfigurationService::class);
        $component = $service->createComponent($company->id, ['code' => 'BASIC', 'name' => 'Basic Foundation', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'default_value_minor' => 5000000, 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'status' => 'active'], $maker->id);
        foreach ($employees as $employee) {
            $service->assign($company->id, ['employee_id' => $employee, 'salary_component_id' => $component->id, 'amount_minor' => 5000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid()], $maker->id);
        }

        return ['company_id' => $company->id, 'maker_email' => $maker->email, 'approver_email' => $approver->email, 'password' => $password];
    }
}
