<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\MediaStorageInterface;
use App\Support\Media\StoredMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class CommerceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_crud_is_company_scoped_and_navigation_permission_matches_route(): void
    {
        [$actor, $company] = $this->actor('branch.view', 'branch.create', 'branch.update');
        $actor->assignRole(Role::findOrCreate('System Admin', 'web'));
        $foreign = $this->company('FOREIGN');

        $this->actingAs($actor)->post(route('admin.branches.store'), ['company_id' => $company->id, 'code' => 'HCM', 'name' => 'TP.HCM', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh'])->assertRedirect();
        $branch = $this->databaseId(DB::table('branches')->where('company_id', $company->id)->value('id'));
        $this->actingAs($actor)->get(route('admin.branches.index', ['company_id' => $company->id]))->assertOk()->assertSee('TP.HCM');
        $this->actingAs($actor)->put(route('admin.branches.update', $branch), ['company_id' => $company->id, 'code' => 'HCM', 'name' => 'Chi nhánh Hồ Chí Minh', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh'])->assertRedirect();
        $this->actingAs($actor)->get(route('admin.branches.index', ['company_id' => $foreign->id]))->assertNotFound();
    }

    public function test_branch_mutation_requires_platform_admin_even_with_crafted_permission(): void
    {
        [$actor, $company] = $this->actor('branch.view', 'branch.create', 'branch.update');
        $actor->assignRole(Role::findOrCreate('HR Manager', 'web'));

        $this->actingAs($actor)->post(route('admin.branches.store'), ['company_id' => $company->id, 'code' => 'HN', 'name' => 'Hà Nội', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh'])->assertForbidden();
        $this->assertDatabaseMissing('branches', ['company_id' => $company->id, 'code' => 'HN']);
    }

    public function test_branch_leader_is_canonical_employee_and_receives_access(): void
    {
        [$actor, $company] = $this->actor('branch.view', 'branch.create', 'branch.update');
        $actor->assignRole(Role::findOrCreate('System Admin', 'web'));
        $leaderUser = User::factory()->create(['access_enabled' => true, 'email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $leaderUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $leaderUser->id, 'employee_code' => 'LEADER-1', 'full_name' => 'Lãnh đạo Chi nhánh', 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'LEAD-U', 'name' => 'Ban điều hành', 'type' => 'department', 'effective_from' => today()]);
        $position = JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'LEAD-P', 'title' => 'Branch Manager', 'career_level' => 6, 'approved_headcount' => 1, 'budgeted_cost' => 0]);
        EmployeeAssignment::query()->create(['employee_id' => $employee->id, 'organization_unit_id' => $unit->id, 'job_position_id' => $position->id, 'assignment_type' => 'primary', 'allocation_percent' => 100, 'effective_from' => today()]);

        $this->actingAs($actor)->post(route('admin.branches.store'), ['company_id' => $company->id, 'leader_employee_id' => $employee->id, 'code' => 'LEAD', 'name' => 'Chi nhánh Lãnh đạo', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh'])->assertRedirect();
        $branchId = $this->databaseId(DB::table('branches')->where('code', 'LEAD')->value('id'));
        $this->assertDatabaseHas('branches', ['id' => $branchId, 'leader_employee_id' => $employee->id]);
        $this->assertDatabaseHas('branch_user_access', ['branch_id' => $branchId, 'user_id' => $leaderUser->id]);
        $this->actingAs($actor)->get(route('admin.branches.index', ['company_id' => $company->id]))->assertOk()->assertSee('Lãnh đạo Chi nhánh')->assertSee('Branch Manager');
    }

    public function test_seller_cannot_edit_another_seller_store_and_governance_transition_is_bounded(): void
    {
        [$seller] = $this->actor('commerce.store.view', 'commerce.store.update');
        $other = User::factory()->create(['email_verified_at' => now()]);
        $sellerProfile = (int) DB::table('seller_profiles')->insertGetId(['user_id' => $seller->id, 'display_name' => 'Seller One', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $otherProfile = (int) DB::table('seller_profiles')->insertGetId(['user_id' => $other->id, 'display_name' => 'Seller Two', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stores')->insert(['seller_profile_id' => $otherProfile, 'name' => 'Other Store', 'slug' => 'other-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($seller)->put(route('admin.seller.store.update'), ['name' => 'Own Store', 'description' => 'Owned by current seller'])->assertRedirect();
        $this->assertDatabaseHas('stores', ['seller_profile_id' => $sellerProfile, 'name' => 'Own Store']);
        $this->assertDatabaseHas('stores', ['seller_profile_id' => $otherProfile, 'name' => 'Other Store']);

        [$governor] = $this->actor('commerce.seller.governance.view', 'commerce.seller.governance.manage');
        $pending = (int) DB::table('seller_profiles')->insertGetId(['user_id' => User::factory()->create()->id, 'display_name' => 'Pending', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($governor)->patch(route('admin.commerce.sellers.transition', $pending), ['status' => 'active'])->assertStatus(422);
        $this->actingAs($governor)->patch(route('admin.commerce.sellers.transition', $pending), ['status' => 'verified'])->assertRedirect();
    }

    public function test_article_uploads_cover_and_gallery_through_unified_media_and_public_page(): void
    {
        [$actor, $company] = $this->actor('article.view', 'article.create', 'article.update', 'article.publish');
        $storage = new class implements MediaStorageInterface
        {
            public int $sequence = 0;

            /** @var list<string> */
            public array $deleted = [];

            public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
            {
                $this->sequence++;

                return new StoredMedia('fake', 'asset-'.$this->sequence, 'https://media.test/'.$this->sequence, (string) $file->getMimeType(), (int) $file->getSize(), hash('sha256', (string) $this->sequence));
            }

            public function delete(string $assetId): void
            {
                $this->deleted[] = $assetId;
            }
        };
        $this->app->instance(MediaStorageInterface::class, $storage);
        $this->app->forgetInstance(MediaService::class);
        $payload = ['company_id' => $company->id, 'title' => 'Unified Media Story', 'excerpt' => 'A bounded article media test.', 'body' => str_repeat('Nội dung doanh nghiệp có kiểm soát. ', 5), 'category' => 'Công nghệ', 'status' => 'published', 'published_at' => now()->format('Y-m-d H:i:s'), 'cover_image' => UploadedFile::fake()->image('cover.webp'), 'gallery_images' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')]];

        $this->actingAs($actor)->post(route('articles.store'), $payload)->assertRedirect(route('articles.index', ['company_id' => $company->id]));
        $article = Article::query()->where('title', 'Unified Media Story')->firstOrFail();
        self::assertSame(3, DB::table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id])->count());
        $this->actingAs($actor)->get(route('articles.edit', ['article' => $article, 'company_id' => $company->id]))->assertOk();
        $this->get(route('public.articles.show', $article->slug))->assertOk()->assertSee('Unified Media Story')->assertSee('https://media.test/2');
    }

    public function test_shared_media_reference_is_not_deleted_until_last_owner_is_removed(): void
    {
        $storage = new class implements MediaStorageInterface
        {
            /** @var list<string> */
            public array $deleted = [];

            public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
            {
                return new StoredMedia('fake', 'shared', null, 'image/png', 1, hash('sha256', 'x'));
            }

            public function delete(string $assetId): void
            {
                $this->deleted[] = $assetId;
            }
        };
        $service = new MediaService($storage, app('db'));
        foreach ([1, 2] as $owner) {
            DB::table('media_assets')->insert(['owner_type' => 'store', 'owner_id' => $owner, 'purpose' => 'store_logo', 'provider' => 'fake', 'provider_asset_id' => 'shared', 'mime_type' => 'image/png', 'size_bytes' => 1, 'checksum' => hash('sha256', 'x'), 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $service->removeAsset($this->databaseId(DB::table('media_assets')->where('owner_id', 1)->value('id')));
        self::assertSame([], $storage->deleted);
        $service->removeAsset($this->databaseId(DB::table('media_assets')->where('owner_id', 2)->value('id')));
        self::assertSame(['shared'], $storage->deleted);
    }

    public function test_article_media_accepts_bounded_8k_but_rejects_larger_dimension(): void
    {
        $storage = new class implements MediaStorageInterface
        {
            public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
            {
                return new StoredMedia('fake', '8k', null, (string) $file->getMimeType(), (int) $file->getSize(), hash('sha256', '8k'));
            }

            public function delete(string $assetId): void {}
        };
        $service = new MediaService($storage, app('db'));
        self::assertSame('8k', $service->stage(UploadedFile::fake()->image('article-8k.jpg', 8192, 1), MediaPurpose::ArticleCover)->assetId);

        $this->expectException(ValidationException::class);
        $service->stage(UploadedFile::fake()->image('article-too-wide.jpg', 8193, 1), MediaPurpose::ArticleImage);
    }

    /** @return array{User,Company} */
    private function actor(string ...$permissions): array
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        foreach ($permissions as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $company = $this->company(Str::upper(Str::random(8)));
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$actor, $company];
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function databaseId(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && ctype_digit($value)));

        return is_int($value) ? $value : (int) $value;
    }
}
