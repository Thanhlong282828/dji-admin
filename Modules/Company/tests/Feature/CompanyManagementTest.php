<?php

namespace Modules\Company\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorization_index_search_filter_and_pagination(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/companies')->assertForbidden();
        $user = $this->authorized('company.view');
        $this->company(['code' => 'ACTIVE-ONE', 'name' => 'Schema Active', 'status' => 'active']);
        $this->company(['code' => 'INACTIVE-ONE', 'name' => 'Schema Inactive', 'status' => 'inactive']);
        $this->actingAs($user)->get('/admin/companies?search=Active&status=active&per_page=5')->assertOk()->assertSee('ACTIVE-ONE')->assertDontSee('INACTIVE-ONE');
    }

    public function test_create_validates_unique_fields_and_dispatches_event(): void
    {
        Event::fake([CompanyCreated::class]);
        $user = $this->authorized('company.create');
        $response = $this->actingAs($user)->post('/admin/companies', $this->payload());
        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('companies', ['code' => 'SCHEMA-HOLDING', 'created_by' => $user->id]);
        Event::assertDispatched(CompanyCreated::class);
        $this->actingAs($user)->from('/admin/companies/create')->post('/admin/companies', $this->payload())->assertSessionHasErrors(['code', 'tax_code']);
    }

    public function test_company_list_shows_only_seller_accounts_linked_to_each_company(): void
    {
        $viewer = $this->authorized('company.view');
        $company = $this->company(['code' => 'SELLER-COMPANY', 'name' => 'Seller Company']);
        $other = $this->company(['code' => 'OTHER-COMPANY', 'name' => 'Other Company']);
        $seller = User::factory()->create(['name' => 'Seller Account Owner', 'email' => 'seller-company@example.test']);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $seller->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('seller_profiles')->insert(['company_id' => $company->id, 'user_id' => $seller->id, 'display_name' => 'Seller Company Profile', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($viewer)->get('/admin/companies?per_page=15');
        $response->assertOk()->assertSee('Seller Company Profile')->assertSee('seller-company@example.test')->assertSee('Other Company')->assertSee('Chưa gắn Seller');
        self::assertNotSame($company->id, $other->id);
    }

    public function test_update_status_soft_delete_and_restore(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['company.update', 'company.change-status', 'company.delete', 'company.restore', 'company.view']);
        $company = $this->company();
        $this->actingAs($user)->put("/admin/companies/{$company->id}", $this->payload(['name' => 'Schema Holding Updated']))->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Schema Holding Updated']);
        $this->actingAs($user)->patch("/admin/companies/{$company->id}/status", ['status' => 'inactive'])->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status' => 'inactive']);
        $this->actingAs($user)->delete("/admin/companies/{$company->id}")->assertRedirect();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->actingAs($user)->post("/admin/companies/{$company->id}/restore")->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'deleted_at' => null]);
    }

    public function test_company_create_and_update_branches_are_atomic_and_do_not_blind_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole('System Admin');
        $user->givePermissionTo(['company.create', 'company.update', 'company.view']);
        $payload = $this->payload(['branches' => [['code' => 'HCM', 'name' => 'TP.HCM', 'address_line' => '123 Nguyễn Văn Cừ', 'city' => 'TP.HCM', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh']]]);
        $this->actingAs($user)->post('/admin/companies', $payload)->assertRedirect();
        $company = Company::query()->where('code', 'SCHEMA-HOLDING')->firstOrFail();
        $branchId = DB::table('branches')->where('company_id', $company->id)->where('code', 'HCM')->value('id');
        self::assertNotNull($branchId);
        $this->assertDatabaseHas('branch_user_access', ['branch_id' => $branchId, 'user_id' => $user->id]);

        $this->actingAs($user)->put("/admin/companies/{$company->id}", $this->payload(['branches' => [['id' => $branchId, 'code' => 'HCM', 'name' => 'Hồ Chí Minh HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh'], ['code' => 'HN', 'name' => 'Hà Nội', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh']]]))->assertRedirect();
        $this->assertDatabaseHas('branches', ['id' => $branchId, 'name' => 'Hồ Chí Minh HQ']);
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'HN']);

        $this->actingAs($user)->put("/admin/companies/{$company->id}", $this->payload(['branches' => [['id' => $branchId, 'code' => 'HCM', 'name' => 'Hồ Chí Minh HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh']]]))->assertRedirect();
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'HN']);
    }

    public function test_company_create_and_edit_manage_canonical_seller_account_in_one_transaction(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(['company.create', 'company.update', 'company.view', 'commerce.seller.governance.manage', 'commerce.seller.governance.view']);
        $seller = ['display_name' => 'Seller Công ty Thực', 'account_name' => 'Nguyễn Chủ Shop', 'email' => 'seller-owned@example.test', 'phone_e164' => '+84901111222', 'password' => 'SellerSecure123!', 'password_confirmation' => 'SellerSecure123!'];

        $this->actingAs($actor)->post('/admin/companies', $this->payload(['seller_accounts' => [$seller]]))->assertRedirect();
        $company = Company::query()->where('code', 'SCHEMA-HOLDING')->firstOrFail();
        $owner = User::query()->where('email', 'seller-owned@example.test')->firstOrFail();
        $this->assertTrue($owner->hasRole('Seller'));
        $this->assertDatabaseHas('company_user_access', ['company_id' => $company->id, 'user_id' => $owner->id]);
        $profileId = DB::table('seller_profiles')->where(['company_id' => $company->id, 'user_id' => $owner->id])->value('id');
        self::assertIsInt($profileId);
        $this->assertDatabaseHas('seller_profiles', ['id' => $profileId, 'display_name' => 'Seller Công ty Thực', 'status' => 'pending']);

        $seller['id'] = $profileId;
        $seller['display_name'] = 'Seller Công ty Đã Sửa';
        $seller['account_name'] = 'Nguyễn Chủ Shop Mới';
        $seller['password'] = '';
        $seller['password_confirmation'] = '';
        $this->actingAs($actor)->put("/admin/companies/{$company->id}", $this->payload(['seller_accounts' => [$seller]]))->assertRedirect();
        $this->assertDatabaseHas('seller_profiles', ['id' => $profileId, 'display_name' => 'Seller Công ty Đã Sửa', 'status' => 'pending']);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'name' => 'Nguyễn Chủ Shop Mới']);
        $this->actingAs($actor)->get("/admin/companies/{$company->id}")->assertOk()->assertSee('Seller Công ty Đã Sửa')->assertSee('Mở Seller Governance');
    }

    public function test_company_form_cannot_bypass_seller_governance_permission_and_invalid_seller_rolls_back_company(): void
    {
        $actor = $this->authorized('company.create');
        $seller = ['display_name' => 'Forbidden Seller', 'account_name' => 'Forbidden', 'email' => 'forbidden@example.test', 'password' => 'SellerSecure123!', 'password_confirmation' => 'SellerSecure123!'];
        $this->actingAs($actor)->post('/admin/companies', $this->payload(['seller_accounts' => [$seller]]))->assertForbidden();
        $this->assertDatabaseMissing('companies', ['code' => 'SCHEMA-HOLDING']);

        $actor->givePermissionTo('commerce.seller.governance.manage');
        User::factory()->create(['email' => 'forbidden@example.test']);
        $this->actingAs($actor)->post('/admin/companies', $this->payload(['seller_accounts' => [$seller]]))->assertSessionHasErrors('seller_accounts.0.email');
        $this->assertDatabaseMissing('companies', ['code' => 'SCHEMA-HOLDING']);
    }

    public function test_empty_seller_can_be_removed_but_commercial_history_blocks_deletion(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(['company.update', 'company.view', 'commerce.seller.governance.manage']);
        $company = $this->company();
        $owner = User::factory()->create();
        $owner->assignRole('Seller');
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $profile = DB::table('seller_profiles')->insertGetId(['company_id' => $company->id, 'user_id' => $owner->id, 'display_name' => 'Empty Seller', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($actor)->delete("/admin/companies/{$company->id}/seller-accounts/{$profile}")->assertRedirect();
        $this->assertDatabaseMissing('seller_profiles', ['id' => $profile]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $refreshedOwner = $owner->fresh();
        self::assertInstanceOf(User::class, $refreshedOwner);
        $this->assertFalse($refreshedOwner->hasRole('Seller'));

        $owner->assignRole('Seller');
        $profile = DB::table('seller_profiles')->insertGetId(['company_id' => $company->id, 'user_id' => $owner->id, 'display_name' => 'Trading Seller', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stores')->insert(['seller_profile_id' => $profile, 'name' => 'Store có lịch sử', 'slug' => 'store-co-lich-su', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($actor)->delete("/admin/companies/{$company->id}/seller-accounts/{$profile}")->assertSessionHasErrors('seller_account');
        $this->assertDatabaseHas('seller_profiles', ['id' => $profile]);
    }

    public function test_empty_branch_is_soft_deleted_but_operating_branch_is_protected(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('Super Admin');
        $company = $this->company();
        $empty = DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'EMPTY', 'name' => 'Empty Branch', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($actor)->delete("/admin/branches/{$empty}?company_id={$company->id}")->assertRedirect();
        $this->assertDatabaseHas('branches', ['id' => $empty, 'status' => 'archived']);
        self::assertNotNull(DB::table('branches')->where('id', $empty)->value('deleted_at'));

        $used = DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'USED', 'name' => 'Used Branch', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_management_revenues')->insert(['company_id' => $company->id, 'branch_id' => $used, 'reference' => 'TEST-BRANCH-REVENUE', 'source_type' => 'management_ledger', 'amount_minor' => 1000, 'currency_code' => 'VND', 'recognized_on' => now()->toDateString(), 'status' => 'recognized', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($actor)->delete("/admin/branches/{$used}?company_id={$company->id}")->assertSessionHasErrors('branch');
        $this->assertDatabaseHas('branches', ['id' => $used, 'deleted_at' => null]);
    }

    private function authorized(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [...['code' => 'SCHEMA-HOLDING', 'name' => 'Schema Enterprise Holding', 'short_name' => 'Schema Holding', 'tax_code' => 'DEMO-0319999999', 'email' => 'holding@schema.demo', 'phone' => '+84 28 7300 1999', 'website' => 'https://example.com/schema-holding', 'address' => 'Thành phố Hồ Chí Minh', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'legal_representative' => 'Phạm Minh Anh', 'status' => 'active', 'description' => 'Dữ liệu kiểm thử Company CQRS.'], ...$override];
    }

    /** @param array<string,mixed> $override */
    private function company(array $override = []): Company
    {
        $codeValue = $override['code'] ?? 'SCHEMA-HOLDING';
        $code = is_scalar($codeValue) ? (string) $codeValue : 'SCHEMA-HOLDING';

        return Company::query()->create([...$this->payload(), 'tax_code' => 'DEMO-'.hash('crc32b', $code), 'currency' => 'VND', 'is_active' => ($override['status'] ?? 'active') === 'active', ...$override]);
    }
}
