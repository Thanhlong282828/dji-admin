<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CartWishlistMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_cart_merges_quantity_uses_current_price_and_enforces_stock_and_ownership(): void
    {
        [$product, $variant] = $this->catalog('cart');
        $buyer = $this->buyer('buyer-cart@example.test');
        $other = $this->buyer('buyer-other@example.test');

        $this->actingAs($buyer)->post(route('cart.items.add'), ['variant_id' => $variant, 'quantity' => 2])->assertRedirect();
        $this->actingAs($buyer)->post(route('cart.items.add'), ['variant_id' => $variant, 'quantity' => 3])->assertRedirect();
        self::assertSame(1, DB::table('cart_items')->count());
        $item = DB::table('cart_items')->first();
        self::assertNotNull($item);
        self::assertSame(5, (int) $item->quantity);

        DB::table('product_variants')->where('id', $variant)->update(['price_minor' => 120000]);
        $this->actingAs($buyer)->get(route('cart.index'))->assertOk()->assertSee('120.000')->assertSee('Giá hiện tại đã thay đổi');
        $this->actingAs($buyer)->patch(route('cart.items.quantity', $item->id), ['quantity' => 11])->assertSessionHasErrors('quantity');
        $this->actingAs($other)->delete(route('cart.items.remove', $item->id))->assertNotFound();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'product_id' => $product]);
    }

    public function test_wishlist_is_idempotent_private_and_can_move_product_to_cart(): void
    {
        [$product] = $this->catalog('wish');
        $buyer = $this->buyer('buyer-wish@example.test');
        $other = $this->buyer('buyer-wish-other@example.test');

        $this->actingAs($buyer)->post(route('wishlist.like', $product))->assertRedirect();
        $this->actingAs($buyer)->post(route('wishlist.like', $product))->assertRedirect();
        self::assertSame(1, DB::table('wishlist_items')->count());
        $this->actingAs($other)->get(route('wishlist.index'))->assertOk()->assertDontSee('Sản phẩm wish');
        $this->actingAs($buyer)->from(route('wishlist.index'))->post(route('wishlist.cart', $product))->assertRedirect(route('wishlist.index'));
        self::assertSame(1, DB::table('cart_items')->count());
        $this->actingAs($buyer)->delete(route('wishlist.unlike', $product))->assertRedirect();
        self::assertSame(0, DB::table('wishlist_items')->count());
    }

    public function test_guests_are_redirected_and_public_catalog_searches_real_data(): void
    {
        $this->catalog('public');
        $this->get(route('cart.index'))->assertRedirect(route('login'));
        $this->get(route('wishlist.index'))->assertRedirect(route('login'));
        $this->get(route('public.products.index', ['q' => 'Sản phẩm public']))->assertOk()->assertSee('Sản phẩm public');
        $this->get(route('public.stores.show', 'store-public'))->assertOk()->assertSee('Sản phẩm public');
    }

    public function test_legacy_direct_inventory_adjustment_route_remains_removed(): void
    {
        [, $variant] = $this->catalog('inventory');

        self::assertFalse(Route::has('admin.seller.inventory.adjust'));
        self::assertTrue(Route::has('admin.seller.inventory.governance'));
        $this->assertDatabaseHas('inventory_balances', ['product_variant_id' => $variant, 'on_hand' => 10, 'reserved' => 2]);
    }

    private function buyer(string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        DB::table('buyer_profiles')->insert(['user_id' => $user->id, 'display_name' => $user->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** @return array{int,int,User} */
    private function catalog(string $slug): array
    {
        $seller = User::factory()->create(['email' => "seller-{$slug}@example.test", 'email_verified_at' => now()]);
        $seller->assignRole('Seller');
        $company = DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CART-'.strtoupper($slug), 'name' => "Company {$slug}", 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $seller->id, 'created_at' => now(), 'updated_at' => now()]);
        $profile = DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $seller->id, 'display_name' => "Seller {$slug}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => "Store {$slug}", 'slug' => "store-{$slug}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('product_categories')->insertGetId(['name' => "Danh mục {$slug}", 'slug' => "category-{$slug}", 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['store_id' => $store, 'product_category_id' => $category, 'code' => "P-{$slug}", 'name' => "Sản phẩm {$slug}", 'slug' => "product-{$slug}", 'base_price_minor' => 100000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => "SKU-{$slug}", 'name' => 'Mặc định', 'price_minor' => 100000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'code' => 'MAIN', 'name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 2, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [(int) $product, (int) $variant, $seller];
    }
}
