<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Commerce\CheckoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CheckoutOrderReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_address_default_is_private_transactional_and_reassigned_after_delete(): void
    {
        $buyer = $this->buyer('address-owner@example.test');
        $other = $this->buyer('address-other@example.test');
        $first = $this->addressPayload('Nhà riêng');
        $this->actingAs($buyer)->post(route('addresses.store'), $first)->assertRedirect();
        $firstId = $this->databaseInt(DB::table('buyer_addresses')->where('user_id', $buyer->id)->value('id'));
        $this->assertDatabaseHas('buyer_addresses', ['id' => $firstId, 'is_default' => true]);
        $this->actingAs($buyer)->post(route('addresses.store'), $this->addressPayload('Công ty') + ['is_default' => true])->assertRedirect();
        $secondId = $this->databaseInt(DB::table('buyer_addresses')->where('user_id', $buyer->id)->where('label', 'Công ty')->value('id'));
        $this->assertDatabaseHas('buyer_addresses', ['id' => $firstId, 'is_default' => false]);
        $this->actingAs($other)->patch(route('addresses.update', $firstId), $this->addressPayload('Sai owner'))->assertNotFound();
        $this->actingAs($buyer)->delete(route('addresses.destroy', $secondId))->assertRedirect();
        $this->assertDatabaseHas('buyer_addresses', ['id' => $firstId, 'is_default' => true]);
    }

    public function test_checkout_splits_by_store_snapshots_price_reserves_stock_is_idempotent_and_preserves_unselected_cart(): void
    {
        $buyer = $this->buyer('checkout@example.test');
        $address = $this->address($buyer);
        [$productA, $variantA] = $this->catalog('alpha', 150000, 4);
        [$productB, $variantB] = $this->catalog('beta', 250000, 6);
        [, $variantC] = $this->catalog('unselected', 99000, 8);
        $cart = $this->cart($buyer);
        $this->cartItem($cart, $productA, $variantA, 2, true, 1);
        $this->cartItem($cart, $productB, $variantB, 1, true, 1);
        $this->cartItem($cart, $this->databaseInt(DB::table('product_variants')->where('id', $variantC)->value('product_id')), $variantC, 1, false, 99000);
        $key = (string) Str::uuid();

        $first = app(CheckoutService::class)->place($buyer, $address, 'standard', $key);
        $second = app(CheckoutService::class)->place($buyer, $address, 'standard', $key);

        self::assertSame($first, $second);
        self::assertCount(2, $first);
        self::assertSame(2, DB::table('orders')->count());
        self::assertSame(2, DB::table('order_items')->count());
        self::assertSame(2, DB::table('inventory_reservations')->where('status', 'reserved')->count());
        self::assertSame(1, DB::table('cart_items')->count());
        $line = DB::table('order_items')->where('product_variant_id', $variantA)->first();
        self::assertNotNull($line);
        self::assertSame(150000, $this->databaseInt($line->unit_price_minor));
        self::assertSame(300000, $this->databaseInt($line->line_total_minor));
        $order = DB::table('orders')->where('id', $line->order_id)->first();
        self::assertNotNull($order);
        self::assertSame(330000, $this->databaseInt($order->total_minor));
        self::assertStringContainsString('Nhà riêng', $this->databaseString($order->shipping_address_snapshot));
    }

    public function test_insufficient_stock_rolls_back_whole_multistore_checkout_and_cart(): void
    {
        $buyer = $this->buyer('rollback@example.test');
        $address = $this->address($buyer);
        [$productA, $variantA] = $this->catalog('rollback-a', 100000, 2);
        [$productB, $variantB] = $this->catalog('rollback-b', 200000, 0);
        $cart = $this->cart($buyer);
        $this->cartItem($cart, $productA, $variantA, 1, true, 100000);
        $this->cartItem($cart, $productB, $variantB, 1, true, 200000);

        try {
            app(CheckoutService::class)->place($buyer, $address, 'standard', (string) Str::uuid());
            self::fail('Checkout thiếu tồn kho phải thất bại.');
        } catch (ValidationException) {
            self::assertSame(0, DB::table('orders')->count());
            self::assertSame(0, DB::table('inventory_reservations')->count());
            self::assertSame(2, DB::table('cart_items')->count());
            self::assertSame(0, $this->databaseInt(DB::table('inventory_balances')->where('product_variant_id', $variantA)->value('reserved')));
        }
    }

    public function test_buyer_cancel_releases_inventory_and_seller_isolation_is_enforced(): void
    {
        $buyer = $this->buyer('cancel@example.test');
        $address = $this->address($buyer);
        [$product, $variant, $seller] = $this->catalog('cancel', 500000, 3);
        [, , $foreignSeller] = $this->catalog('foreign', 600000, 3);
        $cart = $this->cart($buyer);
        $this->cartItem($cart, $product, $variant, 2, true, 500000);
        $orderId = app(CheckoutService::class)->place($buyer, $address, 'express', (string) Str::uuid())[0];

        $this->actingAs($foreignSeller)->get(route('admin.seller.orders.show', $orderId))->assertNotFound();
        $this->actingAs($seller)->get(route('admin.seller.orders.show', $orderId))->assertOk()->assertSee('Người nhận Demo');
        $this->actingAs($buyer)->post(route('orders.cancel', $orderId))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $orderId, 'status' => 'released']);
        self::assertSame(0, $this->databaseInt(DB::table('inventory_balances')->where('product_variant_id', $variant)->value('reserved')));
        $this->actingAs($seller)->post(route('admin.seller.orders.transition', $orderId), ['action' => 'process'])->assertStatus(409);
    }

    private function buyer(string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now(), 'access_enabled' => true]);
        DB::table('buyer_profiles')->insert(['user_id' => $user->id, 'display_name' => $user->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** @return array{label:string,recipient_name:string,phone:string,province:string,district:string,ward:string,address_line:string,postal_code:null} */
    private function addressPayload(string $label): array
    {
        return ['label' => $label, 'recipient_name' => 'Người nhận Demo', 'phone' => '0901234567', 'province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 1', 'ward' => 'Bến Nghé', 'address_line' => '123 Đường Demo', 'postal_code' => null];
    }

    private function address(User $buyer): int
    {
        return (int) DB::table('buyer_addresses')->insertGetId($this->addressPayload('Nhà riêng') + ['user_id' => $buyer->id, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cart(User $buyer): int
    {
        return (int) DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cartItem(int $cart, int $product, int $variant, int $quantity, bool $selected, int $price): void
    {
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => $product, 'product_variant_id' => $variant, 'quantity' => $quantity, 'unit_price_minor' => $price, 'selected' => $selected, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{int,int,User} */
    private function catalog(string $slug, int $price, int $stock): array
    {
        $seller = User::factory()->create(['email' => "seller-{$slug}@example.test", 'email_verified_at' => now(), 'access_enabled' => true]);
        $seller->assignRole('Seller');
        $profile = (int) DB::table('seller_profiles')->insertGetId(['user_id' => $seller->id, 'display_name' => "Seller {$slug}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => "Store {$slug}", 'slug' => "store-{$slug}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('product_categories')->insertGetId(['name' => "Category {$slug}", 'slug' => "category-{$slug}", 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'product_category_id' => $category, 'code' => "P-{$slug}", 'name' => "Product {$slug}", 'slug' => "product-{$slug}", 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => "SKU-{$slug}", 'name' => 'Mặc định', 'price_minor' => $price, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'code' => 'MAIN', 'name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => $stock, 'reserved' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$product, $variant, $seller];
    }

    private function databaseInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && ctype_digit($value)));

        return is_int($value) ? $value : (int) $value;
    }

    private function databaseString(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
