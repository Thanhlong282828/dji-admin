<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class AdminContextualHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_page_renders_the_specific_structured_guide(): void
    {
        [$company, $user] = $this->actor(['employee.view', 'hr.lifecycle.view', 'organization-unit.view', 'job-position.view']);

        $this->actingAs($user)->get(route('admin.employees.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Hướng dẫn sử dụng trang này')
            ->assertSee('Quản lý hồ sơ Employee, account login và current primary assignment.')
            ->assertSee('Dữ liệu lấy từ đâu')
            ->assertSee('Dữ liệu đầu vào từ')
            ->assertSee('Các chức năng')
            ->assertSee('Cách thao tác')
            ->assertSee('Quy tắc quan trọng')
            ->assertSee('Sau khi lưu thì dữ liệu đi đâu')
            ->assertSee('Dữ liệu sau đó đi tới')
            ->assertSee('Luồng liên kết')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Quyền cần có')
            ->assertSee('EmployeeLifecycleService');
    }

    public function test_related_links_are_filtered_server_side_by_permission(): void
    {
        [$company, $user] = $this->actor(['payroll-period.view']);
        $employeeUrl = route('admin.employees.index', ['company_id' => $company->id]);
        $runUrl = route('admin.payroll.runs.index', ['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('admin.payroll.inputs.index', ['company_id' => $company->id]));
        $response->assertOk()
            ->assertSee('Tạo point-in-time snapshot đầu vào tính lương.')
            ->assertDontSee($employeeUrl, false)
            ->assertDontSee($runUrl, false)
            ->assertDontSee('payroll.assignment.view (xem compensation)');
    }

    public function test_payroll_and_governance_guides_do_not_render_employee_salary_values(): void
    {
        [$company, $user] = $this->actor(['payroll-period.view', 'operations.view']);

        foreach ([
            route('admin.payroll.inputs.index', ['company_id' => $company->id]),
            route('admin.operations.data-quality'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertDontSee('25.000.000')
                ->assertDontSee('55.000.000')
                ->assertDontSee('bank_account');
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Company, 1: User}
     */
    private function actor(array $permissions): array
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'HELP',
            'name' => 'Context Help Test',
            'country_code' => 'VN',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'currency' => 'VND',
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $user->givePermissionTo($permissions);

        return [$company, $user];
    }
}
