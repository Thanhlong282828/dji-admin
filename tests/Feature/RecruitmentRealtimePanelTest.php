<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Application\Listeners\SendRecruitmentWorkflowNotifications;
use Modules\Recruitment\Application\Notifications\RecruitmentNotificationRecipientResolver;
use Modules\Recruitment\Application\Notifications\RecruitmentWorkflowNotification;
use Modules\Recruitment\Application\Queries\GetInterviewPanelSummary\GetInterviewPanelSummaryHandler;
use Modules\Recruitment\Application\Queries\GetInterviewPanelSummary\GetInterviewPanelSummaryQuery;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\InterviewEvaluation;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Tests\TestCase;

final class RecruitmentRealtimePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_panel_summary_excludes_draft_and_private_notes_and_renders_dom_contract(): void
    {
        [$company, $application] = $this->application('PANEL');
        $viewer = $this->user($company, ['interview.view-panel-summary']);
        $interviewerA = $this->user($company, ['interview.evaluate']);
        $interviewerB = $this->user($company, ['interview.evaluate']);
        $interviewerC = $this->user($company, ['interview.evaluate']);
        $interview = $this->interview($company, $application, $viewer);
        foreach ([$interviewerA, $interviewerB, $interviewerC] as $member) {
            DB::table('application_interviewers')->insert(['company_id' => $company->id, 'application_interview_id' => $interview->id, 'user_id' => $member->id, 'role' => 'interviewer', 'response_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
        InterviewEvaluation::query()->create([
            'company_id' => $company->id, 'application_interview_id' => $interview->id, 'interviewer_user_id' => $interviewerA->id,
            'overall_score' => '80.00', 'recommendation' => 'hire',
            'scorecard' => [['criterion_code' => 'TECH', 'criterion_label' => 'Chuyên môn', 'score' => 4, 'maximum_score' => 5, 'weight' => 100]],
            'strengths' => 'Kiến trúc tốt', 'concerns' => 'Cần thêm domain', 'notes' => 'PRIVATE-NOTE-MUST-NOT-LEAK', 'submitted_at' => now(),
        ]);
        InterviewEvaluation::query()->create([
            'company_id' => $company->id, 'application_interview_id' => $interview->id, 'interviewer_user_id' => $interviewerB->id,
            'overall_score' => '20.00', 'recommendation' => 'no_hire',
            'scorecard' => [['criterion_code' => 'TECH', 'criterion_label' => 'Chuyên môn', 'score' => 1, 'maximum_score' => 5, 'weight' => 100]],
            'strengths' => 'DRAFT-STRENGTH', 'notes' => 'DRAFT-PRIVATE', 'submitted_at' => null,
        ]);

        $summary = app(GetInterviewPanelSummaryHandler::class)->handle(new GetInterviewPanelSummaryQuery($company->id, $interview->id));
        self::assertSame(3, $summary->assignedCount);
        self::assertSame(1, $summary->submittedCount);
        self::assertSame(1, $summary->draftCount);
        self::assertSame(1, $summary->pendingCount);
        self::assertSame('33.33', $summary->completionPercent);
        self::assertSame('80.00', $summary->averageScore);
        self::assertSame('hire', $summary->recommendations[0]->recommendation->value);
        self::assertSame(1, $summary->recommendations[0]->count);
        self::assertSame('100.00', $summary->recommendations[0]->percentage);
        self::assertSame('80.00', $summary->criteria[0]->averagePercent);
        self::assertNotContains('DRAFT-STRENGTH', $summary->strengths);

        $response = $this->actingAs($viewer)->get("/admin/interviews/{$interview->id}/panel-summary?company_id={$company->id}");
        $response->assertOk()->assertSee('data-panel-summary', false)->assertSee('data-refresh-url', false)->assertDontSee('PRIVATE-NOTE-MUST-NOT-LEAK')->assertDontSee('DRAFT-PRIVATE');
    }

    public function test_panel_summary_permission_and_broadcast_payload_are_safe(): void
    {
        [$company, $application] = $this->application('SAFE');
        $user = $this->user($company, ['interview.view']);
        $interview = $this->interview($company, $application, $user);
        $this->actingAs($user)->get("/admin/interviews/{$interview->id}/panel-summary?company_id={$company->id}")->assertForbidden();

        $payload = (new RecruitmentWorkflowChanged($company->id, 'candidate_offer', 41, 'approved', 7, $user->id))->broadcastWith();
        self::assertSame($company->id, $payload['company_id']);
        self::assertSame('candidate_offer', $payload['entity']);
        self::assertSame(41, $payload['entity_id']);
        self::assertSame('approved', $payload['action']);
        self::assertSame(7, $payload['version']);
        self::assertNotSame('', $payload['updated_at']);
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach (['salary', 'email', 'phone', 'meeting_url', 'scorecard', 'private_notes', 'storage_key'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }

        $notificationPayload = (new RecruitmentWorkflowNotification($company->id, 'candidate_offer', 41, 'approved', 7))->toArray($user);
        $notificationJson = json_encode($notificationPayload, JSON_THROW_ON_ERROR);
        foreach (['salary', 'email', 'phone', 'meeting_url', 'scorecard', 'private_notes', 'storage_key'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $notificationJson);
        }
    }

    public function test_internal_notification_is_tenant_scoped_actor_filtered_and_idempotent(): void
    {
        [$company, $application] = $this->application('NOTIFY');
        $actor = $this->user($company, ['interview.schedule']);
        $recipient = $this->user($company, ['interview.evaluate']);
        $foreignCompany = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Schema Foreign', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $foreignUser = $this->user($foreignCompany, ['interview.evaluate']);
        $interview = $this->interview($company, $application, $actor);
        foreach ([$actor, $recipient, $foreignUser] as $member) {
            DB::table('application_interviewers')->insert(['company_id' => $company->id, 'application_interview_id' => $interview->id, 'user_id' => $member->id, 'role' => 'interviewer', 'response_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }

        $event = new RecruitmentWorkflowChanged($company->id, 'interview', $interview->id, 'rescheduled', 3, $actor->id);
        $listener = app(SendRecruitmentWorkflowNotifications::class);
        $listener->handle($event);
        $listener->handle($event);

        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $recipient->id)->count());
        self::assertSame(1, DB::table('recruitment_notification_deliveries')->where('recipient_user_id', $recipient->id)->count());
        self::assertSame(0, DB::table('notifications')->where('notifiable_id', $actor->id)->count());
        self::assertSame(0, DB::table('notifications')->where('notifiable_id', $foreignUser->id)->count());
    }

    public function test_private_channel_authorization_enforces_company_and_user_scope(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testing-key',
            'broadcasting.connections.reverb.secret' => 'testing-secret',
            'broadcasting.connections.reverb.app_id' => 'testing-app',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        [$company] = $this->application('CHANNEL');
        $member = $this->user($company, ['interview.view']);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $payload = ['socket_id' => '1234.5678', 'channel_name' => 'private-company.'.$company->id];

        self::assertTrue(app(TenantAccessService::class)->canAccess($member, $company->id));
        self::assertFalse(app(TenantAccessService::class)->canAccess($other, $company->id));
        $this->actingAs($member)->post('/broadcasting/auth', $payload)->assertSuccessful();
        $this->actingAs($other)->post('/broadcasting/auth', $payload)->assertForbidden();
        $this->post('/broadcasting/auth', $payload)->assertForbidden();
        $this->actingAs($member)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$member->id])->assertSuccessful();
        $this->actingAs($member)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$other->id])->assertForbidden();
    }

    public function test_echo_bootstrap_import_chain_and_dom_templates_are_wired(): void
    {
        $package = file_get_contents(base_path('package.json'));
        $entry = file_get_contents(resource_path('js/app.js'));
        $echo = file_get_contents(resource_path('ts/realtime/echo.ts'));
        $listener = file_get_contents(resource_path('ts/realtime/recruitment.ts'));
        self::assertIsString($package);
        self::assertIsString($entry);
        self::assertIsString($echo);
        self::assertIsString($listener);
        self::assertStringContainsString('laravel-echo', $package);
        self::assertStringContainsString('pusher-js', $package);
        self::assertStringContainsString('../ts/realtime/recruitment.ts', $entry);
        self::assertStringContainsString('VITE_REVERB_ENABLED', $echo);
        self::assertStringContainsString('return null', $echo);
        self::assertStringContainsString('company.${companyId}', $listener);
        self::assertStringContainsString('.recruitment.workflow.changed', $listener);
        self::assertStringContainsString('App.Models.User.${userId}', $listener);
        self::assertStringContainsString('data-recruitment-page', (string) file_get_contents(base_path('Modules/Recruitment/resources/views/interviews/show.blade.php')));
        self::assertStringContainsString('data-panel-summary', (string) file_get_contents(base_path('Modules/Recruitment/resources/views/interviews/panel-summary.blade.php')));
        self::assertStringContainsString('data-offer-id', (string) file_get_contents(base_path('Modules/Recruitment/resources/views/offers/show.blade.php')));
        self::assertStringContainsString('data-recruitment-page', (string) file_get_contents(base_path('Modules/Recruitment/resources/views/applications/pipeline.blade.php')));
        self::assertStringContainsString('data-recruitment-page', (string) file_get_contents(base_path('Modules/Recruitment/resources/views/dashboard/index.blade.php')));
    }

    public function test_offer_submission_resolves_only_company_approvers_and_excludes_actor(): void
    {
        [$company, $application] = $this->application('APPROVER');
        $actor = $this->user($company, ['candidate-offer.submit', 'candidate-offer.approve']);
        $approver = $this->user($company, ['candidate-offer.approve']);
        [$foreignCompany] = $this->application('APPROVER-OTHER');
        $foreignApprover = $this->user($foreignCompany, ['candidate-offer.approve']);
        $requisition = $application->requisition;
        self::assertInstanceOf(RecruitmentRequisition::class, $requisition);
        $position = $requisition->jobPosition;
        self::assertInstanceOf(JobPosition::class, $position);
        $offer = CandidateOffer::query()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'offer_number' => 'SCHEMA-OFFER-APPROVER',
            'job_position_id' => $position->id,
            'organization_unit_id' => $position->organization_unit_id,
            'employment_type' => 'full_time',
            'base_salary' => '25000000.00',
            'currency' => 'VND',
            'start_date' => today()->addMonth(),
            'status' => 'pending_approval',
            'submitted_by' => $actor->id,
            'version' => 2,
        ]);

        $recipients = app(RecruitmentNotificationRecipientResolver::class)->resolve($company->id, 'candidate_offer', $offer->id, 'pending_approval', $actor->id)->pluck('id')->all();
        self::assertSame([$approver->id], $recipients);
        self::assertNotContains($foreignApprover->id, $recipients);
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

    private function interview(Company $company, Application $application, User $scheduler): ApplicationInterview
    {
        return ApplicationInterview::query()->create(['company_id' => $company->id, 'application_id' => $application->id, 'round_number' => 1, 'title' => 'Panel interview', 'interview_type' => 'online', 'status' => 'confirmed', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'timezone' => 'Asia/Ho_Chi_Minh', 'scheduled_by' => $scheduler->id, 'version' => 3]);
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo($permissions);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
