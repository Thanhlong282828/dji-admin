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
use Tests\TestCase;

final class CareerSuccessionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_career_and_succession_are_operable_with_append_only_history(): void
    {
        [$company,$employee,$position,$creator] = $this->context(['career.view', 'career.manage', 'career.plan.create', 'succession.manage']);
        $approver = $this->user($company, ['succession.approve']);
        $this->actingAs($creator)->post($this->action('path.create'), $this->payload($company, 'path', ['name' => 'Engineering Leadership', 'position_ids' => [$position->id], 'required_levels' => [4]]))->assertRedirect()->assertSessionHasNoErrors();
        $path = (int) DB::table('career_paths')->value('id');
        $this->actingAs($creator)->post($this->action('plan.assign'), $this->payload($company, 'assign', ['employee_id' => $employee->id, 'career_path_id' => $path, 'target_step' => 1]))->assertRedirect();
        $plan = (int) DB::table('employee_career_plans')->value('id');
        $this->actingAs($creator)->post($this->action('plan.progress', $plan), $this->payload($company, 'progress', ['current_step' => 1]))->assertRedirect();
        $this->actingAs($creator)->post($this->action('succession.create'), $this->payload($company, 'succession', ['position_id' => $position->id]))->assertRedirect();
        $succession = (int) DB::table('succession_plans')->value('id');
        $this->actingAs($creator)->post($this->action('succession.nominate', $succession), $this->payload($company, 'nominate', ['employee_id' => $employee->id, 'readiness_level' => 'developing']))->assertRedirect();
        $candidate = (int) DB::table('succession_candidates')->value('id');
        $this->actingAs($creator)->post($this->action('succession.assess', $succession), $this->payload($company, 'assess', ['candidate_id' => $candidate, 'readiness_level' => 'ready_now', 'score' => '88.50']))->assertRedirect();
        $this->actingAs($approver)->post($this->action('succession.approve', $succession), $this->payload($company, 'approve'))->assertRedirect();
        $this->assertDatabaseHas('employee_career_plans', ['id' => $plan, 'status' => 'completed']);
        $this->assertDatabaseHas('succession_plans', ['id' => $succession, 'status' => 'approved', 'approved_by' => $approver->id]);
        $this->assertDatabaseCount('readiness_assessments', 1);
        self::assertSame(7, DB::table('career_history')->count());
        self::assertSame(7, DB::table('career_outbox')->count());
    }

    public function test_authorization_tenant_isolation_and_maker_checker(): void
    {
        [$a,$employee,$position,$owner] = $this->context(['career.view', 'succession.manage', 'succession.approve']);
        [$b,$foreign] = $this->context([]);
        $this->get('/admin/career?company_id='.$a->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/career?company_id='.$a->id)->assertForbidden();
        $this->actingAs($owner)->get('/admin/career/employees/'.$foreign->id.'?company_id='.$a->id)->assertNotFound();
        $this->actingAs($owner)->post($this->action('succession.create'), $this->payload($a, 'maker', ['position_id' => $position->id]));
        $plan = (int) DB::table('succession_plans')->where('company_id', $a->id)->value('id');
        DB::table('succession_plans')->where('id', $plan)->update(['status' => 'assessed']);
        $this->actingAs($owner)->post($this->action('succession.approve', $plan), $this->payload($a, 'self-approve'))->assertSessionHasErrors('actor');
        self::assertNotSame($a->id, $b->id);
        self::assertNotSame($employee->id, $foreign->id);
    }

    private function action(string $action, ?int $subject = null): string
    {
        return '/admin/career/'.$action.($subject ? '/'.$subject : '');
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function payload(Company $c, string $key, array $extra = []): array
    {
        return ['company_id' => $c->id, 'idempotency_key' => $key, ...$extra];
    }

    /** @param list<string> $permissions @return array{Company,Employee,JobPosition,User} */
    private function context(array $permissions): array
    {
        $token = Str::upper(Str::random(6));
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'CAR-'.$token, 'name' => 'Career '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $e = Employee::query()->create(['company_id' => $c->id, 'employee_code' => 'C-'.$token, 'full_name' => 'Career Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $u = OrganizationUnit::query()->create(['company_id' => $c->id, 'code' => 'U-'.$token, 'name' => 'Career Unit', 'type' => 'department', 'headcount_limit' => 5, 'effective_from' => today()]);
        $p = JobPosition::query()->create(['company_id' => $c->id, 'organization_unit_id' => $u->id, 'code' => 'P-'.$token, 'title' => 'Engineering Manager', 'approved_headcount' => 1, 'budgeted_cost' => 0, 'is_critical' => true]);

        return [$c, $e, $p, $this->user($c, $permissions)];
    }

    /** @param list<string> $permissions */
    private function user(Company $c, array $permissions): User
    {
        $u = User::factory()->create();
        if ($permissions !== []) {
            $u->givePermissionTo($permissions);
        }DB::table('company_user_access')->insert(['company_id' => $c->id, 'user_id' => $u->id]);

        return $u;
    }
}
