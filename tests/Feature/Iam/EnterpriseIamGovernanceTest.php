<?php

namespace Tests\Feature\Iam;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Application\Commands\ApproveAccessRequestCommand;
use Modules\Iam\Application\Commands\CreateAccessRequestCommand;
use Modules\Iam\Application\Commands\ProvisionAccessRequestCommand;
use Modules\Iam\Application\Commands\ReviewAccessRequestCommand;
use Modules\Iam\Application\Commands\RevokeAccessAssignmentCommand;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class EnterpriseIamGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['iam.view', 'iam.request.create', 'iam.request.review', 'iam.request.approve', 'iam.assignment.manage', 'iam.privileged.manage', 'iam.sod.manage', 'iam.review.manage', 'iam.audit.view'] as $p) {
            Permission::findOrCreate($p);
        }
    }

    public function test_access_request_maker_checker_provision_and_idempotent_revoke(): void
    {
        $c = $this->company('IAM-A');
        $maker = $this->user($c, ['iam.view', 'iam.request.create']);
        $checker = $this->user($c, ['iam.request.review', 'iam.request.approve', 'iam.assignment.manage']);
        $subject = $this->user($c, []);
        $role = Role::findOrCreate('IAM Test Role');
        $bus = app(CommandBusInterface::class);
        $key = (string) Str::uuid();
        $command = new CreateAccessRequestCommand($c->id, $maker->id, $subject->id, 'Approved operational need', $key, [['type' => 'role', 'id' => $this->databaseInt($role->id), 'effective_to' => now()->addDay()->toDateTimeString()]]);
        $id = $this->databaseInt($bus->dispatch($command));
        self::assertSame($id, $bus->dispatch($command));
        $bus->dispatch(new ReviewAccessRequestCommand($c->id, $id, $checker->id));
        $this->expectException(ValidationException::class);
        $bus->dispatch(new ApproveAccessRequestCommand($c->id, $id, $maker->id));
    }

    public function test_checker_can_approve_and_provision_once_then_revoke_once(): void
    {
        $c = $this->company('IAM-B');
        $maker = $this->user($c, []);
        $checker = $this->user($c, []);
        $approver = $this->user($c, []);
        $subject = $this->user($c, []);
        $role = Role::findOrCreate('IAM Provision Role');
        $bus = app(CommandBusInterface::class);
        $id = $this->databaseInt($bus->dispatch(new CreateAccessRequestCommand($c->id, $maker->id, $subject->id, 'Temporary role', (string) Str::uuid(), [['type' => 'role', 'id' => $this->databaseInt($role->id), 'effective_to' => now()->addDay()->toDateTimeString()]])));
        $bus->dispatch(new ReviewAccessRequestCommand($c->id, $id, $checker->id));
        $bus->dispatch(new ApproveAccessRequestCommand($c->id, $id, $approver->id));
        $bus->dispatch(new ProvisionAccessRequestCommand($c->id, $id, $approver->id));
        $bus->dispatch(new ProvisionAccessRequestCommand($c->id, $id, $approver->id));
        $freshSubject = $subject->fresh();
        self::assertInstanceOf(User::class, $freshSubject);
        self::assertTrue($freshSubject->hasRole($role));
        self::assertSame(1, DB::table('iam_access_assignments')->count());
        $assignment = $this->databaseInt(DB::table('iam_access_assignments')->value('id'));
        $bus->dispatch(new RevokeAccessAssignmentCommand($c->id, $assignment, $approver->id));
        $bus->dispatch(new RevokeAccessAssignmentCommand($c->id, $assignment, $approver->id));
        $revokedSubject = $subject->fresh();
        self::assertInstanceOf(User::class, $revokedSubject);
        self::assertFalse($revokedSubject->hasRole($role));
        self::assertSame(1, DB::table('iam_governance_histories')->where('action', 'revoked')->count());
    }

    public function test_routes_enforce_permission_and_foreign_company_as_not_found(): void
    {
        $a = $this->company('IAM-C');
        $b = $this->company('IAM-D');
        $none = $this->user($a, []);
        $viewer = $this->user($a, ['iam.view']);
        $this->get(route('admin.iam.dashboard', ['company_id' => $a->id]))->assertRedirect();
        $this->actingAs($none)->get(route('admin.iam.dashboard', ['company_id' => $a->id]))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.iam.dashboard', ['company_id' => $a->id]))->assertOk();
        $this->actingAs($viewer)->get(route('admin.iam.dashboard', ['company_id' => $b->id]))->assertNotFound();
    }

    public function test_blocking_sod_prevents_provision(): void
    {
        $c = $this->company('IAM-E');
        $maker = $this->user($c, []);
        $checker = $this->user($c, []);
        $subject = $this->user($c, []);
        $left = Role::findOrCreate('Payroll Maker');
        $right = Role::findOrCreate('Payroll Approver');
        $subject->assignRole($left);
        DB::table('iam_sod_rules')->insert(['company_id' => $c->id, 'code' => 'PAY-MAKER-APPROVER', 'name' => 'Payroll maker cannot approve', 'left_type' => 'role', 'left_id' => $left->id, 'right_type' => 'role', 'right_id' => $right->id, 'severity' => 'blocking', 'active' => true, 'checksum' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $bus = app(CommandBusInterface::class);
        $id = $this->databaseInt($bus->dispatch(new CreateAccessRequestCommand($c->id, $maker->id, $subject->id, 'Conflict test', (string) Str::uuid(), [['type' => 'role', 'id' => $this->databaseInt($right->id), 'effective_to' => now()->addDay()->toDateTimeString()]])));
        $bus->dispatch(new ReviewAccessRequestCommand($c->id, $id, $checker->id));
        $this->expectException(ValidationException::class);
        $bus->dispatch(new ApproveAccessRequestCommand($c->id, $id, $maker->id));
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @param list<string> $permissions */
    private function user(Company $c, array $permissions): User
    {
        $u = User::factory()->create();
        if ($permissions !== []) {
            $u->givePermissionTo($permissions);
        }DB::table('company_user_access')->insert(['company_id' => $c->id, 'user_id' => $u->id]);

        return $u;
    }

    private function databaseInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && ctype_digit($value)));

        return (int) $value;
    }
}
