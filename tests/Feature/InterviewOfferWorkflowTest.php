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
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Tests\TestCase;

final class InterviewOfferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_interview_schedule_is_tenant_scoped_and_assigns_panel_atomically(): void
    {
        [$company, $application] = $this->application('INT');
        $scheduler = $this->user($company, ['interview.schedule', 'interview.view', 'interview.reschedule']);
        $interviewer = $this->user($company, ['interview.evaluate']);

        $response = $this->actingAs($scheduler)->post('/admin/interviews', [
            'company_id' => $company->id,
            'application_id' => $application->id,
            'title' => 'Backend technical interview',
            'interview_type' => 'online',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'meeting_url' => 'https://meet.example.test/schema-interview',
            'round_number' => 1,
            'interviewer_user_ids' => [$interviewer->id],
            'expected_application_version' => 1,
        ]);

        $response->assertRedirect();
        $interview = ApplicationInterview::query()->firstOrFail();
        self::assertSame('scheduled', $interview->status->value);
        $this->assertDatabaseHas('application_interviewers', ['application_interview_id' => $interview->id, 'user_id' => $interviewer->id]);

        $this->actingAs($scheduler)->patch("/admin/interviews/{$interview->id}/confirmed", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $this->actingAs($interviewer)->put("/admin/interviews/{$interview->id}/evaluation/submit", [
            'company_id' => $company->id,
            'recommendation' => 'hire',
            'submit' => 1,
            'items' => [
                ['criterion_code' => 'TECHNICAL', 'criterion_label' => 'Chuyên môn', 'score' => 4, 'maximum_score' => 5, 'weight' => 70],
                ['criterion_code' => 'COMMUNICATION', 'criterion_label' => 'Giao tiếp', 'score' => 3, 'maximum_score' => 5, 'weight' => 30],
            ],
        ])->assertRedirect();
        $this->assertDatabaseHas('interview_evaluations', [
            'application_interview_id' => $interview->id,
            'interviewer_user_id' => $interviewer->id,
            'overall_score' => '74.00',
            'recommendation' => 'hire',
        ]);
        $csv = $this->actingAs($scheduler)->get('/admin/interviews-export.csv?company_id='.$company->id);
        $csv->assertOk();
        self::assertStringContainsString('Nguyễn Schema', $csv->streamedContent());
    }

    public function test_offer_requires_approval_and_accept_reserves_once_without_hiring(): void
    {
        [$company, $application] = $this->application('OFF');
        $creator = $this->user($company, ['candidate-offer.create', 'candidate-offer.view', 'candidate-offer.submit', 'candidate-offer.send', 'candidate-offer.accept']);
        $approver = $this->user($company, ['candidate-offer.view', 'candidate-offer.approve']);

        $this->actingAs($creator)->post('/admin/candidate-offers', [
            'company_id' => $company->id,
            'application_id' => $application->id,
            'employment_type' => 'full_time',
            'base_salary' => '25000000',
            'currency' => 'VND',
            'start_date' => today()->addMonth()->toDateString(),
            'expires_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $offer = CandidateOffer::query()->firstOrFail();

        $this->actingAs($creator)->patch("/admin/candidate-offers/{$offer->id}/pending_approval", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $this->actingAs($approver)->patch("/admin/candidate-offers/{$offer->id}/approved", ['company_id' => $company->id, 'version' => 2])->assertRedirect();
        $this->actingAs($creator)->patch("/admin/candidate-offers/{$offer->id}/sent", ['company_id' => $company->id, 'version' => 3])->assertRedirect();
        $this->actingAs($creator)->patch("/admin/candidate-offers/{$offer->id}/accepted", ['company_id' => $company->id, 'version' => 4])->assertRedirect();
        $this->actingAs($creator)->patch("/admin/candidate-offers/{$offer->id}/accepted", ['company_id' => $company->id, 'version' => 5])->assertRedirect();

        $offer->refresh();
        $application->refresh();
        self::assertSame('accepted', $offer->status->value);
        self::assertSame('reserved', $offer->reservation_status);
        self::assertSame('offer', $application->stage->value);
        self::assertNull($application->hired_employee_id);
        self::assertSame(1, CandidateOffer::query()->where('reservation_status', 'reserved')->count());
        $csv = $this->actingAs($creator)->get('/admin/candidate-offers-export.csv?company_id='.$company->id);
        $csv->assertOk();
        self::assertStringContainsString($offer->offer_number, $csv->streamedContent());
        self::assertStringNotContainsString('Base Salary', $csv->streamedContent());
    }

    /** @return array{Company, Application} */
    private function application(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'UNIT-'.$code, 'name' => 'Technology', 'type' => 'division', 'headcount_limit' => 10, 'effective_from' => today()]);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'POS-'.$code, 'title' => 'Developer', 'approved_headcount' => 3, 'budgeted_cost' => 0, 'is_critical' => false]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EMP-'.$code, 'full_name' => 'Recruiter', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $requisition = RecruitmentRequisition::query()->create(['job_position_id' => $position->id, 'requested_by' => $employee->id, 'vacancies' => 2, 'budget' => 0, 'status' => 'open']);
        $candidate = Candidate::query()->create(['company_id' => $company->id, 'full_name' => 'Nguyễn Schema', 'email' => strtolower($code).'@example.test', 'profile_fingerprint' => hash('sha256', $code), 'consent' => true, 'status' => 'active']);
        $application = Application::query()->create(['recruitment_requisition_id' => $requisition->id, 'candidate_id' => $candidate->id, 'stage' => 'interview', 'stage_changed_at' => now(), 'version' => 1]);

        return [$company, $application];
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
