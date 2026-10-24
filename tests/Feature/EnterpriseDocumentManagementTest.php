<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Document\Application\Services\DocumentMalwareScannerInterface;
use Modules\Document\Application\Services\DocumentScanStatus;
use Tests\TestCase;

final class EnterpriseDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('document.storage_disk', 'local');
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_document_versions_are_private_immutable_and_company_scoped(): void
    {
        $company = $this->company('DMS-A');
        $foreign = $this->company('DMS-B');
        $employee = $this->employee($company, 'DMS-E001');
        $user = $this->actor($company);

        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Hồ sơ định danh có kiểm soát',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nversion-one"),
        ])->assertRedirect();

        $document = DB::table('enterprise_documents')->where('subject_id', $employee)->first();
        self::assertNotNull($document);
        $first = DB::table('enterprise_document_versions')->where('document_id', $document->id)->first();
        self::assertNotNull($first);
        self::assertSame(64, strlen((string) $first->sha256));
        self::assertStringNotContainsString('http', (string) $first->storage_path);

        $this->actingAs($user)->post(route('admin.documents.versions.store', ['document' => $document->id]), [
            'company_id' => $company,
            'document' => UploadedFile::fake()->createWithContent('identity-v2.pdf', "%PDF-1.4\nversion-two"),
        ])->assertRedirect();

        self::assertSame(2, DB::table('enterprise_document_versions')->where('document_id', $document->id)->count());
        self::assertSame($first->sha256, DB::table('enterprise_document_versions')->find($first->id)->sha256);
        self::assertNotSame((int) $first->id, (int) DB::table('enterprise_documents')->find($document->id)->current_version_id);

        $this->actingAs($user)->get(route('admin.documents.download', ['version' => $first->id, 'company_id' => $company]))->assertOk();
        $unauthorized = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $unauthorized->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($unauthorized)->get(route('admin.documents.download', ['version' => $first->id, 'company_id' => $company]))->assertForbidden();
        DB::table('company_user_access')->insert(['company_id' => $foreign, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('admin.documents.show', ['document' => $document->id, 'company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.documents.download', ['version' => $first->id, 'company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Foreign link',
            'classification' => 'INTERNAL',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $this->employee($foreign, 'DMS-E002'),
            'document' => UploadedFile::fake()->createWithContent('foreign.pdf', '%PDF-1.4'),
        ])->assertNotFound();

        $this->actingAs($user)->post(route('admin.documents.archive', ['document' => $document->id]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($user)->post(route('admin.documents.versions.store', ['document' => $document->id]), [
            'company_id' => $company,
            'document' => UploadedFile::fake()->createWithContent('blocked.pdf', '%PDF-1.4'),
        ])->assertStatus(409);
    }

    public function test_supabase_s3_adapter_uses_private_tenant_paths_and_temporary_download_urls(): void
    {
        Storage::fake('supabase');
        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'test-access-key');
        config()->set('filesystems.disks.supabase.secret', 'test-secret-key');
        config()->set('filesystems.disks.supabase.bucket', 'private-documents');
        config()->set('filesystems.disks.supabase.endpoint', 'https://project-ref.supabase.co/storage/v1/s3');
        Storage::disk('supabase')->buildTemporaryUrlsUsing(fn (string $path): string => 'https://signed.test/download?object='.rawurlencode($path).'&expires=300');

        $company = $this->company('DMS-SUPABASE');
        $employee = $this->employee($company, 'DMS-S001');
        $user = $this->actor($company);
        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Private Supabase evidence',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertRedirect();

        $version = DB::table('enterprise_document_versions')->firstOrFail();
        self::assertSame('supabase', $version->storage_driver);
        self::assertStringStartsWith("enterprise-documents/{$company}/", (string) $version->storage_path);
        self::assertStringNotContainsString('identity.pdf', (string) $version->storage_path);
        Storage::disk('supabase')->assertExists((string) $version->storage_path);
        $this->actingAs($user)->get(route('admin.documents.download', ['version' => $version->id, 'company_id' => $company]))
            ->assertRedirectContains('https://signed.test/download');
    }

    public function test_document_endpoints_reject_guests_forged_tenants_and_missing_objects_before_signed_url_generation(): void
    {
        Storage::fake('supabase');
        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'test-access-key');
        config()->set('filesystems.disks.supabase.secret', 'test-secret-key');
        config()->set('filesystems.disks.supabase.bucket', 'private-documents');
        config()->set('filesystems.disks.supabase.endpoint', 'https://project-ref.supabase.co/storage/v1/s3');
        $signedUrlCalls = 0;
        Storage::disk('supabase')->buildTemporaryUrlsUsing(function (string $path) use (&$signedUrlCalls): string {
            $signedUrlCalls++;

            return 'https://signed.test/download?object='.rawurlencode($path);
        });

        $company = $this->company('DMS-BOUNDARY-A');
        $foreign = $this->company('DMS-BOUNDARY-B');
        $employee = $this->employee($company, 'DMS-BOUNDARY-E001');
        $user = $this->actor($company);
        DB::table('company_user_access')->insert(['company_id' => $foreign, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Authorization boundary',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertRedirect();
        $document = DB::table('enterprise_documents')->where('company_id', $company)->firstOrFail();
        $version = DB::table('enterprise_document_versions')->where('document_id', $document->id)->firstOrFail();

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.documents.download', ['version' => $version->id, 'company_id' => $company]))->assertRedirect(route('login'));
        self::assertSame(0, $signedUrlCalls);

        $this->actingAs($user)->get(route('admin.documents.download', ['version' => $version->id, 'company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->post(route('admin.documents.versions.store', ['document' => $document->id]), [
            'company_id' => $foreign,
            'document' => UploadedFile::fake()->createWithContent('forged.pdf', '%PDF-1.4'),
        ])->assertNotFound();
        $this->actingAs($user)->post(route('admin.documents.archive', ['document' => $document->id]), ['company_id' => $foreign])->assertNotFound();
        self::assertSame(0, $signedUrlCalls);

        Storage::disk('supabase')->delete((string) $version->storage_path);
        $this->actingAs($user)->get(route('admin.documents.download', ['version' => $version->id, 'company_id' => $company]))->assertNotFound();
        self::assertSame(0, $signedUrlCalls);
        self::assertSame('ACTIVE', DB::table('enterprise_documents')->where('id', $document->id)->value('status'));
        self::assertSame(1, DB::table('enterprise_document_versions')->where('document_id', $document->id)->count());
    }

    public function test_document_upload_rejects_invalid_signature_oversize_and_unconfigured_provider_without_partial_rows(): void
    {
        $company = $this->company('DMS-SECURITY');
        $employee = $this->employee($company, 'DMS-S002');
        $user = $this->actor($company);
        $base = ['company_id' => $company, 'document_type' => 'IDENTITY_DOCUMENT', 'title' => 'Rejected evidence', 'classification' => 'RESTRICTED', 'owner_module' => 'Employee', 'subject_type' => 'EMPLOYEE', 'subject_id' => $employee];

        $this->actingAs($user)->post(route('admin.documents.store'), $base + ['document' => UploadedFile::fake()->createWithContent('forged.pdf', 'MZ executable payload')])->assertStatus(422);
        $this->actingAs($user)->post(route('admin.documents.store'), $base + ['document' => UploadedFile::fake()->create('oversized.pdf', 10_241, 'application/pdf')])->assertSessionHasErrors('document');
        self::assertSame(0, DB::table('enterprise_documents')->where('company_id', $company)->count());

        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', null);
        try {
            $this->actingAs($user)->post(route('admin.documents.store'), $base + ['document' => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\nvalid")]);
        } catch (\RuntimeException $exception) {
            self::assertSame('Supabase Storage chưa được cấu hình đầy đủ.', $exception->getMessage());
        }
        self::assertSame(0, DB::table('enterprise_documents')->where('company_id', $company)->count());
    }

    public function test_supabase_rejects_mime_outside_verified_bucket_allowlist_without_uploading(): void
    {
        Storage::fake('supabase');
        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'test-access-key');
        config()->set('filesystems.disks.supabase.secret', 'test-secret-key');
        config()->set('filesystems.disks.supabase.bucket', 'private-documents');
        config()->set('filesystems.disks.supabase.endpoint', 'https://project-ref.supabase.co/storage/v1/s3');
        $company = $this->company('DMS-MIME');
        $employee = $this->employee($company, 'DMS-MIME-001');
        $user = $this->actor($company);

        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Unsupported Supabase MIME',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('evidence.txt', 'plain text'),
        ])->assertStatus(422);

        self::assertSame(0, DB::table('enterprise_documents')->where('company_id', $company)->count());
        self::assertSame([], Storage::disk('supabase')->allFiles());
    }

    public function test_document_upload_fails_closed_when_malware_scanner_is_unavailable(): void
    {
        $this->app->instance(DocumentMalwareScannerInterface::class, new class implements DocumentMalwareScannerInterface
        {
            public function scan(string $absolutePath): DocumentScanStatus
            {
                return DocumentScanStatus::Failed;
            }
        });
        $company = $this->company('DMS-SCAN');
        $employee = $this->employee($company, 'DMS-SCAN-001');
        $user = $this->actor($company);

        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Scanner unavailable',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertStatus(503);

        self::assertSame(0, DB::table('enterprise_documents')->where('company_id', $company)->count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_supabase_transport_failure_rolls_back_document_and_attempts_scoped_cleanup(): void
    {
        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'test-access-key');
        config()->set('filesystems.disks.supabase.secret', 'test-secret-key');
        config()->set('filesystems.disks.supabase.bucket', 'private-documents');
        config()->set('filesystems.disks.supabase.endpoint', 'https://project-ref.supabase.co/storage/v1/s3');
        $filesystem = \Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->once()->andReturnFalse();
        $filesystem->shouldReceive('putFileAs')->once()->withArgs(fn (string $directory, UploadedFile $file, string $name, array $options): bool => str_starts_with($directory, 'enterprise-documents/') && str_ends_with($name, '.pdf') && $options === ['ContentType' => 'application/pdf'])->andThrow(new \RuntimeException('Simulated S3 transport failure.'));
        $filesystem->shouldReceive('deleteDirectory')->once()->withArgs(fn (string $path): bool => str_starts_with($path, 'enterprise-documents/'))->andThrow(new \RuntimeException('Cleanup access denied with private key.'));
        Storage::set('supabase', $filesystem);
        $company = $this->company('DMS-TRANSPORT');
        $employee = $this->employee($company, 'DMS-TRANSPORT-001');
        $user = $this->actor($company);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->post(route('admin.documents.store'), [
                'company_id' => $company,
                'document_type' => 'IDENTITY_DOCUMENT',
                'title' => 'Transport failure',
                'classification' => 'RESTRICTED',
                'owner_module' => 'Employee',
                'subject_type' => 'EMPLOYEE',
                'subject_id' => $employee,
                'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
            ]);
            self::fail('Expected the simulated S3 transport failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated S3 transport failure.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('enterprise_documents')->where('company_id', $company)->count());
        self::assertSame(0, DB::table('enterprise_document_versions')->count());
        self::assertSame(1, Cache::get('operations:storage:cleanup_directory:supabase:failure'));
    }

    public function test_signed_url_provider_failure_is_reported_without_fallback_or_metadata_mutation(): void
    {
        Storage::fake('supabase');
        config()->set('document.storage_disk', 'supabase');
        config()->set('filesystems.disks.supabase.key', 'test-access-key');
        config()->set('filesystems.disks.supabase.secret', 'test-secret-key');
        config()->set('filesystems.disks.supabase.bucket', 'private-documents');
        config()->set('filesystems.disks.supabase.endpoint', 'https://project-ref.supabase.co/storage/v1/s3');
        $company = $this->company('DMS-SIGNED-FAIL');
        $employee = $this->employee($company, 'DMS-SIGNED-001');
        $user = $this->actor($company);
        $this->actingAs($user)->post(route('admin.documents.store'), [
            'company_id' => $company,
            'document_type' => 'IDENTITY_DOCUMENT',
            'title' => 'Signed provider failure',
            'classification' => 'RESTRICTED',
            'owner_module' => 'Employee',
            'subject_type' => 'EMPLOYEE',
            'subject_id' => $employee,
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\nprivate"),
        ])->assertRedirect();
        $version = DB::table('enterprise_document_versions')->firstOrFail();
        $filesystem = \Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->once()->with((string) $version->storage_path)->andReturnTrue();
        $filesystem->shouldReceive('temporaryUrl')->once()->andThrow(new \RuntimeException('Signed URL provider timeout with private-key-material.'));
        Storage::set('supabase', $filesystem);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->get(route('admin.documents.download', ['version' => $version->id, 'company_id' => $company]));
            self::fail('Expected signed URL provider failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringStartsWith('Signed URL provider timeout', $exception->getMessage());
        }

        self::assertSame(1, DB::table('enterprise_document_versions')->where('id', $version->id)->count());
        self::assertSame(1, Cache::get('operations:storage:signed_url:supabase:failure'));
    }

    private function actor(int $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(['document.view', 'document.create', 'document.upload', 'document.download', 'document.archive']);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(int $company, string $code): int
    {
        return (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => $code, 'full_name' => $code, 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }
}
