<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Organization\Domain\Models\CostCenter;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Tests\TestCase;

final class OrganizationExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_user_without_permission_cannot_export(): void
    {
        $company = $this->company('SCHEMA-A');
        $url = route('admin.cost-centers.export', ['company_id' => $company->id]);

        $this->get($url)->assertRedirect('/login');
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_cost_center_export_is_tenant_scoped_filtered_utf8_and_formula_safe(): void
    {
        $companyA = $this->company('SCHEMA-A');
        $companyB = $this->company('SCHEMA-B');
        CostCenter::query()->create(['company_id' => $companyA->id, 'code' => 'TECH', 'name' => '=Công nghệ', 'annual_budget' => '6200000000.00', 'currency' => 'VND']);
        CostCenter::query()->create(['company_id' => $companyA->id, 'code' => 'HR', 'name' => 'Nhân sự', 'annual_budget' => '1000.00', 'currency' => 'VND']);
        CostCenter::query()->create(['company_id' => $companyB->id, 'code' => 'FOREIGN', 'name' => 'Tenant B', 'annual_budget' => '999.00', 'currency' => 'USD']);
        $user = $this->authorized($companyA, ['cost-center.export']);

        $response = $this->actingAs($user)->get(route('admin.cost-centers.export', [
            'company_id' => $companyA->id,
            'search' => 'TECH',
        ]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        self::assertStringContainsString('Code,Name,Company', $content);
        self::assertStringContainsString('TECH', $content);
        self::assertStringContainsString("'=Công nghệ", $content);
        self::assertStringContainsString('6200000000.00', $content);
        self::assertStringNotContainsString('FOREIGN', $content);
        self::assertStringNotContainsString(',HR,', $content);
    }

    public function test_cross_company_export_is_forbidden(): void
    {
        $companyA = $this->company('SCHEMA-A');
        $companyB = $this->company('SCHEMA-B');
        $user = $this->authorized($companyA, ['organization-unit.export']);

        $this->actingAs($user)->get(route('admin.organization-units.export', ['company_id' => $companyB->id]))->assertForbidden();
    }

    public function test_all_organization_exports_have_real_csv_content_and_tree_hierarchy(): void
    {
        $company = $this->company('SCHEMA-A');
        $costCenter = CostCenter::query()->create(['company_id' => $company->id, 'code' => 'TECH', 'name' => 'Công nghệ', 'annual_budget' => '6200000000.00', 'currency' => 'VND']);
        $root = $this->unit($company, 'HQ', 'Trụ sở chính', 'company', null, $costCenter->id);
        $child = $this->unit($company, 'BACKEND', 'Phòng Backend', 'department', $root->id, $costCenter->id);
        JobPosition::query()->create([
            'company_id' => $company->id,
            'organization_unit_id' => $child->id,
            'code' => 'BE',
            'title' => 'Backend Developer',
            'approved_headcount' => 73,
            'budgeted_cost' => '2740000000.00',
            'is_critical' => true,
        ]);
        $user = $this->authorized($company, ['organization-unit.export', 'job-position.export']);

        $unit = $this->actingAs($user)->get(route('admin.organization-units.export', ['company_id' => $company->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('Code,Name,Type', $unit);
        self::assertStringContainsString('Phòng Backend', $unit);

        $tree = $this->actingAs($user)->get(route('admin.organization-units.export-tree', ['company_id' => $company->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('Depth,Path,Code', $tree);
        self::assertStringContainsString('Trụ sở chính / Phòng Backend', $tree);

        $positions = $this->actingAs($user)->get(route('admin.job-positions.export', ['company_id' => $company->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('Backend Developer', $positions);
        self::assertStringContainsString('2740000000.00', $positions);

        $headcount = $this->actingAs($user)->get(route('admin.organization.export-headcount', ['company_id' => $company->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('"Approved Headcount","People Count","Allocated FTE",Vacancy', $headcount);
        self::assertStringContainsString(',73,0,0,73,', $headcount);
        self::assertStringContainsString('6200000000.00', $headcount);
    }

    private function company(string $code): Company
    {
        return Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => $code,
            'name' => $code,
            'country_code' => 'VN',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'currency' => 'VND',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    /** @param list<string> $permissions */
    private function authorized(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    private function unit(Company $company, string $code, string $name, string $type, ?int $parentId, int $costCenterId): OrganizationUnit
    {
        return OrganizationUnit::query()->create([
            'company_id' => $company->id,
            'parent_id' => $parentId,
            'cost_center_id' => $costCenterId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'headcount_limit' => 100,
            'effective_from' => today(),
        ]);
    }
}
