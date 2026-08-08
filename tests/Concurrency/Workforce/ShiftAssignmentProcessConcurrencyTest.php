<?php

namespace Tests\Concurrency\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class ShiftAssignmentProcessConcurrencyTest extends TestCase
{
    public function test_two_php_processes_cannot_create_overlapping_shift_assignments(): void
    {
        self::assertSame('mysql', DB::connection()->getDriverName());
        $token = Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'RACE-'.$token, 'name' => 'Schema Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'RACE-'.$token, 'full_name' => 'Race Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $shift = (int) DB::table('work_shifts')->insertGetId(['company_id' => $company->id, 'code' => 'SHIFT-'.$token, 'name' => 'Race Shift', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:00', 'ends_at' => '17:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 0, 'grace_out_minutes' => 0, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'rules' => '{}', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        try {
            $payload = ['company_id' => $company->id, 'employee_id' => $employee->id, 'shift_id' => $shift, 'actor_id' => $user->id, 'effective_from' => '2026-08-01', 'effective_to' => '2026-08-31'];
            $race = (new MysqlProcessRaceRunner)->run('shift_assignment', $payload, $payload);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_shift_assignments')->where('company_id', $company->id)->where('employee_id', $employee->id)->count());
            self::assertSame(1, DB::table('activity_log')->where('log_name', 'workforce')->where('properties->company_id', $company->id)->where('properties->resource', 'assignment')->count());
        } finally {
            DB::table('employee_shift_assignments')->where('company_id', $company->id)->delete();
            DB::table('work_shifts')->where('id', $shift)->delete();
            DB::table('activity_log')->where('causer_id', $user->id)->delete();
            DB::table('company_user_access')->where('company_id', $company->id)->delete();
            $employee->forceDelete();
            $user->delete();
            $company->forceDelete();
        }
    }
}
