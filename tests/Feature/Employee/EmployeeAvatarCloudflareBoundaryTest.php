<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Support\Secrets\ResolvedSecret;
use App\Support\Secrets\SecretProviderInterface;
use App\Support\Secrets\SecretReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\DeleteEmployee\DeleteEmployeeCommand;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeAvatarService;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;
use Modules\Employee\Infrastructure\Media\CloudflareImagesEmployeeAvatarStorage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class EmployeeAvatarCloudflareBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_replace_and_remove_use_owned_external_reference_safely(): void
    {
        $storage = new class implements EmployeeAvatarStorageInterface
        {
            /** @var list<string> */
            public array $deleted = [];

            private int $sequence = 0;

            public function upload(UploadedFile $file): StoredEmployeeAvatar
            {
                $this->sequence++;

                return new StoredEmployeeAvatar('cloudflare-image-'.$this->sequence, 'https://imagedelivery.net/demo/avatar-'.$this->sequence.'/public');
            }

            public function delete(string $reference): void
            {
                $this->deleted[] = $reference;
            }
        };
        $service = new EmployeeAvatarService($storage);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AVATAR', 'name' => 'Avatar Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AVATAR-1', 'full_name' => 'Avatar Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        $service->upload($company->id, $employee->id, UploadedFile::fake()->image('first.png', 120, 120));
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'avatar_storage_key' => 'cloudflare-image-1']);
        $service->upload($company->id, $employee->id, UploadedFile::fake()->image('second.webp', 120, 120));
        self::assertSame(['cloudflare-image-1'], $storage->deleted);
        $service->remove($company->id, $employee->id);
        self::assertSame(['cloudflare-image-1', 'cloudflare-image-2'], $storage->deleted);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'avatar_storage_key' => null, 'avatar_url' => null]);
    }

    public function test_cloudflare_adapter_uses_governed_secret_and_expected_api_contract(): void
    {
        config(['services.cloudflare_images.account_id' => 'account-for-test']);
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-for-test/images/v1' => Http::response([
                'success' => true,
                'result' => ['id' => 'image-for-test', 'variants' => ['https://imagedelivery.net/demo/image-for-test/public']],
            ]),
            'https://api.cloudflare.com/client/v4/accounts/account-for-test/images/v1/image-for-test' => Http::response(['success' => true]),
        ]);
        $provider = new class implements SecretProviderInterface
        {
            public function resolve(SecretReference $reference): ?ResolvedSecret
            {
                return $reference->value === 'secret://media/cloudflare-images' ? new ResolvedSecret('token-for-test') : null;
            }
        };
        $storage = new CloudflareImagesEmployeeAvatarStorage(app(Factory::class), $provider);

        $stored = $storage->upload(UploadedFile::fake()->image('avatar.png', 120, 120));
        $storage->delete($stored->reference);

        self::assertSame('image-for-test', $stored->reference);
        self::assertSame('https://imagedelivery.net/demo/image-for-test/public', $stored->deliveryUrl);
        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-for-test'));
    }

    public function test_failed_database_persistence_compensates_new_cloudflare_asset(): void
    {
        $storage = new class implements EmployeeAvatarStorageInterface
        {
            /** @var list<string> */
            public array $deleted = [];

            public function upload(UploadedFile $file): StoredEmployeeAvatar
            {
                return new StoredEmployeeAvatar(str_repeat('x', 300), null);
            }

            public function delete(string $reference): void
            {
                $this->deleted[] = $reference;
            }
        };
        $service = new EmployeeAvatarService($storage);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AVATAR-DB', 'name' => 'Avatar DB Failure', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'AVATAR-DB-1', 'full_name' => 'Avatar DB Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        try {
            $service->upload($company->id, $employee->id, UploadedFile::fake()->image('avatar.png', 120, 120));
            self::fail('Expected persistence failure.');
        } catch (\Throwable) {
            self::assertSame([str_repeat('x', 300)], $storage->deleted);
            $this->assertDatabaseHas('employees', ['id' => $employee->id, 'avatar_storage_key' => null]);
        }
    }

    public function test_cloudflare_api_failure_and_invalid_files_fail_without_secret_disclosure(): void
    {
        config(['services.cloudflare_images.account_id' => 'account-for-test']);
        Http::fake(['https://api.cloudflare.com/*' => Http::response(['success' => false], 503)]);
        $provider = new class implements SecretProviderInterface
        {
            public function resolve(SecretReference $reference): ResolvedSecret
            {
                return new ResolvedSecret('never-expose-this-token');
            }
        };
        $storage = new CloudflareImagesEmployeeAvatarStorage(app(Factory::class), $provider);
        try {
            $storage->upload(UploadedFile::fake()->image('avatar.png', 120, 120));
            self::fail('Expected Cloudflare API failure.');
        } catch (\Throwable $exception) {
            self::assertStringNotContainsString('never-expose-this-token', $exception->getMessage());
        }

        $service = new EmployeeAvatarService($storage);
        foreach ([UploadedFile::fake()->createWithContent('avatar.txt', 'not-an-image'), UploadedFile::fake()->image('large.png', 120, 120)->size(6000)] as $invalid) {
            try {
                $service->stage($invalid);
                self::fail('Expected avatar validation failure.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('avatar', $exception->errors());
            }
        }
        Http::assertSentCount(1);
    }

    public function test_shared_reference_and_missing_avatar_never_delete_another_owned_asset(): void
    {
        $storage = new class implements EmployeeAvatarStorageInterface
        {
            /** @var list<string> */
            public array $deleted = [];

            public function upload(UploadedFile $file): StoredEmployeeAvatar
            {
                return new StoredEmployeeAvatar('unused', null);
            }

            public function delete(string $reference): void
            {
                $this->deleted[] = $reference;
            }
        };
        $service = new EmployeeAvatarService($storage);
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'SHARED', 'name' => 'Shared Avatar', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $first = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'SHARED-1', 'full_name' => 'Shared One', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'avatar_storage_key' => 'shared-image']);
        Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'SHARED-2', 'full_name' => 'Shared Two', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'avatar_storage_key' => 'shared-image']);
        $withoutAvatar = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'NO-AVATAR', 'full_name' => 'No Avatar', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        $service->remove((int) $company->id, (int) $first->id);
        $service->remove((int) $company->id, (int) $withoutAvatar->id);

        self::assertSame([], $storage->deleted);
        $this->assertDatabaseHas('employees', ['id' => $first->id, 'avatar_storage_key' => null]);
        $this->assertDatabaseHas('employees', ['employee_code' => 'SHARED-2', 'avatar_storage_key' => 'shared-image']);
    }

    public function test_provider_delete_failure_does_not_rollback_owned_reference_removal_or_leak_secret(): void
    {
        $storage = new class implements EmployeeAvatarStorageInterface
        {
            public function upload(UploadedFile $file): StoredEmployeeAvatar
            {
                return new StoredEmployeeAvatar('unused', null);
            }

            public function delete(string $reference): void
            {
                throw new \RuntimeException('provider failed with never-expose-secret');
            }
        };
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'DELETE-FAIL', 'name' => 'Delete Failure', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'DELETE-FAIL-1', 'full_name' => 'Delete Failure', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'avatar_storage_key' => 'owned-image']);

        (new EmployeeAvatarService($storage))->remove((int) $company->id, (int) $employee->id);

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'avatar_storage_key' => null]);
    }

    public function test_soft_archive_retains_avatar_and_private_delivery_uses_the_same_employee_owned_reference(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('employees/avatars/owned.png', 'image-bytes');
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ARCHIVE', 'name' => 'Archive Avatar', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'ARCHIVE-1', 'full_name' => 'Archive Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'avatar_storage_key' => 'employees/avatars/owned.png']);
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->givePermissionTo(Permission::findOrCreate('employee.view', 'web'));
        \DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id]);

        $response = $this->actingAs($actor)->get(route('admin.employees.avatar.show', ['employee' => $employee->id, 'company_id' => $company->id]))->assertOk();
        self::assertSame('image-bytes', $response->streamedContent());
        app(CommandBusInterface::class)->dispatch(new DeleteEmployeeCommand((int) $company->id, (int) $employee->id, (int) $actor->id));

        $archived = Employee::withTrashed()->findOrFail($employee->id);
        self::assertNotNull($archived->deleted_at);
        self::assertSame('employees/avatars/owned.png', $archived->avatar_storage_key);
        Storage::disk('local')->assertExists('employees/avatars/owned.png');
    }
}
