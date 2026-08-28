<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Performance\Application\DTOs\ReportingFilter;
use Modules\Performance\Domain\Services\EnterpriseReportingService;
use Tests\TestCase;

final class EnterpriseReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_hub_requires_auth_permission_and_tenant_access(): void
    {
        $company = $this->company('A');
        $foreign = $this->company('B');
        $this->get(route('admin.reports.index', ['company_id' => $company->id]))->assertRedirect();
        $this->actingAs($this->user($company, []))->get(route('admin.reports.index', ['company_id' => $company->id]))->assertForbidden();
        $this->actingAs($this->user($company, ['analytics.view']))->get(route('admin.reports.index', ['company_id' => $foreign->id]))->assertNotFound();
    }

    public function test_preview_is_read_only_and_restricted_sections_are_absent_server_side(): void
    {
        $company = $this->company('C');
        $this->position($company, 'Engineering', '25123456.00');
        $before = DB::table('job_positions')->count();

        $viewer = $this->user($company, ['analytics.view', 'analytics.export']);
        $this->actingAs($viewer)->get(route('admin.reports.index', ['company_id' => $company->id, 'period_type' => 'quarterly', 'period' => '2026-08']))
            ->assertOk()->assertSee('Enterprise Reporting')->assertSee('Executive Board Pack')->assertSee('Financial sections require payroll.assignment.view')->assertDontSee('25123456');
        $export = $this->actingAs($viewer)->get(route('admin.reports.export', ['format' => 'csv', 'company_id' => $company->id, 'period' => '2026-08']));
        $export->assertOk();
        self::assertStringNotContainsString('25123456', (string) $export->getContent());
        self::assertStringNotContainsString('payroll,gross_minor', (string) $export->getContent());

        self::assertSame($before, DB::table('job_positions')->count());
    }

    public function test_csv_xlsx_and_pdf_exports_have_real_bytes_checksum_and_safe_cells(): void
    {
        $company = $this->company('D');
        $this->position($company, '=Injected Unit', '30000000.00');
        $user = $this->user($company, ['analytics.view', 'analytics.export', 'payroll.assignment.view']);
        foreach (['csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'] as $format => $mime) {
            $response = $this->actingAs($user)->get(route('admin.reports.export', ['format' => $format, 'company_id' => $company->id, 'period' => '2026-08']));
            $response->assertOk()->assertHeader('content-type', $mime);
            $bytes = $response->getContent();
            self::assertIsString($bytes);
            self::assertNotSame('', $bytes);
            self::assertSame(hash('sha256', $bytes), $response->headers->get('X-Report-Checksum-SHA256'));
            if ($format === 'csv') {
                self::assertStringContainsString("'=Injected Unit", $bytes);
            }
            if ($format === 'xlsx') {
                self::assertStringStartsWith('PK', $bytes);
            }
            if ($format === 'pdf') {
                self::assertStringStartsWith('%PDF', $bytes);
            }
        }
    }

    public function test_query_count_is_bounded_when_employee_volume_grows(): void
    {
        $company = $this->company('Q');
        [, $position] = $this->position($company, 'Engineering', '20000000.00');
        $request = request()->create('/admin/reports', 'GET', ['company_id' => $company->id, 'period' => '2026-08']);
        $filter = ReportingFilter::fromRequest($request, (int) $company->id);
        $service = app(EnterpriseReportingService::class);
        DB::enableQueryLog();
        $service->build($filter, true);
        $before = count(DB::getQueryLog());
        foreach (range(1, 50) as $number) {
            $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'REPORT-'.$number, 'full_name' => 'Report Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
            DB::table('employee_assignments')->insert(['employee_id' => $employee->id, 'organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::flushQueryLog();
        $service->build($filter, true);
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertSame($before, $after);
        // Keep the current fixed read-model ceiling; before/after equality is the N+1 gate.
        // C4 adds two composed Commerce quality rules; the count stays fixed as volume grows.
        // C5 adds two fixed Cart/Wishlist checks; C6 adds eight fixed Checkout/Order/Reservation checks.
        self::assertLessThanOrEqual(123, $after);
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'REPORT-'.$suffix, 'name' => 'Reporting '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @return array{OrganizationUnit,JobPosition} */
    private function position(Company $company, string $unitName, string $budget): array
    {
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'type' => 'department', 'code' => 'REPORT-U-'.$company->id, 'name' => $unitName, 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'REPORT-P-'.$company->id, 'title' => 'Developer', 'career_level' => 3, 'approved_headcount' => 100, 'budgeted_cost' => $budget]);

        return [$unit, $position];
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
