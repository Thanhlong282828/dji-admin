<?php

namespace Tests\Concurrency\Iam;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Iam\Domain\Services\AccessReviewService;
use Modules\Iam\Domain\Services\IamGovernanceService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Modules\Workflow\Domain\Services\WorkflowTaskService;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class EnterpriseIamProcessConcurrencyTest extends TestCase
{
    /** @var list<int> */
    private array $companies = [];

    /** @var list<int> */
    private array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->companies as $company) {
            $messageIds = DB::table('notification_messages')->where('company_id', $company)->pluck('id');
            DB::table('notification_outbox')->where('company_id', $company)->delete();
            DB::table('notification_delivery_attempts')->where('company_id', $company)->delete();
            DB::table('notification_recipients')->where('company_id', $company)->delete();
            DB::table('notification_messages')->whereIn('id', $messageIds)->delete();
            $employeeIds = DB::table('employees')->where('company_id', $company)->pluck('id');
            DB::table('employee_lifecycle_outbox')->where('company_id', $company)->delete();
            DB::table('employee_lifecycle_events')->where('company_id', $company)->delete();
            DB::table('employment_transitions')->where('company_id', $company)->delete();
            Employee::query()->whereIn('id', $employeeIds)->forceDelete();
            $instanceIds = DB::table('workflow_instances')->where('company_id', $company)->pluck('id');
            DB::table('workflow_outbox')->where('company_id', $company)->delete();
            DB::table('workflow_histories')->where('company_id', $company)->delete();
            DB::table('workflow_decisions')->where('company_id', $company)->delete();
            DB::table('workflow_tasks')->where('company_id', $company)->delete();
            DB::table('workflow_instances')->whereIn('id', $instanceIds)->delete();
            $definitionIds = DB::table('workflow_definitions')->where('company_id', $company)->pluck('id');
            $versionIds = DB::table('workflow_definition_versions')->whereIn('workflow_definition_id', $definitionIds)->pluck('id');
            $stepIds = DB::table('workflow_steps')->whereIn('workflow_definition_version_id', $versionIds)->pluck('id');
            DB::table('workflow_step_transitions')->whereIn('from_step_id', $stepIds)->delete();
            DB::table('workflow_steps')->whereIn('id', $stepIds)->delete();
            DB::table('workflow_definition_versions')->whereIn('id', $versionIds)->delete();
            DB::table('workflow_definitions')->whereIn('id', $definitionIds)->delete();
            $requests = DB::table('iam_access_requests')->where('company_id', $company)->pluck('id');
            $reviews = DB::table('iam_access_reviews')->where('company_id', $company)->pluck('id');
            DB::table('iam_certifications')->where('company_id', $company)->delete();
            DB::table('iam_access_review_items')->whereIn('access_review_id', $reviews)->delete();
            DB::table('iam_access_reviews')->where('company_id', $company)->delete();
            DB::table('iam_sod_violations')->where('company_id', $company)->delete();
            DB::table('iam_sod_rules')->where('company_id', $company)->delete();
            DB::table('iam_access_assignments')->where('company_id', $company)->delete();
            DB::table('iam_access_request_items')->whereIn('access_request_id', $requests)->delete();
            DB::table('iam_access_requests')->where('company_id', $company)->delete();
            DB::table('iam_governance_histories')->where('company_id', $company)->delete();
            DB::table('iam_outbox')->where('company_id', $company)->delete();
            DB::table('company_user_access')->where('company_id', $company)->delete();
            Company::query()->whereKey($company)->forceDelete();
        }
        User::query()->whereKey($this->users)->delete();
        parent::tearDown();
    }

    public function test_create_create_has_one_request_and_item(): void
    {
        $f = $this->fixture();
        $payload = ['company_id' => $f['company'], 'requester_id' => $f['requester'], 'subject_id' => $f['subject'], 'reason' => 'Race create', 'idempotency_key' => (string) Str::uuid(), 'entitlement_type' => 'role', 'entitlement_id' => $f['role'], 'effective_to' => now()->addDay()->toDateTimeString()];
        $race = (new MysqlProcessRaceRunner)->run('iam_request_create', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('iam_access_requests')->where('company_id', $f['company'])->count());
        self::assertSame(1, DB::table('iam_access_request_items')->count());
    }

    public function test_approve_approve_and_approve_reject_have_one_terminal_decision(): void
    {
        foreach ([false, true] as $versusReject) {
            $f = $this->fixture(true);
            $payload = ['company_id' => $f['company'], 'request_id' => $f['request'], 'actor_id' => $f['approver'], 'reason' => 'race'];
            $race = (new MysqlProcessRaceRunner)->run('iam_request_approve', $payload, $payload, $versusReject ? 'iam_request_reject' : null);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
            self::assertContains(DB::table('iam_access_requests')->where('id', $f['request'])->value('status'), ['approved', 'rejected']);
            self::assertSame(1, DB::table('iam_governance_histories')->where('aggregate_type', 'access_request')->where('aggregate_id', $f['request'])->whereIn('action', ['approve', 'reject'])->count());
        }
    }

    public function test_provision_and_revoke_races_are_idempotent(): void
    {
        $f = $this->fixture(true, true);
        $payload = ['company_id' => $f['company'], 'request_id' => $f['request'], 'actor_id' => $f['approver']];
        $race = (new MysqlProcessRaceRunner)->run('iam_request_provision', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('iam_access_assignments')->where('company_id', $f['company'])->count());
        self::assertSame(1, DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $f['subject'], 'role_id' => $f['role']])->count());
        $assignment = $this->integer(DB::table('iam_access_assignments')->where('company_id', $f['company'])->value('id'));
        $revoke = ['company_id' => $f['company'], 'assignment_id' => $assignment, 'actor_id' => $f['approver']];
        (new MysqlProcessRaceRunner)->run('iam_assignment_revoke', $revoke, $revoke);
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'assignment', 'aggregate_id' => $assignment, 'action' => 'revoked'])->count());
    }

    public function test_review_keep_revoke_has_one_item_decision(): void
    {
        $f = $this->fixture(true, true, true);
        $base = ['company_id' => $f['company'], 'review_id' => $f['review'], 'item_id' => $f['review_item'], 'actor_id' => $f['reviewer'], 'reason' => 'race decision', 'version' => 1];
        $race = (new MysqlProcessRaceRunner)->run('iam_review_decide', $base + ['decision' => 'keep'], $base + ['decision' => 'revoke']);
        self::assertContains($race['exit_codes'], [[0, 2], [2, 0]], implode("\n", $race['outputs']));
        self::assertContains(DB::table('iam_access_review_items')->where('id', $f['review_item'])->value('decision'), ['keep', 'revoke']);
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'access_review_item', 'aggregate_id' => $f['review_item']])->count());
    }

    public function test_workflow_completion_consumer_race_provisions_once(): void
    {
        $f = $this->fixture();
        $request = app(IamGovernanceService::class)->create($f['company'], $f['requester'], $f['subject'], 'Workflow race', (string) Str::uuid(), [['type' => 'role', 'id' => $f['role'], 'effective_to' => now()->addDay()->toDateTimeString()]]);
        $definition = app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData($f['company'], $f['requester'], 'IAM-WF-'.Str::random(6), 'IAM approval', null, 'sequential', [['key' => 'approve', 'name' => 'Approve', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $f['approver'], 'fallback_resolver_type' => 'specific_user', 'fallback_resolver_value' => (string) $f['approver']]]));
        app(WorkflowDefinitionService::class)->publish($f['company'], $definition, $f['requester']);
        $instance = app(WorkflowExecutionService::class)->start(new StartWorkflowData($f['company'], $f['requester'], $definition, 'iam_access_request', $request, 'iam-workflow-'.Str::uuid()));
        app(IamGovernanceService::class)->attachWorkflow($f['company'], $request, $f['requester'], $instance);
        $task = $this->integer(DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->value('id'));
        app(WorkflowTaskService::class)->decide($f['company'], $task, $f['approver'], 'approved', null, 'iam-workflow-decision-'.Str::uuid());

        $race = (new MysqlProcessRaceRunner)->run('iam_consume_workflow', [], []);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('iam_access_assignments')->where('company_id', $f['company'])->count());
        self::assertSame(1, DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $f['subject'], 'role_id' => $f['role']])->count());
        self::assertSame(1, DB::table('workflow_outbox')->where(['workflow_instance_id' => $instance, 'event_type' => 'workflow.completed'])->whereNotNull('iam_consumed_at')->count());
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'access_request', 'aggregate_id' => $request, 'action' => 'provisioned'])->count());
        (new MysqlProcessRaceRunner)->run('iam_consume_workflow', [], []);
        self::assertSame(1, DB::table('iam_access_assignments')->where('company_id', $f['company'])->count());
    }

    public function test_employee_termination_consumer_race_revokes_once(): void
    {
        $f = $this->employeeConsumerFixture('employee.terminated');
        $race = (new MysqlProcessRaceRunner)->run('iam_consume_employee', [], []);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame('revoked', DB::table('iam_access_assignments')->where('id', $f['assignment'])->value('status'));
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'assignment', 'aggregate_id' => $f['assignment'], 'action' => 'revoked'])->count());
        self::assertSame(1, DB::table('employee_lifecycle_outbox')->where('id', $f['outbox'])->whereNotNull('iam_consumed_at')->count());
        self::assertSame(1, DB::table('iam_outbox')->where(['company_id' => $f['company'], 'event_type' => 'iam.access.revoked'])->count());
        self::assertSame(0, DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $f['subject'], 'role_id' => $f['role']])->count());
    }

    public function test_employee_transfer_consumer_race_creates_one_review_without_revocation(): void
    {
        $f = $this->employeeConsumerFixture('employee.transferred');
        $race = (new MysqlProcessRaceRunner)->run('iam_consume_employee', [], []);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame('active', DB::table('iam_access_assignments')->where('id', $f['assignment'])->value('status'));
        self::assertSame(1, DB::table('iam_access_reviews')->where(['company_id' => $f['company'], 'scope_type' => 'user', 'scope_id' => $f['subject']])->count());
        self::assertSame(1, DB::table('iam_access_review_items')->count());
        self::assertSame(1, DB::table('employee_lifecycle_outbox')->where('id', $f['outbox'])->whereNotNull('iam_consumed_at')->count());
    }

    public function test_notification_consumer_race_queues_one_message_and_checkpoint(): void
    {
        $f = $this->fixture();
        $payload = ['subject_user_id' => $f['subject'], 'request_id' => 901];
        $outbox = DB::table('iam_outbox')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $f['company'], 'event_type' => 'iam.access.submitted', 'aggregate_type' => 'access_request', 'aggregate_id' => 901, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'idempotency_key' => (string) Str::uuid(), 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
        $race = (new MysqlProcessRaceRunner)->run('iam_consume_notification', [], []);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('notification_messages')->where('company_id', $f['company'])->count());
        self::assertSame(1, DB::table('notification_recipients')->where('company_id', $f['company'])->count());
        self::assertSame(1, DB::table('iam_outbox')->where('id', $outbox)->whereNotNull('published_at')->count());
        (new MysqlProcessRaceRunner)->run('iam_consume_notification', [], []);
        self::assertSame(1, DB::table('notification_messages')->where('company_id', $f['company'])->count());
    }

    public function test_privileged_approve_race_has_one_transition_and_assignment(): void
    {
        $f = $this->fixture();
        DB::table('iam_privileged_roles')->insert(['company_id' => $f['company'], 'role_id' => $f['role'], 'risk_level' => 'privileged', 'requires_two_step_approval' => true, 'max_duration_hours' => 8, 'checksum' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $request = app(IamGovernanceService::class)->create($f['company'], $f['requester'], $f['subject'], 'Privileged race', (string) Str::uuid(), [['type' => 'role', 'id' => $f['role'], 'effective_to' => now()->addHour()->toDateTimeString()]]);
        app(IamGovernanceService::class)->transition($f['company'], $request, $f['reviewer'], 'review');
        $payload = ['company_id' => $f['company'], 'request_id' => $request, 'actor_id' => $f['approver'], 'reason' => 'privileged race'];
        $race = (new MysqlProcessRaceRunner)->run('iam_request_approve', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
        (new MysqlProcessRaceRunner)->run('iam_request_provision', $payload, $payload);
        self::assertSame(1, DB::table('iam_access_assignments')->where(['company_id' => $f['company'], 'privileged' => true])->count());
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'access_request', 'aggregate_id' => $request, 'action' => 'approve'])->count());
        self::assertSame(1, DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $f['subject'], 'role_id' => $f['role']])->count());
    }

    /** @return array{company:int,requester:int,reviewer:int,approver:int,subject:int,role:int,request:int,review:int,review_item:int} */
    private function fixture(bool $request = false, bool $approved = false, bool $review = false): array
    {
        $token = Str::lower(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'IAM-RACE-'.$token, 'name' => 'IAM Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $actors = [User::factory()->create(), User::factory()->create(), User::factory()->create(), User::factory()->create()];
        $this->companies[] = (int) $company->id;
        foreach ($actors as $actor) {
            $this->users[] = (int) $actor->id;
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id]);
        }
        $role = Role::findOrCreate('IAM Race Role '.$token);
        $requestId = 0;
        if ($request) {
            $requestId = app(IamGovernanceService::class)->create((int) $company->id, (int) $actors[0]->id, (int) $actors[3]->id, 'Race request', (string) Str::uuid(), [['type' => 'role', 'id' => (int) $role->id, 'effective_to' => now()->addDay()->toDateTimeString()]]);
            app(IamGovernanceService::class)->transition((int) $company->id, $requestId, (int) $actors[1]->id, 'review');
            if ($approved) {
                app(IamGovernanceService::class)->transition((int) $company->id, $requestId, (int) $actors[2]->id, 'approve');
            }
        }
        $reviewId = $itemId = 0;
        if ($review) {
            app(IamGovernanceService::class)->provision((int) $company->id, $requestId, (int) $actors[2]->id);
            $reviewId = app(AccessReviewService::class)->create((int) $company->id, 'Race review', 'user', (int) $actors[3]->id, now()->addDay()->toDateString(), (int) $actors[1]->id, (string) Str::uuid());
            app(AccessReviewService::class)->start((int) $company->id, $reviewId, (int) $actors[1]->id);
            $itemId = $this->integer(DB::table('iam_access_review_items')->where('access_review_id', $reviewId)->value('id'));
        }

        return ['company' => (int) $company->id, 'requester' => (int) $actors[0]->id, 'reviewer' => (int) $actors[1]->id, 'approver' => (int) $actors[2]->id, 'subject' => (int) $actors[3]->id, 'role' => (int) $role->id, 'request' => $requestId, 'review' => $reviewId, 'review_item' => $itemId];
    }

    /** @return array{company:int,subject:int,role:int,assignment:int,outbox:int} */
    private function employeeConsumerFixture(string $eventType): array
    {
        $f = $this->fixture(true, true);
        app(IamGovernanceService::class)->provision($f['company'], $f['request'], $f['approver']);
        $assignment = $this->integer(DB::table('iam_access_assignments')->where('company_id', $f['company'])->value('id'));
        $employee = Employee::query()->create(['company_id' => $f['company'], 'user_id' => $f['subject'], 'employee_code' => 'IAM-EVT-'.Str::random(6), 'full_name' => 'IAM Lifecycle Subject', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $eventId = (string) Str::uuid();
        DB::table('employee_lifecycle_events')->insert(['company_id' => $f['company'], 'employee_id' => $employee->id, 'event_type' => $eventType, 'from_state' => 'active', 'to_state' => $eventType === 'employee.terminated' ? 'terminated' : 'active', 'effective_at' => now(), 'correlation_id' => (string) Str::uuid(), 'causation_id' => null, 'idempotency_key' => 'iam-'.$eventId, 'payload_checksum' => str_repeat('b', 64), 'created_by' => $f['approver'], 'created_at' => now()]);
        $outbox = DB::table('employee_lifecycle_outbox')->insertGetId(['event_id' => $eventId, 'company_id' => $f['company'], 'employee_id' => $employee->id, 'event_type' => $eventType, 'payload' => json_encode(['employee_id' => $employee->id], JSON_THROW_ON_ERROR), 'payload_checksum' => str_repeat('c', 64), 'occurred_at' => now(), 'created_at' => now()]);

        return ['company' => $f['company'], 'subject' => $f['subject'], 'role' => $f['role'], 'assignment' => $assignment, 'outbox' => $outbox];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric identifier.');
    }
}
