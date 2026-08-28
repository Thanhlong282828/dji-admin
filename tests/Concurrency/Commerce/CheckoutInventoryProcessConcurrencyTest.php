<?php

namespace Tests\Concurrency\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class CheckoutInventoryProcessConcurrencyTest extends TestCase
{
    public function test_two_buyers_cannot_reserve_the_same_last_stock_unit(): void
    {
        self::assertSame('mysql', DB::connection()->getDriverName());
        $token = Str::lower(Str::random(10));
        $seller = User::factory()->create(['email' => "race-seller-{$token}@example.test", 'access_enabled' => true]);
        $buyers = [User::factory()->create(['email' => "race-a-{$token}@example.test", 'access_enabled' => true]), User::factory()->create(['email' => "race-b-{$token}@example.test", 'access_enabled' => true])];
        $profile = (int) DB::table('seller_profiles')->insertGetId(['user_id' => $seller->id, 'display_name' => 'Race Seller', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Race Store', 'slug' => "race-store-{$token}", 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('product_categories')->insertGetId(['name' => 'Race Category', 'slug' => "race-category-{$token}", 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'product_category_id' => $category, 'code' => "RACE-{$token}", 'name' => 'Race Product', 'slug' => "race-product-{$token}", 'base_price_minor' => 100000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => "RACE-SKU-{$token}", 'name' => 'Default', 'price_minor' => 100000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'code' => 'MAIN', 'name' => 'Main', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 1, 'reserved' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $payloads = [];
        foreach ($buyers as $index => $buyer) {
            DB::table('buyer_profiles')->insert(['user_id' => $buyer->id, 'display_name' => $buyer->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $address = (int) DB::table('buyer_addresses')->insertGetId(['user_id' => $buyer->id, 'label' => 'Nhà', 'recipient_name' => 'Race Buyer', 'phone' => '0901234567', 'province' => 'TP.HCM', 'district' => 'Quận 1', 'ward' => 'Bến Nghé', 'address_line' => 'Race address', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
            $cart = (int) DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => $product, 'product_variant_id' => $variant, 'quantity' => 1, 'unit_price_minor' => 100000, 'selected' => true, 'created_at' => now(), 'updated_at' => now()]);
            $payloads[$index] = ['user_id' => $buyer->id, 'address_id' => $address, 'idempotency_key' => (string) Str::uuid()];
        }

        try {
            $race = (new MysqlProcessRaceRunner)->run('commerce_checkout', $payloads[0], $payloads[1]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('orders')->where('product_variants.id', $variant)->join('order_items', 'order_items.order_id', '=', 'orders.id')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->count());
            self::assertSame(1, DB::table('inventory_reservations')->where('product_variant_id', $variant)->where('status', 'reserved')->count());
            self::assertSame(1, $this->databaseInt(DB::table('inventory_balances')->where('product_variant_id', $variant)->value('reserved')));
        } finally {
            DB::table('orders')->whereIn('buyer_user_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('checkout_sessions')->whereIn('buyer_user_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('carts')->whereIn('user_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('buyer_addresses')->whereIn('user_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('buyer_profiles')->whereIn('user_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('activity_log')->whereIn('causer_id', array_map(static fn (User $buyer): int => $buyer->id, $buyers))->delete();
            DB::table('inventory_balances')->where('product_variant_id', $variant)->delete();
            DB::table('inventory_locations')->where('id', $location)->delete();
            DB::table('product_variants')->where('id', $variant)->delete();
            DB::table('products')->where('id', $product)->delete();
            DB::table('product_categories')->where('id', $category)->delete();
            DB::table('stores')->where('id', $store)->delete();
            DB::table('seller_profiles')->where('id', $profile)->delete();
            foreach ($buyers as $buyer) {
                $buyer->delete();
            }
            $seller->delete();
        }
    }

    private function databaseInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && ctype_digit($value)));

        return is_int($value) ? $value : (int) $value;
    }
}
