<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\DTOs\CartPricingSummary;
use Modules\Commerce\Application\DTOs\CartViewModel;
use RuntimeException;

final class CartService
{
    public function add(User $user, int $variantId, int $quantity): int
    {
        $this->assertBuyer($user);
        $this->assertQuantity($quantity);

        return DB::transaction(function () use ($user, $variantId, $quantity): int {
            $variant = $this->sellableVariant($variantId, true);
            $available = $this->available($variantId);
            DB::table('carts')->insertOrIgnore(['user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $cartId = $this->activeCartId($user, true);
            $item = DB::table('cart_items')->where('cart_id', $cartId)->where('product_variant_id', $variantId)->lockForUpdate()->first(['id', 'quantity']);
            $requested = $quantity + ($item === null ? 0 : $this->integer($item->quantity));
            $this->assertAvailable($requested, $available);
            if ($item === null) {
                DB::table('cart_items')->insert([
                    'cart_id' => $cartId,
                    'product_id' => $this->integer($this->field($variant, 'product_id')),
                    'product_variant_id' => $variantId,
                    'quantity' => $requested,
                    'unit_price_minor' => $this->integer($this->field($variant, 'price_minor')),
                    'selected' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('cart_items')->where('id', $this->integer($item->id))->update(['quantity' => $requested, 'selected' => true, 'updated_at' => now()]);
            }

            return $requested;
        }, 3);
    }

    public function updateQuantity(User $user, int $itemId, int $quantity): void
    {
        $this->assertBuyer($user);
        $this->assertQuantity($quantity);
        DB::transaction(function () use ($user, $itemId, $quantity): void {
            $item = $this->ownedItem($user, $itemId, true);
            $variantId = $this->integer($this->field($item, 'product_variant_id'));
            $this->sellableVariant($variantId, true);
            $this->assertAvailable($quantity, $this->available($variantId));
            DB::table('cart_items')->where('id', $itemId)->update(['quantity' => $quantity, 'updated_at' => now()]);
        }, 3);
    }

    public function remove(User $user, int $itemId): void
    {
        $this->assertBuyer($user);
        $item = $this->ownedItem($user, $itemId);
        DB::table('cart_items')->where('id', $this->integer($this->field($item, 'id')))->delete();
    }

    public function selectItem(User $user, int $itemId, bool $selected): void
    {
        $this->assertBuyer($user);
        $item = $this->ownedItem($user, $itemId);
        DB::table('cart_items')->where('id', $this->integer($this->field($item, 'id')))->update(['selected' => $selected, 'updated_at' => now()]);
    }

    public function selectStore(User $user, int $storeId, bool $selected): void
    {
        $cartId = $this->activeCartId($user);
        DB::table('cart_items')->where('cart_id', $cartId)->whereIn('product_id', DB::table('products')->where('store_id', $storeId)->select('id'))->update(['selected' => $selected, 'updated_at' => now()]);
    }

    public function selectAll(User $user, bool $selected): void
    {
        DB::table('cart_items')->where('cart_id', $this->activeCartId($user))->update(['selected' => $selected, 'updated_at' => now()]);
    }

    public function badge(User $user): int
    {
        if (! DB::table('buyer_profiles')->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return 0;
        }
        $cartId = DB::table('carts')->where('user_id', $user->id)->where('status', 'active')->value('id');

        return is_numeric($cartId) ? (int) DB::table('cart_items')->where('cart_id', (int) $cartId)->sum('quantity') : 0;
    }

    public function view(User $user): CartViewModel
    {
        $this->assertBuyer($user);
        DB::table('carts')->insertOrIgnore(['user_id' => $user->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $cartId = $this->activeCartId($user);
        $inventory = DB::table('inventory_balances')->select('product_variant_id', DB::raw('SUM(on_hand - reserved) as available'))->groupBy('product_variant_id');
        $cover = DB::table('media_assets')->where('owner_type', 'product')->where('purpose', 'product_cover')->select('owner_id', DB::raw('MIN(delivery_url) as delivery_url'))->groupBy('owner_id');
        $rows = DB::table('cart_items as ci')->join('products as p', 'p.id', '=', 'ci.product_id')->join('product_variants as v', 'v.id', '=', 'ci.product_variant_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoinSub($inventory, 'stock', 'stock.product_variant_id', '=', 'v.id')->leftJoinSub($cover, 'cover', 'cover.owner_id', '=', 'p.id')->where('ci.cart_id', $cartId)->orderBy('s.name')->orderByDesc('ci.id')->get(['ci.id', 'ci.quantity', 'ci.unit_price_minor', 'ci.selected', 'p.id as product_id', 'p.name as product_name', 'p.slug as product_slug', 'p.status as product_status', 'p.deleted_at', 'v.id as variant_id', 'v.name as variant_name', 'v.sku', 'v.price_minor', 'v.status as variant_status', 's.id as store_id', 's.name as store_name', 's.slug as store_slug', 's.status as store_status', 'sp.status as seller_status', 'stock.available', 'cover.delivery_url']);

        $stores = [];
        $selectedQuantity = 0;
        $subtotal = 0;
        $totalQuantity = 0;
        foreach ($rows as $row) {
            $available = max(0, $this->nullableInteger($this->field($row, 'available')));
            $quantity = $this->integer($this->field($row, 'quantity'));
            $currentPrice = $this->integer($this->field($row, 'price_minor'));
            $reason = $this->invalidReason($row, $available, $quantity);
            $valid = $reason === null;
            $selected = (bool) $this->field($row, 'selected') && $valid;
            if ($selected) {
                $selectedQuantity += $quantity;
                $subtotal += $quantity * $currentPrice;
            }
            $totalQuantity += $quantity;
            $storeId = $this->integer($this->field($row, 'store_id'));
            $stores[$storeId] ??= ['name' => $this->text($this->field($row, 'store_name')), 'slug' => $this->text($this->field($row, 'store_slug')), 'items' => [], 'subtotal_minor' => 0];
            $stores[$storeId]['items'][] = [
                'id' => $this->integer($this->field($row, 'id')), 'product_id' => $this->integer($this->field($row, 'product_id')), 'product_name' => $this->text($this->field($row, 'product_name')),
                'product_slug' => $this->text($this->field($row, 'product_slug')), 'variant_id' => $this->integer($this->field($row, 'variant_id')), 'variant_name' => $this->text($this->field($row, 'variant_name')),
                'sku' => $this->text($this->field($row, 'sku')), 'quantity' => $quantity, 'available' => $available, 'current_price_minor' => $currentPrice,
                'added_price_minor' => $this->integer($this->field($row, 'unit_price_minor')), 'price_changed' => $currentPrice !== $this->integer($this->field($row, 'unit_price_minor')),
                'selected' => $selected, 'valid' => $valid, 'invalid_reason' => $reason, 'cover_url' => is_string($this->field($row, 'delivery_url')) ? $this->field($row, 'delivery_url') : null,
            ];
            if ($selected) {
                $stores[$storeId]['subtotal_minor'] += $quantity * $currentPrice;
            }
        }

        return new CartViewModel($cartId, $stores, new CartPricingSummary($selectedQuantity, $subtotal), count($rows), $totalQuantity);
    }

    private function assertBuyer(User $user): void
    {
        abort_unless(DB::table('buyer_profiles')->where('user_id', $user->id)->where('status', 'active')->exists(), 403, 'Tài khoản chưa có Buyer Profile hoạt động.');
    }

    private function activeCartId(User $user, bool $lock = false): int
    {
        $this->assertBuyer($user);
        $query = DB::table('carts')->where('user_id', $user->id)->where('status', 'active');
        if ($lock) {
            $query->lockForUpdate();
        }
        $id = $query->value('id');
        if (! is_numeric($id)) {
            throw new RuntimeException('Không thể resolve active Cart.');
        }

        return (int) $id;
    }

    private function ownedItem(User $user, int $itemId, bool $lock = false): object
    {
        $query = DB::table('cart_items as ci')->join('carts as c', 'c.id', '=', 'ci.cart_id')->where('ci.id', $itemId)->where('c.user_id', $user->id)->where('c.status', 'active')->select('ci.*');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function sellableVariant(int $variantId, bool $lock = false): object
    {
        $query = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('v.id', $variantId)->where('v.status', 'active')->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->select('v.id', 'v.product_id', 'v.price_minor');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function available(int $variantId): int
    {
        return max(0, $this->nullableInteger(DB::table('inventory_balances')->where('product_variant_id', $variantId)->sum(DB::raw('on_hand - reserved'))));
    }

    private function assertQuantity(int $quantity): void
    {
        $limit = max(1, $this->nullableInteger(config('commerce.inventory.cart_quantity_limit', 99)));
        if ($quantity < 1 || $quantity > $limit) {
            throw ValidationException::withMessages(['quantity' => "Số lượng phải từ 1 đến {$limit}."]);
        }
    }

    private function assertAvailable(int $quantity, int $available): void
    {
        if ($quantity > $available) {
            throw ValidationException::withMessages(['quantity' => "Chỉ còn {$available} sản phẩm khả dụng."]);
        }
    }

    private function invalidReason(object $row, int $available, int $quantity): ?string
    {
        if ($this->field($row, 'deleted_at') !== null || $this->field($row, 'product_status') !== 'active') {
            return 'Sản phẩm tạm ẩn';
        }
        if ($this->field($row, 'variant_status') !== 'active') {
            return 'Phân loại không còn bán';
        }
        if ($this->field($row, 'store_status') !== 'active' || $this->field($row, 'seller_status') !== 'active') {
            return 'Cửa hàng tạm ngưng';
        }
        if ($available < 1) {
            return 'Hết hàng';
        }
        if ($quantity > $available) {
            return "Chỉ còn {$available} sản phẩm khả dụng";
        }

        return null;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        throw new RuntimeException('Commerce persistence value is not an integer.');
    }

    private function nullableInteger(mixed $value): int
    {
        return $value === null ? 0 : $this->integer($value);
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Commerce persistence value is not text.');
        }

        return $value;
    }

    private function field(object $row, string $field): mixed
    {
        return $row->{$field} ?? null;
    }
}
