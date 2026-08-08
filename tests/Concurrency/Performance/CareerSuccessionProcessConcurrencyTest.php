<?php

namespace Tests\Concurrency\Performance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class CareerSuccessionProcessConcurrencyTest extends TestCase
{
    public function test_create_nominate_and_approve_races_do_not_duplicate_history(): void
    {
        $token = Str::upper(Str::random(7));
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'CR-'.$token, 'name' => 'Career Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $e = Employee::query()->create(['company_id' => $c->id, 'employee_code' => 'CR-'.$token, 'full_name' => 'Career Race', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $u = OrganizationUnit::query()->create(['company_id' => $c->id, 'code' => 'CRU-'.$token, 'name' => 'Race', 'type' => 'department', 'headcount_limit' => 2, 'effective_from' => today()]);
        $p = JobPosition::query()->create(['company_id' => $c->id, 'organization_unit_id' => $u->id, 'code' => 'CRP-'.$token, 'title' => 'Critical', 'approved_headcount' => 1, 'budgeted_cost' => 0, 'is_critical' => true]);
        $owner = User::factory()->create();
        $approver = User::factory()->create();
        $runner = new MysqlProcessRaceRunner;
        try {
            $path = ['company_id' => (int) $c->id, 'actor_id' => (int) $owner->id, 'idempotency_key' => 'path-'.$token, 'name' => 'Leadership '.$token, 'position_id' => (int) $p->id];
            $race = $runner->run('career_path_create', $path, $path);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('career_paths')->where('company_id', $c->id)->count());
            $pathId = (int) DB::table('career_paths')->where('company_id', $c->id)->value('id');
            $assign = ['company_id' => (int) $c->id, 'actor_id' => (int) $owner->id, 'idempotency_key' => 'assign-'.$token, 'career_path_id' => $pathId, 'employee_id' => (int) $e->id, 'target_step' => 1];
            $race = $runner->run('career_plan_assign', $assign, $assign);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_career_plans')->where('company_id', $c->id)->count());
            $create = ['company_id' => (int) $c->id, 'actor_id' => (int) $owner->id, 'idempotency_key' => 'succ-'.$token, 'position_id' => (int) $p->id];
            $race = $runner->run('succession_create', $create, $create);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('succession_plans')->where('company_id', $c->id)->count());
            $sid = (int) DB::table('succession_plans')->where('company_id', $c->id)->value('id');
            $nom = ['company_id' => (int) $c->id, 'actor_id' => (int) $owner->id, 'idempotency_key' => 'nom-'.$token, 'succession_plan_id' => $sid, 'employee_id' => (int) $e->id, 'readiness_level' => 'developing'];
            $race = $runner->run('succession_nominate', $nom, $nom);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('succession_candidates')->where('succession_plan_id', $sid)->count());
            DB::table('succession_plans')->where('id', $sid)->update(['status' => 'assessed']);
            $approve = ['company_id' => (int) $c->id, 'actor_id' => (int) $approver->id, 'idempotency_key' => 'approve-'.$token, 'succession_plan_id' => $sid];
            $race = $runner->run('succession_approve', $approve, $approve);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('career_history')->where('company_id', $c->id)->where('event_type', 'SuccessorApproved')->count());
        } finally {
            DB::table('career_outbox')->where('company_id', $c->id)->delete();
            DB::table('career_history')->where('company_id', $c->id)->delete();
            DB::table('succession_candidates')->whereIn('succession_plan_id', DB::table('succession_plans')->where('company_id', $c->id)->select('id'))->delete();
            DB::table('succession_plans')->where('company_id', $c->id)->delete();
            DB::table('employee_career_plans')->where('company_id', $c->id)->delete();
            DB::table('career_path_steps')->whereIn('career_path_id', DB::table('career_paths')->where('company_id', $c->id)->select('id'))->delete();
            DB::table('career_paths')->where('company_id', $c->id)->delete();
            $e->forceDelete();
            $p->delete();
            $u->delete();
            $owner->delete();
            $approver->delete();
            $c->forceDelete();
        }
    }
}
