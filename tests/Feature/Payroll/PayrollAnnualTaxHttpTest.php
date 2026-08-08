<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PayrollAnnualTaxHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_secured_list_create_and_company_scoped_detail_pages_render(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.annual_tax.view', 'payroll.annual_tax.create'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ATHTTP', 'name' => 'Schema Annual HTTP', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $user->givePermissionTo(['payroll.annual_tax.view', 'payroll.annual_tax.create']);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AT-HTTP', 'full_name' => 'Annual HTTP Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $this->get(route('admin.payroll.annual-tax.index', ['company_id' => $company->id]))->assertRedirect('/login');
        $this->actingAs($user)->get(route('admin.payroll.annual-tax.index', ['company_id' => $company->id]))->assertOk()->assertSee('Annual Tax Reconciliation');
        $this->actingAs($user)->get(route('admin.payroll.annual-tax.create', ['company_id' => $company->id]))->assertOk()->assertSee($employee->employee_code);
        $this->actingAs($user)->post(route('admin.payroll.annual-tax.store'), ['company_id' => $company->id, 'employee_id' => $employee->id, 'tax_year' => 2026, 'jurisdiction' => 'VN', 'currency' => 'VND', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $rawId = DB::table('payroll_annual_tax_reconciliations')->value('id');
        self::assertIsNumeric($rawId);
        $id = (int) $rawId;
        $this->actingAs($user)->get(route('admin.payroll.annual-tax.show', ['reconciliation' => $id, 'company_id' => $company->id]))->assertOk()->assertSee('Employer-scoped');
    }
}
