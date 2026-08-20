<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Domain\Enums\AssignmentType;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Tests\TestCase;

final class EmployeeCareerLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_and_demotion_require_objective_level_and_write_distinct_history(): void
    {
        [$company, $employee, $unit, $lower, $middle, $higher, $current] = $this->fixture();
        $actor = User::factory()->create();
        $service = app(EmployeeAssignmentService::class);
        $promoted = $service->transfer($company->id, $employee->id, $current->id, $this->assignment($unit->id, $higher->id, '2026-09-01'), 'Promotion test', $actor->id, true);
        $this->assertDatabaseHas('employee_histories', ['employee_id' => $employee->id, 'event_type' => 'promotion']);
        $demoted = $service->transfer($company->id, $employee->id, $promoted->id, $this->assignment($unit->id, $middle->id, '2026-10-01'), 'Demotion test', $actor->id, false, true);
        $this->assertDatabaseHas('employee_histories', ['employee_id' => $employee->id, 'event_type' => 'demotion']);
        self::assertSame($middle->id, $demoted->job_position_id);

        $this->expectException(ValidationException::class);
        $service->transfer($company->id, $employee->id, $demoted->id, $this->assignment($unit->id, $lower->id, '2026-11-01'), 'Invalid promotion', $actor->id, true);
    }

    /** @return array{Company,Employee,OrganizationUnit,JobPosition,JobPosition,JobPosition,EmployeeAssignment} */
    private function fixture(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'CAREER', 'name' => 'Career Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'ENG', 'name' => 'Engineering', 'type' => 'department', 'effective_from' => '2026-01-01']);
        $position = fn (string $title, int $level): JobPosition => JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => strtoupper($title), 'title' => $title, 'career_level' => $level, 'approved_headcount' => 5, 'budgeted_cost' => 0, 'is_critical' => false]);
        $lower = $position('Junior', 2);
        $middle = $position('Middle', 3);
        $higher = $position('Senior', 4);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'CAREER-1', 'full_name' => 'Career Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $current = EmployeeAssignment::query()->create(['employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $middle->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01']);

        return [$company, $employee, $unit, $lower, $middle, $higher, $current];
    }

    private function assignment(int $unit, int $position, string $date): EmployeeAssignmentData
    {
        return EmployeeAssignmentData::fromArray(['organization_unit_id' => $unit, 'job_position_id' => $position, 'manager_id' => null, 'assignment_type' => AssignmentType::Primary->value, 'allocation_percent' => '100.00', 'work_location' => null, 'effective_from' => $date, 'effective_to' => null]);
    }
}
