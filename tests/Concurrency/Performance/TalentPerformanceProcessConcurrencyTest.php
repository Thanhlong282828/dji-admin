<?php

namespace Tests\Concurrency\Performance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class TalentPerformanceProcessConcurrencyTest extends TestCase
{
    public function test_cycle_goal_approve_and_close_are_process_safe_and_idempotent(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'TP-'.$token, 'name' => 'Talent Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'TP-'.$token, 'full_name' => 'Talent Race', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $runner = new MysqlProcessRaceRunner;

        try {
            $create = ['company_id' => (int) $company->id, 'actor_id' => (int) $creator->id, 'idempotency_key' => 'cycle-'.$token, 'name' => 'Annual '.$token, 'period_start' => '2027-01-01', 'period_end' => '2027-12-31'];
            $race = $runner->run('talent_cycle_create', $create, $create);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('performance_cycles')->where('idempotency_key', 'cycle-'.$token)->count());
            $cycleId = (int) DB::table('performance_cycles')->where('idempotency_key', 'cycle-'.$token)->value('id');

            $goal = ['company_id' => (int) $company->id, 'actor_id' => (int) $creator->id, 'idempotency_key' => 'goal-'.$token, 'cycle_id' => $cycleId, 'employee_id' => (int) $employee->id, 'title' => 'Scale platform', 'weight' => '50'];
            $race = $runner->run('talent_goal_assign', $goal, $goal);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_goals')->where('idempotency_key', 'goal-'.$token)->count());

            $reviewId = (int) DB::table('performance_reviews')->insertGetId(['company_id' => $company->id, 'employee_id' => $employee->id, 'cycle_id' => $cycleId, 'reviewer_id' => $reviewer->id, 'status' => 'manager_review', 'overall_rating' => '4.00', 'checksum' => str_repeat('a', 64), 'idempotency_key' => 'review-'.$token, 'created_at' => now(), 'updated_at' => now()]);
            $approve = ['company_id' => (int) $company->id, 'actor_id' => (int) $approver->id, 'idempotency_key' => 'approve-'.$token, 'review_id' => $reviewId];
            $race = $runner->run('talent_review_approve', $approve, $approve);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('performance_review_history')->where('review_id', $reviewId)->where('to_status', 'approved')->count());

            $close = ['company_id' => (int) $company->id, 'actor_id' => (int) $approver->id, 'idempotency_key' => 'close-'.$token, 'cycle_id' => $cycleId];
            $race = $runner->run('talent_cycle_close', $close, $close);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('closed', DB::table('performance_cycles')->where('id', $cycleId)->value('talent_status'));
            self::assertSame(1, DB::table('talent_management_outbox')->where('aggregate_type', 'performance_cycle')->where('aggregate_id', $cycleId)->where('event_type', 'PerformanceClosed')->count());
        } finally {
            DB::table('talent_management_outbox')->where('company_id', $company->id)->delete();
            DB::table('performance_review_history')->where('company_id', $company->id)->delete();
            DB::table('performance_reviews')->where('company_id', $company->id)->delete();
            DB::table('goal_progress_history')->whereIn('goal_id', DB::table('employee_goals')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('employee_goals')->where('company_id', $company->id)->delete();
            DB::table('performance_cycles')->where('company_id', $company->id)->delete();
            $employee->forceDelete();
            $creator->delete();
            $reviewer->delete();
            $approver->delete();
            $company->forceDelete();
        }
    }
}
