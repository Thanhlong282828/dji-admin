<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Tests\TestCase;

final class TalentPerformanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_goal_and_review_lifecycle_are_operable_and_history_is_append_only(): void
    {
        [$company,$employee,$creator] = $this->context('A', ['talent.performance.view', 'talent.performance.manage', 'talent.goal.create', 'talent.goal.update', 'talent.review.submit']);
        $reviewer = $this->user($company, ['talent.review.submit']);
        $approver = $this->user($company, ['talent.review.approve', 'talent.review.close']);
        $this->actingAs($creator)->post($this->action('cycle.create'), $this->payload($company, 'cycle', ['name' => 'FY 2027', 'period_start' => '2027-01-01', 'period_end' => '2027-12-31']))->assertRedirect()->assertSessionHasNoErrors();
        $cycle = (int) DB::table('performance_cycles')->value('id');
        $this->actingAs($creator)->post($this->action('goal.assign'), $this->payload($company, 'goal', ['employee_id' => $employee->id, 'cycle_id' => $cycle, 'title' => 'Deliver enterprise platform', 'weight' => 50]))->assertRedirect()->assertSessionHasNoErrors();
        $goal = (int) DB::table('employee_goals')->value('id');
        $this->actingAs($creator)->post($this->action('goal.progress', $goal), $this->payload($company, 'progress', ['progress' => 65]))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($creator)->post($this->action('review.start'), $this->payload($company, 'start', ['employee_id' => $employee->id, 'cycle_id' => $cycle, 'reviewer_id' => $reviewer->id]))->assertRedirect()->assertSessionHasNoErrors();
        $review = (int) DB::table('performance_reviews')->value('id');
        $this->actingAs($creator)->post($this->action('review.self', $review), $this->payload($company, 'self'))->assertRedirect();
        $this->actingAs($reviewer)->post($this->action('review.manager', $review), $this->payload($company, 'manager', ['rating' => '4.25']))->assertRedirect();
        $this->actingAs($approver)->post($this->action('review.approve', $review), $this->payload($company, 'approve'))->assertRedirect();
        $this->actingAs($approver)->post($this->action('cycle.close', $cycle), $this->payload($company, 'close'))->assertRedirect();
        $this->assertDatabaseHas('performance_cycles', ['id' => $cycle, 'talent_status' => 'closed']);
        $this->assertDatabaseHas('employee_goals', ['id' => $goal, 'progress' => '65.00']);
        $this->assertDatabaseCount('goal_progress_history', 1);
        $this->assertDatabaseCount('performance_review_history', 4);
        $this->assertDatabaseCount('talent_management_outbox', 7);
    }

    public function test_security_and_tenant_isolation(): void
    {
        [$a,$employee,$viewer] = $this->context('B', ['talent.performance.view']);
        [$b,$foreign] = $this->context('C', []);
        $this->get('/admin/talent/performance?company_id='.$a->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/talent/performance?company_id='.$a->id)->assertForbidden();
        $this->actingAs($viewer)->get('/admin/talent/performance/employees/'.$foreign->id.'?company_id='.$a->id)->assertNotFound();
        $this->actingAs($viewer)->post($this->action('cycle.create'), $this->payload($a, 'blocked'))->assertForbidden();
        self::assertNotSame($employee->id, $foreign->id);
        self::assertNotSame($a->id, $b->id);
    }

    public function test_creator_reviewer_and_reviewer_approver_separation(): void
    {
        [$company,$employee,$creator] = $this->context('D', ['talent.performance.manage', 'talent.review.submit', 'talent.review.approve']);
        $cycle = $this->cycle($company, $creator);
        $this->actingAs($creator)->post($this->action('review.start'), $this->payload($company, 'maker-review', ['employee_id' => $employee->id, 'cycle_id' => $cycle, 'reviewer_id' => $creator->id]))->assertSessionHasErrors('reviewer_id');
        $reviewer = $this->user($company, ['talent.performance.manage', 'talent.review.submit', 'talent.review.approve']);
        $this->actingAs($creator)->post($this->action('review.start'), $this->payload($company, 'valid-review', ['employee_id' => $employee->id, 'cycle_id' => $cycle, 'reviewer_id' => $reviewer->id]));
        $review = (int) DB::table('performance_reviews')->value('id');
        DB::table('performance_reviews')->where('id', $review)->update(['status' => 'manager_review']);
        $this->actingAs($reviewer)->post($this->action('review.approve', $review), $this->payload($company, 'self-approve'))->assertSessionHasErrors('actor');
    }

    private function action(string $action, ?int $subject = null): string
    {
        return '/admin/talent/performance/'.$action.($subject !== null ? '/'.$subject : '');
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function payload(Company $c, string $key, array $extra = []): array
    {
        return ['company_id' => $c->id, 'idempotency_key' => $key, ...$extra];
    }

    /** @param list<string> $permissions @return array{Company,Employee,User} */
    private function context(string $token, array $permissions): array
    {
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'TAL-'.$token, 'name' => 'Schema Talent '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $e = Employee::query()->create(['company_id' => $c->id, 'employee_code' => 'E-'.$token, 'full_name' => 'Talent Employee '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$c, $e, $this->user($c, $permissions)];
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

    private function cycle(Company $c, User $u): int
    {
        return (int) DB::table('performance_cycles')->insertGetId(['company_id' => $c->id, 'name' => 'Cycle', 'starts_at' => '2027-01-01', 'ends_at' => '2027-12-31', 'rating_scale' => '{"min":0,"max":5}', 'period_start' => '2027-01-01', 'period_end' => '2027-12-31', 'status' => 'draft', 'talent_status' => 'draft', 'created_by' => $u->id, 'checksum' => str_repeat('a', 64), 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
