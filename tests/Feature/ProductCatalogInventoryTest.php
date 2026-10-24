<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Domain\Services\InventoryService;
use Tests\TestCase;

final class ProductCatalogInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_seller_creates_product_variant_inventory_and_public_page(): void
    {
        [$seller,$store,$branch] = $this->sellerStore('seller-one@example.test', 'seller-one');
        $category = DB::table('product_categories')->insertGetId(['name' => 'Điện tử', 'slug' => 'dien-tu', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $response = $this->actingAs($seller)->post(route('admin.products.store'), ['store_id' => $store, 'branch_id' => $branch, 'product_category_id' => $category, 'code' => 'PHONE-01', 'name' => 'Điện thoại Demo', 'description' => 'Sản phẩm synthetic', 'base_price_minor' => 25000000, 'currency' => 'VND', 'status' => 'active', 'variants' => [['sku' => 'PHONE-01-BLACK', 'name' => 'Đen 256GB', 'attributes' => 'Đen · 256GB', 'price_minor' => 25000000, 'status' => 'active', 'on_hand' => 10, 'reserved' => 3]]]);
        $response->assertRedirect(route('admin.products.index', ['company_id' => DB::table('seller_profiles')->where('user_id', $seller->id)->value('company_id')]));
        $product = DB::table('products')->where('code', 'PHONE-01')->first();
        self::assertNotNull($product);
        $variant = DB::table('product_variants')->where('sku', 'PHONE-01-BLACK')->first();
        self::assertNotNull($variant);
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant->id, 'on_hand' => 10, 'reserved' => 3]);
        $this->get(route('public.products.show', $product->slug))->assertOk()->assertSee('Điện thoại Demo')->assertSee('Còn 7');
    }

    public function test_cross_seller_product_update_is_blocked(): void
    {
        [$owner,$store,$branch] = $this->sellerStore('owner@example.test', 'owner');
        [$other] = $this->sellerStore('other@example.test', 'other');
        $product = DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'OWNED', 'name' => 'Owned', 'slug' => 'owned', 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->put(route('admin.products.update', $product), ['store_id' => $store, 'branch_id' => $branch, 'code' => 'OWNED', 'name' => 'Hijacked', 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'draft', 'variants' => [['sku' => 'OWNED-1', 'name' => 'Default', 'price_minor' => 1000, 'status' => 'active', 'on_hand' => 1, 'reserved' => 0]]])->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => $product, 'name' => 'Owned']);
        self::assertNotSame($owner->id, $other->id);
    }

    public function test_new_product_keeps_attached_cover_and_gallery_after_commit(): void
    {
        Storage::fake('public');
        [$seller, $store, $branch] = $this->sellerStore('seller-media@example.test', 'seller-media');

        $this->actingAs($seller)->post(route('admin.products.store'), [
            'store_id' => $store,
            'branch_id' => $branch,
            'code' => 'MEDIA-01',
            'name' => 'Sản phẩm có media',
            'base_price_minor' => 1000000,
            'currency' => 'VND',
            'status' => 'draft',
            'cover' => UploadedFile::fake()->image('cover.png', 100, 100),
            'gallery' => [UploadedFile::fake()->image('gallery.png', 120, 120)],
            'variants' => [[
                'sku' => 'MEDIA-01-DEFAULT',
                'name' => 'Default',
                'price_minor' => 1000000,
                'status' => 'active',
                'on_hand' => 1,
                'reserved' => 0,
            ]],
        ])->assertRedirect(route('admin.products.index', ['company_id' => DB::table('seller_profiles')->where('user_id', $seller->id)->value('company_id')]));

        $productIdValue = DB::table('products')->where('code', 'MEDIA-01')->value('id');
        self::assertIsNumeric($productIdValue);
        $productId = (int) $productIdValue;
        $companyId = DB::table('seller_profiles')->where('user_id', $seller->id)->value('company_id');
        self::assertIsNumeric($companyId);
        self::assertSame(1, DB::table('media_assets')->where(['company_id' => (int) $companyId, 'owner_type' => 'product', 'owner_id' => $productId, 'purpose' => 'product_cover'])->count());
        self::assertSame(1, DB::table('media_assets')->where(['owner_type' => 'product', 'owner_id' => $productId, 'purpose' => 'product_image'])->count());
        $deliveryUrl = DB::table('media_assets')->where(['owner_type' => 'product', 'owner_id' => $productId, 'purpose' => 'product_cover'])->value('delivery_url');
        self::assertIsString($deliveryUrl);
        self::assertStringStartsWith('/storage/media/product_cover/', $deliveryUrl);
        self::assertStringNotContainsString('localhost', $deliveryUrl);
    }

    public function test_inventory_rejects_reserved_above_on_hand_and_available_is_integer(): void
    {
        $service = app(InventoryService::class);
        self::assertSame(7, $service->available(10, 3));
        $this->expectException(ValidationException::class);
        $service->set(1, 1, 2, 3);
    }

    public function test_one_user_seller_profiles_are_isolated_by_company(): void
    {
        [$seller, $storeA] = $this->sellerStore('multi-company-seller@example.test', 'multi-a');
        $companyAValue = DB::table('seller_profiles')->where('user_id', $seller->id)->value('company_id');
        self::assertIsNumeric($companyAValue);
        $companyA = (int) $companyAValue;
        $companyB = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CMP-MULTI-B', 'name' => 'Company Multi B', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $companyB, 'user_id' => $seller->id, 'created_at' => now(), 'updated_at' => now()]);
        $profileB = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $companyB, 'user_id' => $seller->id, 'display_name' => 'multi-b', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $storeB = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profileB, 'name' => 'Store Multi B', 'slug' => 'multi-b', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->insert([['store_id' => $storeA, 'code' => 'ONLY-A', 'name' => 'Product Company A', 'slug' => 'only-a', 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()], ['store_id' => $storeB, 'code' => 'ONLY-B', 'name' => 'Product Company B', 'slug' => 'only-b', 'base_price_minor' => 2000, 'currency' => 'VND', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]]);

        $this->actingAs($seller)->get(route('admin.products.index', ['company_id' => $companyA]))->assertOk()->assertSee('Product Company A')->assertDontSee('Product Company B');
        $this->actingAs($seller)->get(route('admin.products.index', ['company_id' => $companyB]))->assertOk()->assertSee('Product Company B')->assertDontSee('Product Company A');
    }

    /** @return array{User,int,int} */
    private function sellerStore(string $email, string $slug): array
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->assignRole('Seller');
        $company = DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CMP-'.strtoupper($slug), 'name' => 'Company '.$slug, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'BR-'.strtoupper($slug), 'name' => 'Branch '.$slug, 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_user_access')->insert(['branch_id' => $branch, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $profile = DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $user->id, 'display_name' => $slug, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Store '.$slug, 'slug' => $slug, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return [$user, (int) $store, (int) $branch];
    }
}
