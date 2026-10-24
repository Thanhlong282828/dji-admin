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
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RecruitmentCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_unauthorized_user_are_blocked(): void
    {
        $c = $this->company('A');
        $this->get('/admin/recruitment/dashboard?company_id='.$c->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/recruitment/dashboard?company_id='.$c->id)->assertForbidden();
    }

    public function test_requisition_validation_and_cross_company_position(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $user = $this->authorized($a, ['recruitment-requisition.view', 'recruitment-requisition.create']);
        $requester = $this->employee($a, 'E-A');
        $position = $this->position($b);
        $this->actingAs($user)->post('/admin/recruitment-requisitions', ['company_id' => $a->id, 'job_position_id' => $position->id, 'requested_by' => $requester->id, 'vacancies' => 0, 'budget' => -1])->assertSessionHasErrors(['vacancies', 'budget']);
        $this->actingAs($user)->post('/admin/recruitment-requisitions', ['company_id' => $a->id, 'job_position_id' => $position->id, 'requested_by' => $requester->id, 'vacancies' => 1, 'budget' => 1])->assertNotFound();
    }

    public function test_candidate_is_normalized_and_application_requires_open_requisition(): void
    {
        $c = $this->company('A');
        $user = $this->authorized($c, ['candidate.view', 'candidate.create', 'application.view', 'application.create']);
        $this->actingAs($user)->post('/admin/candidates', ['company_id' => $c->id, 'full_name' => 'Nguyễn An', 'email' => 'AN@EXAMPLE.TEST', 'phone' => '090 123 4567', 'consent' => 1])->assertRedirect();
        $candidate = Candidate::query()->firstOrFail();
        self::assertSame('an@example.test', $candidate->email);
        $position = $this->position($c);
        $employee = $this->employee($c, 'E-A');
        $req = RecruitmentRequisition::query()->create(['job_position_id' => $position->id, 'requested_by' => $employee->id, 'vacancies' => 1, 'budget' => 0, 'status' => 'draft']);
        $this->actingAs($user)->post('/admin/applications', ['company_id' => $c->id, 'recruitment_requisition_id' => $req->id, 'candidate_id' => $candidate->id])->assertSessionHasErrors('requisition');
    }

    public function test_super_admin_can_render_requisitions_from_another_company_without_tenant_assignment(): void
    {
        $company = $this->company('REMOTE');
        $position = $this->position($company);
        $requester = $this->employee($company, 'E-REMOTE');
        RecruitmentRequisition::query()->create([
            'job_position_id' => $position->id,
            'requested_by' => $requester->id,
            'vacancies' => 1,
            'budget' => 1000000,
            'status' => 'open',
        ]);
        DB::table('employment_contracts')->insert([
            'employee_id' => $requester->id,
            'contract_number' => 'REC-REMOTE-CONTRACT',
            'type' => 'indefinite',
            'starts_at' => today(),
            'base_salary' => 25000000,
            'currency' => 'VND',
            'terms' => '{}',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Super Admin'));

        $this->actingAs($user)
            ->get(route('admin.recruitment-requisitions.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Developer')
            ->assertSee('E-REMOTE')
            ->assertSee(route('admin.job-positions.show', [
                'job_position' => $position->id,
                'company_id' => $company->id,
            ]), false);

        $this->actingAs($user)
            ->get(route('admin.employees.show', ['employee' => $requester->id, 'company_id' => $company->id]))
            ->assertOk()
            ->assertSee('25.000.000 VND');
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'REC-'.$code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function authorized(Company $c, array $permissions): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $c->id, 'user_id' => $u->id]);

        return $u;
    }

    private function employee(Company $c, string $code): Employee
    {
        return Employee::query()->create(['company_id' => $c->id, 'employee_code' => $code, 'full_name' => $code, 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
    }

    private function position(Company $c): JobPosition
    {
        $unit = OrganizationUnit::query()->create(['company_id' => $c->id, 'code' => 'UNIT-'.$c->id, 'name' => 'Technology', 'type' => 'division', 'headcount_limit' => 10, 'effective_from' => today()]);

        return JobPosition::query()->create(['company_id' => $c->id, 'organization_unit_id' => $unit->id, 'code' => 'POS-'.$c->id, 'title' => 'Developer', 'approved_headcount' => 2, 'budgeted_cost' => 0, 'is_critical' => false]);
    }
}
