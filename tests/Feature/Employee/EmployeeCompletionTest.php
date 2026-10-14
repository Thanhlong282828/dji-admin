<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Enums\DocumentScanStatus;
use Modules\Employee\Domain\Models\DataConsent;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Models\EmploymentContract;
use Modules\Employee\Domain\Services\MalwareScannerInterface;
use Modules\Employee\Notifications\EmployeeUserInvitationNotification;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Tests\TestCase;

final class EmployeeCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('employee.document_disk', 'local');
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_assignment_enforces_allocation_primary_and_tenant_scope(): void
    {
        [$company, $unit, $position, $employee] = $this->organization('A');
        [$foreignCompany, , , $foreignEmployee] = $this->organization('B');
        $user = $this->authorized($company, ['employee-assignment.create', 'employee-assignment.view']);

        $this->actingAs($user)->post(route('admin.employees.assignments.store', $employee), $this->assignmentPayload($company, $unit, $position, 60))->assertRedirect();
        $this->assertDatabaseHas('employee_assignments', ['employee_id' => $employee->id, 'allocation_percent' => 60]);

        $this->actingAs($user)->post(route('admin.employees.assignments.store', $employee), $this->assignmentPayload($company, $unit, $position, 50, 'concurrent'))->assertSessionHasErrors('allocation_percent');
        $this->actingAs($user)->post(route('admin.employees.assignments.store', $employee), $this->assignmentPayload($company, $unit, $position, 40))->assertSessionHasErrors('assignment_type');

        $this->actingAs($user)->get(route('admin.employees.assignments.index', ['employee' => $foreignEmployee->id, 'company_id' => $foreignCompany->id]))->assertForbidden();
    }

    public function test_transfer_closes_current_assignment_and_writes_history(): void
    {
        [$company, $unit, $position, $employee] = $this->organization('T');
        $user = $this->authorized($company, ['employee-assignment.create', 'employee-assignment.transfer']);
        $assignment = EmployeeAssignment::query()->create([
            'employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $position->id,
            'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => '2026-01-01',
        ]);
        $payload = $this->assignmentPayload($company, $unit, $position, 100);
        $payload['current_assignment_id'] = $assignment->id;
        $payload['effective_from'] = '2026-06-01';
        $payload['reason'] = 'Điều chuyển theo kế hoạch nhân sự';

        $this->actingAs($user)->post(route('admin.employees.transfer', $employee), $payload)->assertRedirect();

        self::assertSame('2026-05-31', $assignment->refresh()->effective_to?->format('Y-m-d'));
        self::assertSame(2, EmployeeAssignment::query()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('employee_histories', ['employee_id' => $employee->id, 'event_type' => 'transfer']);
    }

    public function test_employee_export_is_real_filtered_and_tenant_scoped(): void
    {
        [$company, , , $employee] = $this->organization('E');
        [$foreign, , , $foreignEmployee] = $this->organization('F');
        $employee->update(['full_name' => '=Schema Export']);
        $foreignEmployee->update(['full_name' => 'Foreign Secret']);
        $user = $this->authorized($company, ['employee.export']);

        $response = $this->actingAs($user)->get(route('admin.employees.export', ['company_id' => $company->id, 'search' => 'Schema']));
        $response->assertOk();
        $content = $response->streamedContent();
        self::assertStringContainsString('Employee Code', $content);
        self::assertStringContainsString("'=Schema Export", $content);
        self::assertStringNotContainsString('Foreign Secret', $content);
        self::assertStringNotContainsString('identity_number', $content);

        $this->actingAs($user)->get(route('admin.employees.export', ['company_id' => $foreign->id]))->assertForbidden();
    }

    public function test_private_document_upload_checksum_download_and_authorization(): void
    {
        Storage::fake('local');
        [$company, , , $employee] = $this->organization('D');
        $user = $this->authorized($company, ['employee-document.upload', 'employee-document.download']);
        $file = UploadedFile::fake()->createWithContent('policy.pdf', "%PDF-1.4\nSchema private document");

        $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
            'company_id' => $company->id, 'type' => 'identity', 'name' => 'Hồ sơ định danh', 'document' => $file,
        ])->assertRedirect();
        $document = DB::table('employee_documents')->where('employee_id', $employee->id)->first();
        self::assertSame(64, strlen((string) $document->checksum));
        self::assertStringNotContainsString('http', (string) $document->storage_key);

        $this->actingAs(User::factory()->create())->get(route('admin.employee-documents.download', ['document' => $document->id, 'company_id' => $company->id]))->assertForbidden();
    }

    public function test_private_employee_document_uses_supabase_and_downloads_with_temporary_url(): void
    {
        Storage::fake('supabase');
        Storage::disk('supabase')->buildTemporaryUrlsUsing(fn (string $path): string => 'https://storage.test/signed/'.$path);
        config()->set('employee.document_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'testing-key');
        config()->set('filesystems.disks.supabase.secret', 'testing-secret');
        config()->set('filesystems.disks.supabase.bucket', 'testing-bucket');
        config()->set('filesystems.disks.supabase.endpoint', 'https://storage.test');

        [$company, , , $employee] = $this->organization('S3');
        $user = $this->authorized($company, ['employee-document.upload', 'employee-document.download']);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\nPrivate employment contract");

        $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
            'company_id' => $company->id, 'type' => 'contract', 'name' => 'Hợp đồng lao động', 'document' => $file,
        ])->assertRedirect();

        $document = DB::table('employee_documents')->where('employee_id', $employee->id)->first();
        Storage::disk('supabase')->assertExists((string) $document->storage_key);
        $policy = json_decode((string) $document->access_policy, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('supabase', $policy['storage_disk']);
        $this->actingAs($user)->get(route('admin.employee-documents.download', ['document' => $document->id, 'company_id' => $company->id]))
            ->assertRedirect('https://storage.test/signed/'.$document->storage_key);
    }

    public function test_private_employee_document_rejects_guest_forged_company_employee_and_archive_access(): void
    {
        Storage::fake('supabase');
        $signedUrlCalls = 0;
        Storage::disk('supabase')->buildTemporaryUrlsUsing(function (string $path) use (&$signedUrlCalls): string {
            $signedUrlCalls++;

            return 'https://storage.test/signed/'.$path;
        });
        config()->set('employee.document_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'testing-key');
        config()->set('filesystems.disks.supabase.secret', 'testing-secret');
        config()->set('filesystems.disks.supabase.bucket', 'testing-bucket');
        config()->set('filesystems.disks.supabase.endpoint', 'https://storage.test');

        [$company, , , $employee] = $this->organization('BOUNDARY-A');
        [$foreign, , , $foreignEmployee] = $this->organization('BOUNDARY-B');
        $user = $this->authorized($company, ['employee-document.upload', 'employee-document.download', 'employee-document.archive']);
        DB::table('company_user_access')->insert(['company_id' => $foreign->id, 'user_id' => $user->id]);
        $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
            'company_id' => $company->id,
            'type' => 'identity',
            'name' => 'Tenant boundary',
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertRedirect();
        $document = DB::table('employee_documents')->where('employee_id', $employee->id)->firstOrFail();

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.employee-documents.download', ['document' => $document->id, 'company_id' => $company->id]))->assertRedirect(route('login'));
        self::assertSame(0, $signedUrlCalls);

        $this->actingAs($user)->get(route('admin.employee-documents.download', ['document' => $document->id, 'company_id' => $foreign->id]))->assertNotFound();
        $this->actingAs($user)->delete(route('admin.employee-documents.archive', ['document' => $document->id]), ['company_id' => $foreign->id])->assertNotFound();
        $this->actingAs($user)->post(route('admin.employees.documents.store', $foreignEmployee), [
            'company_id' => $company->id,
            'type' => 'identity',
            'name' => 'Forged employee',
            'document' => UploadedFile::fake()->createWithContent('forged.pdf', '%PDF-1.4'),
        ])->assertNotFound();
        self::assertSame(0, $signedUrlCalls);
        self::assertNull(DB::table('employee_documents')->where('id', $document->id)->value('archived_at'));
        self::assertSame(0, DB::table('employee_documents')->where('employee_id', $foreignEmployee->id)->count());

        Storage::disk('supabase')->delete((string) $document->storage_key);
        $this->actingAs($user)->get(route('admin.employee-documents.download', ['document' => $document->id, 'company_id' => $company->id]))->assertNotFound();
        self::assertSame(0, $signedUrlCalls);
    }

    public function test_private_employee_document_fails_closed_when_scanner_is_unavailable(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerInterface::class, new class implements MalwareScannerInterface
        {
            public function scan(string $absolutePath): DocumentScanStatus
            {
                return DocumentScanStatus::Failed;
            }
        });
        [$company, , , $employee] = $this->organization('SCAN');
        $user = $this->authorized($company, ['employee-document.upload']);

        $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
            'company_id' => $company->id,
            'type' => 'identity',
            'name' => 'Scanner unavailable',
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertStatus(503);

        self::assertSame(0, DB::table('employee_documents')->where('employee_id', $employee->id)->count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_private_employee_document_rejects_supabase_mime_outside_bucket_allowlist(): void
    {
        Storage::fake('supabase');
        config()->set('employee.document_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'testing-key');
        config()->set('filesystems.disks.supabase.secret', 'testing-secret');
        config()->set('filesystems.disks.supabase.bucket', 'testing-bucket');
        config()->set('filesystems.disks.supabase.endpoint', 'https://storage.test');
        [$company, , , $employee] = $this->organization('MIME');
        $user = $this->authorized($company, ['employee-document.upload']);

        $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
            'company_id' => $company->id,
            'type' => 'contract',
            'name' => 'Unsupported Supabase DOCX',
            'document' => UploadedFile::fake()->createWithContent('contract.docx', "PK\x03\x04document"),
        ])->assertSessionHasErrors('document');

        self::assertSame(0, DB::table('employee_documents')->where('employee_id', $employee->id)->count());
        self::assertSame([], Storage::disk('supabase')->allFiles());
    }

    public function test_private_employee_document_transport_failure_creates_no_metadata(): void
    {
        config()->set('employee.document_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'testing-key');
        config()->set('filesystems.disks.supabase.secret', 'testing-secret');
        config()->set('filesystems.disks.supabase.bucket', 'testing-bucket');
        config()->set('filesystems.disks.supabase.endpoint', 'https://storage.test');
        $filesystem = \Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->once()->andReturnFalse();
        $filesystem->shouldReceive('putFileAs')->once()->withArgs(fn (string $directory, UploadedFile $file, string $name, array $options): bool => str_starts_with($directory, 'employees/') && str_ends_with($name, '.pdf') && $options === ['ContentType' => 'application/pdf'])->andThrow(new \RuntimeException('Simulated S3 transport failure.'));
        Storage::set('supabase', $filesystem);
        [$company, , , $employee] = $this->organization('TRANSPORT');
        $user = $this->authorized($company, ['employee-document.upload']);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->post(route('admin.employees.documents.store', $employee), [
                'company_id' => $company->id,
                'type' => 'identity',
                'name' => 'Transport failure',
                'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
            ]);
            self::fail('Expected the simulated S3 transport failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated S3 transport failure.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('employee_documents')->where('employee_id', $employee->id)->count());
    }

    public function test_contract_state_machine_rejects_invalid_transition_and_allows_valid_flow(): void
    {
        [$company, , , $employee] = $this->organization('C');
        $user = $this->authorized($company, ['employment-contract.create', 'employment-contract.submit', 'employment-contract.approve', 'employment-contract.activate']);
        $this->actingAs($user)->post(route('admin.employees.contracts.store', $employee), [
            'company_id' => $company->id, 'contract_number' => 'SC-C-001', 'type' => 'fixed_term',
            'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'base_salary' => '25000000', 'currency' => 'VND',
        ])->assertRedirect();
        $contract = EmploymentContract::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->actingAs($user)->post(route('admin.employment-contracts.activate', $contract), ['company_id' => $company->id])->assertSessionHasErrors('status');
        $this->actingAs($user)->post(route('admin.employment-contracts.submit', $contract), ['company_id' => $company->id])->assertRedirect();
        $this->actingAs($user)->post(route('admin.employment-contracts.approve', $contract), ['company_id' => $company->id])->assertRedirect();
        $this->actingAs($user)->post(route('admin.employment-contracts.activate', $contract), ['company_id' => $company->id])->assertRedirect();
        self::assertSame('active', $contract->refresh()->status->value);
    }

    public function test_consent_withdrawal_creates_immutable_decision(): void
    {
        [$company, , , $employee] = $this->organization('K');
        $user = $this->authorized($company, ['data-consent.grant', 'data-consent.withdraw']);
        $this->actingAs($user)->post(route('admin.employees.consents.grant', $employee), [
            'company_id' => $company->id, 'purpose' => 'Xử lý hồ sơ nhân sự', 'policy_version' => '2026.1',
        ])->assertRedirect();
        $granted = DataConsent::query()->firstOrFail();
        $this->actingAs($user)->post(route('admin.data-consents.withdraw', $granted), ['company_id' => $company->id, 'reason' => 'Nhân viên rút đồng ý'])->assertRedirect();
        self::assertSame(2, DataConsent::query()->where('employee_id', $employee->id)->count());
        self::assertTrue((bool) $granted->refresh()->granted);
        self::assertFalse((bool) DataConsent::query()->latest('id')->firstOrFail()->granted);
    }

    public function test_user_link_is_unique_and_unlink_does_not_delete_user(): void
    {
        [$company, , , $employee] = $this->organization('U');
        $operator = $this->authorized($company, ['employee-user.link', 'employee-user.unlink']);
        $account = $this->authorized($company, []);

        $this->actingAs($operator)->post(route('admin.employees.user-link.store', $employee), ['company_id' => $company->id, 'user_id' => $account->id])->assertRedirect();
        self::assertSame($account->id, $employee->refresh()->user_id);
        $this->actingAs($operator)->delete(route('admin.employees.user-link.destroy', $employee), ['company_id' => $company->id])->assertRedirect();
        self::assertNull($employee->refresh()->user_id);
        $this->assertDatabaseHas('users', ['id' => $account->id]);
    }

    public function test_invitation_stores_only_hash_and_dispatches_notification(): void
    {
        Notification::fake();
        [$company, , , $employee] = $this->organization('I');
        $operator = $this->authorized($company, ['employee-user.invite']);
        $this->actingAs($operator)->post(route('admin.employees.user.invite', $employee), ['company_id' => $company->id, 'email' => 'invitee@schema.demo'])->assertRedirect();
        $invitation = DB::table('employee_user_invitations')->where('employee_id', $employee->id)->first();
        self::assertSame(64, strlen((string) $invitation->token_hash));
        self::assertObjectNotHasProperty('token', $invitation);
        Notification::assertSentOnDemand(EmployeeUserInvitationNotification::class);
    }

    /** @return array{Company, OrganizationUnit, JobPosition, Employee} */
    private function organization(string $suffix): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'EC-'.$suffix, 'name' => 'Schema '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'type' => 'department', 'code' => 'UNIT-'.$suffix, 'name' => 'Phòng '.$suffix, 'headcount_limit' => 10, 'effective_from' => '2026-01-01']);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'POS-'.$suffix, 'title' => 'Chuyên viên '.$suffix, 'approved_headcount' => 10, 'budgeted_cost' => 0]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'EMP-'.$suffix, 'full_name' => 'Nhân viên '.$suffix, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $unit, $position, $employee];
    }

    private function authorized(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function assignmentPayload(Company $company, OrganizationUnit $unit, JobPosition $position, int $allocation, string $type = 'primary'): array
    {
        return ['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $position->id, 'assignment_type' => $type, 'allocation_percent' => $allocation, 'effective_from' => '2026-01-01'];
    }
}
