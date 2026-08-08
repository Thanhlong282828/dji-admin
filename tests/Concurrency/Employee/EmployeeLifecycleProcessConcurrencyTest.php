<?php

namespace Tests\Concurrency\Employee;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeLifecycleService;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class EmployeeLifecycleProcessConcurrencyTest extends TestCase
{
    public function test_create_onboarding_and_termination_races_have_one_winner_without_duplicate_history(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'LC-'.$token, 'name' => 'Lifecycle Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'LC-'.$token, 'full_name' => 'Lifecycle Race', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $actor = User::factory()->create();
        $base = ['company_id' => (int) $company->id, 'employee_id' => (int) $employee->id, 'actor_id' => (int) $actor->id, 'effective_at' => now()->toIso8601String()];
        try {
            $race = (new MysqlProcessRaceRunner)->run('employee_lifecycle', $base + ['action' => 'create', 'idempotency_key' => 'create-a'], $base + ['action' => 'create', 'idempotency_key' => 'create-b']);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->count());

            $race = (new MysqlProcessRaceRunner)->run('employee_lifecycle', $base + ['action' => 'onboarding', 'idempotency_key' => 'onboard-a'], $base + ['action' => 'onboarding', 'idempotency_key' => 'onboard-b']);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->where('to_state', 'onboarding')->count());

            app(EmployeeLifecycleService::class)->completeOnboarding(new LifecycleTransitionData((int) $company->id, (int) $employee->id, (int) $actor->id, 'complete', now()->toImmutable()));
            $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'RACE-UNIT', 'name' => 'Race Unit', 'type' => 'department', 'headcount_limit' => 5, 'effective_from' => today()]);
            $firstPosition = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'RACE-P1', 'title' => 'Initial', 'approved_headcount' => 2, 'budgeted_cost' => 0, 'is_critical' => false]);
            $nextPosition = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'RACE-P2', 'title' => 'Transferred', 'approved_headcount' => 2, 'budgeted_cost' => 0, 'is_critical' => false]);
            $assignment = DB::table('employee_assignments')->insertGetId(['employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $firstPosition->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            $transfer = $base + ['action' => 'transfer', 'current_assignment_id' => (int) $assignment, 'organization_unit_id' => (int) $unit->id, 'position_id' => (int) $nextPosition->id, 'effective_at' => '2026-07-01T00:00:00+07:00', 'reason' => 'Concurrent transfer'];
            $race = (new MysqlProcessRaceRunner)->run('employee_lifecycle', $transfer + ['idempotency_key' => 'transfer-a'], $transfer + ['idempotency_key' => 'transfer-b']);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->where('event_type', 'employee.transferred')->count());
            self::assertSame(1, DB::table('employee_positions_history')->where('employee_id', $employee->id)->count());

            $race = (new MysqlProcessRaceRunner)->run('employee_lifecycle', $base + ['action' => 'terminate', 'idempotency_key' => 'terminate-a', 'reason' => 'Race'], $base + ['action' => 'terminate', 'idempotency_key' => 'terminate-b', 'reason' => 'Race']);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->where('to_state', 'terminated')->count());
            self::assertSame(DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->count(), DB::table('employment_transitions')->where('employee_id', $employee->id)->count());
        } finally {
            DB::table('employee_lifecycle_outbox')->where('employee_id', $employee->id)->delete();
            DB::table('employment_transitions')->where('employee_id', $employee->id)->delete();
            DB::table('employee_lifecycle_events')->where('employee_id', $employee->id)->delete();
            DB::table('employee_manager_history')->where('employee_id', $employee->id)->delete();
            DB::table('employee_positions_history')->where('employee_id', $employee->id)->delete();
            DB::table('employee_assignments')->where('employee_id', $employee->id)->delete();
            DB::table('employee_histories')->where('employee_id', $employee->id)->delete();
            $employee->forceDelete();
            JobPosition::query()->where('company_id', $company->id)->delete();
            OrganizationUnit::query()->where('company_id', $company->id)->delete();
            $actor->delete();
            $company->forceDelete();
        }
    }
}
