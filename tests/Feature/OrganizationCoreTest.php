<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;
use Tests\TestCase;

final class OrganizationCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_user_without_permission_are_blocked(): void
    {
        $company = $this->company('SCHEMA-A');
        $this->get('/admin/cost-centers?company_id='.$company->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->get('/admin/cost-centers?company_id='.$company->id)
            ->assertForbidden();
    }

    public function test_tenant_access_and_cost_center_rules(): void
    {
        $companyA = $this->company('SCHEMA-A');
        $companyB = $this->company('SCHEMA-B');
        $user = $this->authorized($companyA, ['cost-center.view', 'cost-center.create']);

        $this->actingAs($user)->post('/admin/cost-centers', [
            'company_id' => $companyA->id, 'code' => 'TECH', 'name' => 'Công nghệ',
            'annual_budget' => 1000000, 'currency' => 'VND',
        ])->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['company_id' => $companyA->id, 'code' => 'TECH']);

        $this->actingAs($user)->post('/admin/cost-centers', [
            'company_id' => $companyA->id, 'code' => 'TECH', 'name' => 'Trùng',
            'annual_budget' => 1, 'currency' => 'VND',
        ])->assertSessionHasErrors('code');

        $this->actingAs($user)->post('/admin/cost-centers', [
            'company_id' => $companyA->id, 'code' => 'NEG', 'name' => 'Âm',
            'annual_budget' => -1, 'currency' => 'VND',
        ])->assertSessionHasErrors('annual_budget');

        $this->actingAs($user)->get('/admin/cost-centers?company_id='.$companyB->id)->assertForbidden();
    }

    public function test_tree_prevents_cycle_and_cross_company_position(): void
    {
        $companyA = $this->company('SCHEMA-A');
        $companyB = $this->company('SCHEMA-B');
        $user = $this->authorized($companyA, ['organization-unit.view', 'organization-unit.move', 'job-position.view', 'job-position.create']);
        $root = $this->unit($companyA, 'HQ', 'company');
        $child = $this->unit($companyA, 'TECH', 'division', $root->id);
        $foreign = $this->unit($companyB, 'FOREIGN', 'department');

        $this->actingAs($user)->patch("/admin/organization-units/{$root->id}/move", [
            'company_id' => $companyA->id, 'parent_id' => $child->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_units', ['id' => $root->id, 'parent_id' => null]);

        try {
            app(OrganizationWriteRepositoryInterface::class)->createPosition($companyA->id, [
                'organization_unit_id' => $foreign->id, 'code' => 'BE',
                'title' => 'Backend Developer', 'approved_headcount' => 1,
                'budgeted_cost' => 1000, 'is_critical' => false,
            ]);
            self::fail('Cross-company organization unit must be rejected.');
        } catch (ModelNotFoundException) {
            self::assertDatabaseMissing('job_positions', ['company_id' => $companyA->id, 'code' => 'BE']);
        }
    }

    public function test_headcount_distinguishes_people_and_allocated_fte(): void
    {
        $company = $this->company('SCHEMA-A');
        $unit = $this->unit($company, 'TECH', 'division');
        $position = JobPosition::query()->create([
            'company_id' => $company->id, 'organization_unit_id' => $unit->id,
            'code' => 'BE', 'title' => 'Backend Developer', 'approved_headcount' => 3,
            'budgeted_cost' => 1000, 'is_critical' => true,
        ]);
        $employee = DB::table('employees')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company->id,
            'employee_code' => 'E001', 'full_name' => 'Nguyễn Minh An',
            'join_date' => today(), 'employment_status' => 'active',
            'employment_type' => 'full_time', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employee_assignments')->insert([
            'employee_id' => $employee, 'organization_unit_id' => $unit->id,
            'job_position_id' => $position->id, 'assignment_type' => 'primary',
            'allocation_percent' => 50, 'effective_from' => today()->subDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $stats = app(OrganizationReadRepositoryInterface::class)->dashboard($company->id, []);

        self::assertSame(1, $stats['people_count']);
        self::assertSame(0.5, $stats['allocated_fte']);
        self::assertSame(2.5, $stats['vacancy']);
    }

    private function company(string $code): Company
    {
        return Company::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code,
            'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active',
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

    private function unit(Company $company, string $code, string $type, ?int $parentId = null): OrganizationUnit
    {
        return OrganizationUnit::query()->create([
            'company_id' => $company->id, 'parent_id' => $parentId,
            'code' => $code, 'name' => $code, 'type' => $type,
            'headcount_limit' => 10, 'effective_from' => today(),
        ]);
    }
}
