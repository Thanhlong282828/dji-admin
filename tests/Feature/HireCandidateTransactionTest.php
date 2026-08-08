<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Recruitment\Domain\Events\CandidateHireCompleted;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Tests\TestCase;

final class HireCandidateTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_user_without_permissions_cannot_hire(): void
    {
        [$company, $offer] = $this->acceptedOffer('AUTH');
        $url = "/admin/candidate-offers/{$offer->id}/hire?company_id={$company->id}";

        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->user($company, []))->get($url)->assertForbidden();
    }

    public function test_hire_is_atomic_links_all_aggregates_and_is_idempotent(): void
    {
        Event::fake([CandidateHireCompleted::class]);
        [$company, $offer] = $this->acceptedOffer('OK');
        $user = $this->user($company, ['candidate-offer.hire', 'onboarding.create-from-hire']);
        $payload = $this->payload($offer);

        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertRedirect();
        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertRedirect();

        self::assertSame(1, Employee::query()->where('source_offer_id', $offer->id)->count());
        $employee = Employee::query()->where('source_offer_id', $offer->id)->firstOrFail();
        $this->assertDatabaseHas('employee_assignments', ['employee_id' => $employee->id, 'assignment_type' => 'primary', 'allocation_percent' => '100.00']);
        $this->assertDatabaseHas('employment_contracts', ['employee_id' => $employee->id, 'source_offer_id' => $offer->id, 'status' => 'draft', 'base_salary' => '25000000.00']);
        $this->assertDatabaseHas('applications', ['id' => $offer->application_id, 'stage' => 'hired', 'hired_employee_id' => $employee->id]);
        $this->assertDatabaseHas('candidates', ['hired_employee_id' => $employee->id, 'status' => 'hired']);
        $this->assertDatabaseHas('candidate_offers', ['id' => $offer->id, 'reservation_status' => 'consumed', 'hired_employee_id' => $employee->id]);
        $this->assertDatabaseHas('onboarding_cases', ['employee_id' => $employee->id, 'offer_id' => $offer->id, 'status' => 'preboarding']);
        self::assertSame(10, DB::table('onboarding_checklist_items')->count());
        self::assertSame(8, DB::table('onboarding_provisioning_items')->count());
        self::assertSame(1, DB::table('hire_transaction_markers')->count());
        self::assertSame(1, RecruitmentRequisition::query()->firstOrFail()->filled_hires);
        Event::assertDispatched(CandidateHireCompleted::class, fn (CandidateHireCompleted $event): bool => $event->employeeId === $employee->id);
    }

    public function test_stale_version_rolls_back_every_downstream_record(): void
    {
        [$company, $offer] = $this->acceptedOffer('STALE');
        $user = $this->user($company, ['candidate-offer.hire', 'onboarding.create-from-hire']);
        $payload = $this->payload($offer);
        $payload['expected_offer_version'] = 999;

        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertSessionHasErrors('version');

        self::assertSame(0, Employee::query()->where('source', 'recruitment')->count());
        self::assertSame(0, DB::table('hire_transaction_markers')->count());
        self::assertSame(0, DB::table('onboarding_cases')->count());
        self::assertSame('reserved', $offer->refresh()->reservation_status);
    }

    public function test_cross_company_hire_is_forbidden_without_writing_data(): void
    {
        [$companyA, $offer] = $this->acceptedOffer('TENANT-A');
        [$companyB] = $this->acceptedOffer('TENANT-B');
        $user = $this->user($companyB, ['candidate-offer.hire', 'onboarding.create-from-hire']);
        $payload = $this->payload($offer);
        $payload['company_id'] = $companyA->id;

        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertForbidden();
        self::assertSame(0, DB::table('hire_transaction_markers')->count());
    }

    public function test_non_accepted_offer_cannot_hire_and_preview_hides_salary(): void
    {
        [$company, $offer] = $this->acceptedOffer('STATE');
        $user = $this->user($company, ['candidate-offer.hire']);
        $this->actingAs($user)->get("/admin/candidate-offers/{$offer->id}/hire?company_id={$company->id}")
            ->assertOk()->assertDontSee('25000000');

        foreach (['draft', 'pending_approval', 'approved', 'sent', 'declined', 'withdrawn', 'expired'] as $status) {
            $offer->update(['status' => $status, 'reservation_status' => 'released']);
            $payload = $this->payload($offer->refresh());
            $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertSessionHasErrors('offer');
        }
        self::assertSame(0, DB::table('hire_transaction_markers')->count());
    }

    public function test_onboarding_unique_conflict_rolls_back_employee_assignment_contract_and_reservation(): void
    {
        [$company, $offer] = $this->acceptedOffer('ROLLBACK');
        $user = $this->user($company, ['candidate-offer.hire']);
        $existingEmployee = Employee::query()->where('company_id', $company->id)->firstOrFail();
        DB::table('onboarding_cases')->insert([
            'company_id' => $company->id,
            'employee_id' => $existingEmployee->id,
            'application_id' => $offer->application_id,
            'start_date' => today(),
            'checklist' => '[]',
            'provisioning' => '[]',
            'status' => 'preboarding',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employeeCount = Employee::query()->count();

        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $this->payload($offer))->assertSessionHasErrors('hire');

        self::assertSame($employeeCount, Employee::query()->count());
        self::assertSame(0, DB::table('employee_assignments')->count());
        self::assertSame(0, DB::table('employment_contracts')->count());
        self::assertSame(0, DB::table('hire_transaction_markers')->count());
        self::assertSame('reserved', $offer->refresh()->reservation_status);
    }

    public function test_client_salary_is_ignored_and_duplicate_employee_number_is_blocked(): void
    {
        [$company, $offer] = $this->acceptedOffer('MAP');
        $user = $this->user($company, ['candidate-offer.hire']);
        Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'TAKEN-01', 'full_name' => 'Existing Employee', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $payload = $this->payload($offer) + ['employee_number' => 'TAKEN-01', 'base_salary' => '1', 'currency' => 'USD'];
        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertSessionHasErrors('employee_number');

        $payload['employee_number'] = 'NEW-01';
        $payload['idempotency_key'] = (string) Str::uuid();
        $this->actingAs($user)->post("/admin/candidate-offers/{$offer->id}/hire", $payload)->assertRedirect();
        $this->assertDatabaseHas('employment_contracts', ['source_offer_id' => $offer->id, 'base_salary' => '25000000.00', 'currency' => 'VND']);
    }

    /** @return array{Company, CandidateOffer} */
    private function acceptedOffer(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'UNIT-'.$code, 'name' => 'Khối Công nghệ', 'type' => 'division', 'headcount_limit' => 10, 'effective_from' => today()]);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'POS-'.$code, 'title' => 'Senior Laravel Engineer', 'approved_headcount' => 3, 'budgeted_cost' => 0, 'is_critical' => true]);
        $requester = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'REQ-'.$code, 'full_name' => 'Recruitment Owner', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $requisition = RecruitmentRequisition::query()->create(['job_position_id' => $position->id, 'requested_by' => $requester->id, 'vacancies' => 1, 'filled_hires' => 0, 'budget' => 0, 'status' => 'open', 'version' => 1]);
        $candidate = Candidate::query()->create(['company_id' => $company->id, 'full_name' => 'Nguyễn Minh Schema', 'email' => strtolower($code).'@example.test', 'phone' => '0901234567', 'profile_fingerprint' => hash('sha256', $code), 'profile' => ['nationality' => 'VN'], 'consent' => true, 'status' => 'active']);
        $application = Application::query()->create(['recruitment_requisition_id' => $requisition->id, 'candidate_id' => $candidate->id, 'stage' => 'offer', 'stage_changed_at' => now(), 'version' => 2]);
        $offer = CandidateOffer::query()->create(['company_id' => $company->id, 'application_id' => $application->id, 'offer_number' => 'SCH-OFF-'.$code, 'job_position_id' => $position->id, 'organization_unit_id' => $unit->id, 'employment_type' => 'full_time', 'base_salary' => '25000000.00', 'currency' => 'VND', 'start_date' => today()->addMonth(), 'status' => 'accepted', 'reservation_status' => 'reserved', 'reserved_at' => now(), 'accepted_at' => now(), 'version' => 5]);

        return [$company, $offer];
    }

    /** @return array<string, int|string> */
    private function payload(CandidateOffer $offer): array
    {
        $application = Application::query()->findOrFail($offer->application_id);

        return ['company_id' => $offer->company_id, 'expected_offer_version' => $offer->version, 'expected_application_version' => $application->version, 'start_date' => $offer->start_date->toDateString(), 'organization_unit_id' => $offer->organization_unit_id, 'job_position_id' => $offer->job_position_id, 'employment_type' => 'full_time', 'contract_type' => 'indefinite', 'reason' => 'Offer accepted and approved for onboarding.', 'idempotency_key' => (string) Str::uuid()];
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
