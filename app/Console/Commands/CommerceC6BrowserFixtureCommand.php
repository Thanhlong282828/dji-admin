<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CommerceC6BrowserFixtureCommand extends Command
{
    protected $signature = 'commerce:c6-browser-fixture {--cleanup}';

    protected $description = 'Create or remove isolated checkout/order browser fixtures';

    public function handle(): int
    {
        if (DB::connection()->getDatabaseName() !== 'schema_admin_browser_testing' || ! app()->environment('testing')) {
            $this->error('C6 browser fixtures require testing + schema_admin_browser_testing.');

            return self::FAILURE;
        }

        $this->cleanup();
        if ($this->option('cleanup')) {
            $this->info('C6 browser fixtures removed.');

            return self::SUCCESS;
        }

        $now = now();
        $userId = DB::transaction(function () use ($now): int {
            $user = User::query()->create(['name' => 'C6 Browser Actor', 'email' => 'audit.browser@schema.test', 'password' => Hash::make(Str::random(32)), 'access_enabled' => true]);
            $user->forceFill(['email_verified_at' => $now])->save();
            $user->assignRole('Super Admin');
            $companyId = DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'AUDIT-FIXTURE', 'name' => 'C6 Browser Company', 'short_name' => 'C6 Browser', 'country_code' => 'VN', 'currency_code' => 'VND', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('company_user_access')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);

            return $user->id;
        }, 3);
        DB::transaction(function () use ($userId, $now): void {
            $buyerId = DB::table('buyer_profiles')->insertGetId(['user_id' => (int) $userId, 'display_name' => 'Buyer C6 Browser', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $sellerId = DB::table('seller_profiles')->insertGetId(['user_id' => (int) $userId, 'display_name' => 'Seller C6 Browser', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Danh mục C6 Browser', 'slug' => 'c6-browser-category', 'status' => 'active', 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now]);
            $cartId = DB::table('carts')->insertGetId(['user_id' => (int) $userId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            foreach ([['a', 'Cửa hàng C6 Alpha', 15990000], ['b', 'Cửa hàng C6 Beta', 21990000], ['keep', 'Cửa hàng C6 Alpha', 990000]] as [$key, $storeName, $price]) {
                $store = DB::table('stores')->where('slug', 'c6-browser-store-'.$key)->value('id');
                if (! is_numeric($store)) {
                    $store = DB::table('stores')->insertGetId(['seller_profile_id' => $sellerId, 'name' => $storeName, 'slug' => 'c6-browser-store-'.$key, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                }
                $product = DB::table('products')->insertGetId(['store_id' => (int) $store, 'product_category_id' => $categoryId, 'code' => 'C6-BROWSER-'.strtoupper($key), 'name' => 'Laptop Aurora C6 '.strtoupper($key), 'slug' => 'c6-browser-product-'.$key, 'description' => 'Sản phẩm synthetic chỉ dùng cho browser C6.', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'C6-BROWSER-SKU-'.strtoupper($key), 'name' => $key === 'b' ? '32GB / 1TB' : '16GB / 512GB', 'price_minor' => $price, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                $location = DB::table('inventory_locations')->insertGetId(['store_id' => (int) $store, 'code' => 'C6-'.$key, 'name' => 'Kho C6 '.$key, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => $product, 'product_variant_id' => $variant, 'quantity' => $key === 'a' ? 2 : 1, 'unit_price_minor' => 1, 'selected' => $key !== 'keep', 'created_at' => $now, 'updated_at' => $now]);
            }
            unset($buyerId);
        }, 3);
        $this->info('C6 browser fixtures ready.');

        return self::SUCCESS;
    }

    private function cleanup(): void
    {
        DB::transaction(function (): void {
            $userId = DB::table('users')->where('email', 'audit.browser@schema.test')->value('id');
            $productIds = DB::table('products')->where('code', 'like', 'C6-BROWSER-%')->pluck('id');
            $storeIds = DB::table('stores')->where('slug', 'like', 'c6-browser-store-%')->pluck('id');
            $orderIds = is_numeric($userId) ? DB::table('orders')->where('buyer_user_id', (int) $userId)->pluck('id') : collect();
            DB::table('inventory_reservations')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
            if (is_numeric($userId)) {
                DB::table('checkout_sessions')->where('buyer_user_id', (int) $userId)->delete();
                DB::table('buyer_addresses')->where('user_id', (int) $userId)->delete();
                DB::table('cart_items')->whereIn('cart_id', DB::table('carts')->where('user_id', (int) $userId)->pluck('id'))->delete();
                DB::table('carts')->where('user_id', (int) $userId)->delete();
                DB::table('buyer_profiles')->where('user_id', (int) $userId)->delete();
                DB::table('activity_log')->where('causer_type', 'App\\Models\\User')->where('causer_id', (int) $userId)->whereIn('log_name', ['commerce.order', 'commerce.inventory'])->delete();
            }
            $variantIds = DB::table('product_variants')->whereIn('product_id', $productIds)->pluck('id');
            DB::table('inventory_balances')->whereIn('product_variant_id', $variantIds)->delete();
            DB::table('inventory_locations')->whereIn('store_id', $storeIds)->delete();
            DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
            DB::table('product_categories')->where('slug', 'c6-browser-category')->delete();
            DB::table('stores')->whereIn('id', $storeIds)->delete();
            if (is_numeric($userId)) {
                DB::table('seller_profiles')->where('user_id', (int) $userId)->delete();
                DB::table('company_user_access')->where('user_id', (int) $userId)->delete();
                DB::table('companies')->where('code', 'AUDIT-FIXTURE')->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', (int) $userId)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', (int) $userId)->delete();
                DB::table('users')->where('id', (int) $userId)->delete();
            }
        }, 3);
    }
}
