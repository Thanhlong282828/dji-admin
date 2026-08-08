<?php

namespace Tests\Feature\Iam;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Application\Commands\ApproveAccessRequestCommand;
use Modules\Iam\Application\Commands\CompleteAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\CreateAccessRequestCommand;
use Modules\Iam\Application\Commands\CreateAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\DecideAccessReviewItemCommand;
use Modules\Iam\Application\Commands\ExpireAccessAssignmentCommand;
use Modules\Iam\Application\Commands\ProvisionAccessRequestCommand;
use Modules\Iam\Application\Commands\ReviewAccessRequestCommand;
use Modules\Iam\Application\Commands\StartAccessReviewCampaignCommand;
use Modules\Iam\Domain\Services\IamChecksum;
use Modules\Iam\Domain\Services\IamValue;
use Modules\Iam\Domain\Services\SegregationOfDutiesService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class IamCompletionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['iam.view', 'iam.request.create', 'iam.request.review', 'iam.request.approve', 'iam.assignment.manage', 'iam.privileged.manage', 'iam.sod.manage', 'iam.review.manage', 'iam.audit.view'] as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    public function test_privileged_access_requires_three_actors_and_expiry_is_idempotent(): void
    {
        [$company, $requester, $reviewer, $approver, $subject] = $this->actors('IAM-HARD-A');
        $role = Role::findOrCreate('Privileged Operations');
        DB::table('iam_privileged_roles')->insert(['company_id' => $company->id, 'role_id' => $role->id, 'risk_level' => 'privileged', 'requires_two_step_approval' => true, 'max_duration_hours' => 8, 'checksum' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $bus = app(CommandBusInterface::class);
        $id = $this->request($bus, $company, $requester, $subject, 'role', (int) $role->id, now()->addHour()->toDateTimeString());

        $this->assertValidation(fn () => $bus->dispatch(new ReviewAccessRequestCommand($company->id, $id, $requester->id)));
        $bus->dispatch(new ReviewAccessRequestCommand($company->id, $id, $reviewer->id));
        $this->assertValidation(fn () => $bus->dispatch(new ApproveAccessRequestCommand($company->id, $id, $reviewer->id)));
        $this->assertValidation(fn () => $bus->dispatch(new ApproveAccessRequestCommand($company->id, $id, $requester->id)));
        $bus->dispatch(new ApproveAccessRequestCommand($company->id, $id, $approver->id));
        $bus->dispatch(new ProvisionAccessRequestCommand($company->id, $id, $approver->id));

        $assignment = IamValue::int(DB::table('iam_access_assignments')->value('id'));
        DB::table('iam_access_assignments')->where('id', $assignment)->update(['effective_to' => now()->subMinute()]);
        $bus->dispatch(new ExpireAccessAssignmentCommand($company->id, $assignment, $approver->id));
        $bus->dispatch(new ExpireAccessAssignmentCommand($company->id, $assignment, $approver->id));
        self::assertSame('expired', DB::table('iam_access_assignments')->where('id', $assignment)->value('status'));
        self::assertSame(1, DB::table('iam_governance_histories')->where(['aggregate_type' => 'assignment', 'aggregate_id' => $assignment, 'action' => 'expired'])->count());
    }

    public function test_access_review_certifies_persisted_snapshot_and_executes_revoke_once(): void
    {
        [$company, $requester, $reviewer, $approver, $subject] = $this->actors('IAM-HARD-B');
        $role = Role::findOrCreate('Review Snapshot Role');
        $bus = app(CommandBusInterface::class);
        $requestId = $this->request($bus, $company, $requester, $subject, 'role', (int) $role->id, now()->addDay()->toDateTimeString());
        $bus->dispatch(new ReviewAccessRequestCommand($company->id, $requestId, $reviewer->id));
        $bus->dispatch(new ApproveAccessRequestCommand($company->id, $requestId, $approver->id));
        $bus->dispatch(new ProvisionAccessRequestCommand($company->id, $requestId, $approver->id));

        $reviewId = IamValue::int($bus->dispatch(new CreateAccessReviewCampaignCommand($company->id, 'Quarterly certification', 'user', $subject->id, now()->addWeek()->toDateString(), $reviewer->id, (string) Str::uuid())));
        $item = DB::table('iam_access_review_items')->where('access_review_id', $reviewId)->firstOrFail();
        $storedSnapshot = IamValue::string($item->snapshot);
        $subject->removeRole($role);
        $bus->dispatch(new StartAccessReviewCampaignCommand($company->id, $reviewId, $reviewer->id));
        $bus->dispatch(new DecideAccessReviewItemCommand($company->id, $reviewId, IamValue::int($item->id), $reviewer->id, 'revoke', 'No longer required', 1));
        $certificationId = IamValue::int($bus->dispatch(new CompleteAccessReviewCampaignCommand($company->id, $reviewId, $approver->id)));
        $same = IamValue::int($bus->dispatch(new CompleteAccessReviewCampaignCommand($company->id, $reviewId, $approver->id)));

        self::assertSame($certificationId, $same);
        $certification = DB::table('iam_certifications')->where('id', $certificationId)->firstOrFail();
        self::assertStringContainsString('Review Snapshot Role', IamValue::string($certification->snapshot));
        self::assertStringContainsString('Review Snapshot Role', $storedSnapshot);
        self::assertSame(1, DB::table('iam_certifications')->where('access_review_id', $reviewId)->count());
        self::assertSame('revoked', DB::table('iam_access_assignments')->value('status'));
    }

    public function test_sod_matrix_checks_existing_entitlements_and_deduplicates_violations(): void
    {
        [$company, $requester, , , $subject] = $this->actors('IAM-HARD-C');
        $leftRole = Role::findOrCreate('Accounting Creator');
        $rightRole = Role::findOrCreate('Accounting Approver');
        $leftPermission = Permission::findOrCreate('payment.create');
        $rightPermission = Permission::findOrCreate('payment.approve');
        $subject->assignRole($leftRole);
        $subject->givePermissionTo($leftPermission);
        $service = app(SegregationOfDutiesService::class);

        foreach ([
            ['RR', 'role', (int) $leftRole->id, 'role', (int) $rightRole->id, 'blocking'],
            ['PP', 'permission', (int) $leftPermission->id, 'permission', (int) $rightPermission->id, 'blocking'],
            ['RP', 'role', (int) $leftRole->id, 'permission', (int) $rightPermission->id, 'warning'],
        ] as [$code, $leftType, $leftId, $rightType, $rightId, $severity]) {
            DB::table('iam_sod_violations')->delete();
            DB::table('iam_sod_rules')->delete();
            $ruleId = DB::table('iam_sod_rules')->insertGetId(['company_id' => $company->id, 'code' => 'MATRIX-'.$code, 'name' => $code, 'left_type' => $leftType, 'left_id' => $leftId, 'right_type' => $rightType, 'right_id' => $rightId, 'severity' => $severity, 'active' => true, 'checksum' => IamChecksum::make(['code' => $code]), 'created_at' => now(), 'updated_at' => now()]);
            $requestId = DB::table('iam_access_requests')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'requester_id' => $requester->id, 'subject_user_id' => $subject->id, 'status' => 'requested', 'reason' => 'SoD matrix', 'idempotency_key' => (string) Str::uuid(), 'version' => 1, 'checksum' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);
            $blocked = $service->detect($company->id, $subject->id, $rightType, $rightId, $requestId);
            $service->detect($company->id, $subject->id, $rightType, $rightId, $requestId);
            self::assertSame($severity === 'blocking', $blocked);
            self::assertSame(1, DB::table('iam_sod_violations')->where(['sod_rule_id' => $ruleId, 'access_request_id' => $requestId])->count());
        }
    }

    public function test_route_permission_matrix_and_notification_outbox_are_idempotent(): void
    {
        [$company, $requester, $reviewer, , $subject] = $this->actors('IAM-HARD-D');
        $role = Role::findOrCreate('Route Matrix Role');
        $bus = app(CommandBusInterface::class);
        $requestId = $this->request($bus, $company, $requester, $subject, 'role', (int) $role->id, now()->addDay()->toDateTimeString());

        $this->actingAs($requester)->post(route('admin.iam.requests.review', ['request' => $requestId]), ['company_id' => $company->id])->assertForbidden();
        $this->actingAs($reviewer)->post(route('admin.iam.requests.review', ['request' => $requestId]), ['company_id' => $company->id])->assertRedirect();
        Artisan::call('iam:consume-notifications');
        $count = DB::table('notification_messages')->count();
        Artisan::call('iam:consume-notifications');
        self::assertSame($count, DB::table('notification_messages')->count());
        self::assertGreaterThan(0, $count);
    }

    /** @return array{Company,User,User,User,User} */
    private function actors(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $users = [User::factory()->create(), User::factory()->create(), User::factory()->create(), User::factory()->create()];
        foreach ($users as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $users[0]->givePermissionTo('iam.request.create');
        $users[1]->givePermissionTo('iam.request.review');
        $users[2]->givePermissionTo(['iam.request.approve', 'iam.assignment.manage']);

        return [$company, $users[0], $users[1], $users[2], $users[3]];
    }

    private function request(CommandBusInterface $bus, Company $company, User $requester, User $subject, string $type, int $id, ?string $expiry): int
    {
        return IamValue::int($bus->dispatch(new CreateAccessRequestCommand($company->id, $requester->id, $subject->id, 'Governed business access', (string) Str::uuid(), [['type' => $type, 'id' => $id, 'effective_to' => $expiry]])));
    }

    private function assertValidation(callable $action): void
    {
        $caught = false;
        try {
            $action();
        } catch (ValidationException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Expected validation exception.');
    }
}
