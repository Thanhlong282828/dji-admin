<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EnterpriseDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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
        $this->actingAs($user)->get(route('admin.documents.show', ['document' => $document->id, 'company_id' => $foreign]))->assertNotFound();
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
