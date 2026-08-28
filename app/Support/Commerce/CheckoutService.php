<?php

namespace App\Support\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CheckoutService
{
    /** @return array{stores:array<int,array{name:string,items:list<array<string,mixed>>,subtotal_minor:int}>,subtotal_minor:int,shipping_minor:int,total_minor:int} */
    public function preview(User $user, string $shippingMethod): array
    {
        $method = $this->shipping($shippingMethod);
        $cart = app(CartService::class)->view($user);
        $stores = [];
        $subtotal = 0;
        foreach ($cart->stores as $storeId => $store) {
            $items = array_values(array_filter($store['items'], static fn (array $item): bool => $item['selected'] && $item['valid']));
            if ($items === []) {
                continue;
            }
            $storeSubtotal = array_sum(array_map(fn (array $item): int => $this->integer($item['current_price_minor']) * $this->integer($item['quantity']), $items));
            $stores[(int) $storeId] = ['name' => $store['name'], 'items' => $items, 'subtotal_minor' => $storeSubtotal];
            $subtotal += $storeSubtotal;
        }
        if ($stores === []) {
            throw ValidationException::withMessages(['cart' => 'Hãy chọn ít nhất một sản phẩm hợp lệ.']);
        }
        $shipping = count($stores) * $method['fee_minor'];

        return ['stores' => $stores, 'subtotal_minor' => $subtotal, 'shipping_minor' => $shipping, 'total_minor' => $subtotal + $shipping];
    }

    /** @return list<int> */
    public function place(User $user, int $addressId, string $shippingMethod, string $idempotencyKey): array
    {
        $method = $this->shipping($shippingMethod);
        $this->assertBuyer($user);

        return $this->integerIds(DB::transaction(function () use ($user, $addressId, $shippingMethod, $idempotencyKey, $method): array {
            $existing = DB::table('checkout_sessions')->where('buyer_user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first(['id', 'checkout_reference', 'status']);
            if ($existing !== null && $existing->status === 'completed') {
                return $this->integerIds(DB::table('orders')->where('checkout_session_id', $existing->id)->orderBy('id')->pluck('id'));
            }
            if ($existing === null) {
                DB::table('checkout_sessions')->insertOrIgnore(['checkout_reference' => (string) Str::uuid(), 'buyer_user_id' => $user->id, 'idempotency_key' => $idempotencyKey, 'status' => 'processing', 'currency' => 'VND', 'created_at' => now(), 'updated_at' => now()]);
                $existing = DB::table('checkout_sessions')->where('buyer_user_id', $user->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->firstOrFail(['id', 'checkout_reference', 'status']);
                if ($existing->status === 'completed') {
                    return $this->integerIds(DB::table('orders')->where('checkout_session_id', $existing->id)->orderBy('id')->pluck('id'));
                }
            }
            $sessionId = $this->integer($existing->id);
            $reference = is_string($existing->checkout_reference ?? null) ? $existing->checkout_reference : $this->string(DB::table('checkout_sessions')->where('id', $sessionId)->value('checkout_reference'));
            $address = DB::table('buyer_addresses')->where('id', $addressId)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $addressSnapshot = json_encode(['label' => $address->label, 'recipient_name' => $address->recipient_name, 'phone' => $address->phone, 'province' => $address->province, 'district' => $address->district, 'ward' => $address->ward, 'address_line' => $address->address_line, 'postal_code' => $address->postal_code], JSON_THROW_ON_ERROR);
            $cartId = DB::table('carts')->where('user_id', $user->id)->where('status', 'active')->value('id');
            abort_unless(is_numeric($cartId), 422, 'Cart không tồn tại.');
            $items = DB::table('cart_items as ci')->join('product_variants as v', 'v.id', '=', 'ci.product_variant_id')->join('products as p', 'p.id', '=', 'ci.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('ci.cart_id', (int) $cartId)->where('ci.selected', true)->where('v.status', 'active')->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->lockForUpdate()->get(['ci.id as cart_item_id', 'ci.quantity', 'p.id as product_id', 'p.name as product_name', 'v.id as variant_id', 'v.sku', 'v.name as variant_name', 'v.price_minor', 's.id as store_id', 's.name as store_name', 'sp.id as seller_profile_id']);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Không còn sản phẩm đã chọn hợp lệ.']);
            }
            $orderIds = [];
            $checkoutTotal = 0;
            foreach ($items->groupBy('store_id') as $storeItems) {
                $first = $storeItems->first();
                if (! is_object($first)) {
                    throw new RuntimeException('Store group rỗng.');
                }
                $subtotal = 0;
                foreach ($storeItems as $item) {
                    $subtotal += $this->integer($item->price_minor) * $this->integer($item->quantity);
                }
                $orderId = (int) DB::table('orders')->insertGetId(['checkout_session_id' => $sessionId, 'order_number' => $this->orderNumber(), 'checkout_reference' => $reference, 'buyer_user_id' => $user->id, 'store_id' => $first->store_id, 'seller_profile_id' => $first->seller_profile_id, 'store_name_snapshot' => $first->store_name, 'shipping_address_snapshot' => $addressSnapshot, 'shipping_method' => $shippingMethod, 'status' => OrderStatus::PendingPayment->value, 'payment_status' => 'unpaid', 'currency' => 'VND', 'subtotal_minor' => $subtotal, 'shipping_minor' => $method['fee_minor'], 'discount_minor' => 0, 'total_minor' => $subtotal + $method['fee_minor'], 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $orderIds[] = $orderId;
                $checkoutTotal += $subtotal + $method['fee_minor'];
                foreach ($storeItems as $item) {
                    $quantity = $this->integer($item->quantity);
                    $price = $this->integer($item->price_minor);
                    $orderItemId = (int) DB::table('order_items')->insertGetId(['order_id' => $orderId, 'product_id' => $item->product_id, 'product_variant_id' => $item->variant_id, 'sku_snapshot' => $item->sku, 'product_name_snapshot' => $item->product_name, 'variant_label_snapshot' => $item->variant_name, 'quantity' => $quantity, 'unit_price_minor' => $price, 'line_total_minor' => $price * $quantity, 'currency' => 'VND', 'created_at' => now(), 'updated_at' => now()]);
                    $this->reserve($orderId, $orderItemId, $this->integer($item->variant_id), $quantity);
                }
            }
            DB::table('cart_items')->whereIn('id', $this->integerIds($items->pluck('cart_item_id')))->delete();
            DB::table('checkout_sessions')->where('id', $sessionId)->update(['status' => 'completed', 'total_minor' => $checkoutTotal, 'completed_at' => now(), 'updated_at' => now()]);
            activity('commerce.order')->causedBy($user)->withProperties([
                'checkout_reference' => $reference,
                'order_count' => count($orderIds),
                'store_count' => count($orderIds),
                'item_quantity' => $items->sum(fn (object $item): int => $this->integer($item->quantity)),
            ])->log('order.created');
            activity('commerce.inventory')->causedBy($user)->withProperties([
                'checkout_reference' => $reference,
                'order_count' => count($orderIds),
                'reserved_quantity' => $items->sum(fn (object $item): int => $this->integer($item->quantity)),
            ])->log('inventory.reserved');

            return $orderIds;
        }, 3));
    }

    public function cancel(User $user, int $orderId): void
    {
        DB::transaction(function () use ($user, $orderId): void {
            $order = DB::table('orders')->where('id', $orderId)->where('buyer_user_id', $user->id)->lockForUpdate()->firstOrFail(['id', 'order_number', 'status']);
            $status = OrderStatus::from($this->string($order->status));
            abort_unless($status->buyerCanCancel(), 409, 'Đơn hàng không còn được phép hủy.');
            $reservations = DB::table('inventory_reservations')->where('order_id', $orderId)->where('status', 'reserved')->lockForUpdate()->get(['id', 'inventory_balance_id', 'quantity']);
            foreach ($reservations as $reservation) {
                $balance = DB::table('inventory_balances')->where('id', $reservation->inventory_balance_id)->lockForUpdate()->firstOrFail(['id', 'reserved', 'version']);
                $next = $this->integer($balance->reserved) - $this->integer($reservation->quantity);
                if ($next < 0) {
                    throw new RuntimeException('Reservation ledger không khớp inventory balance.');
                }
                DB::table('inventory_balances')->where('id', $balance->id)->update(['reserved' => $next, 'version' => $this->integer($balance->version) + 1, 'updated_at' => now()]);
                DB::table('inventory_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
            }
            DB::table('orders')->where('id', $orderId)->update(['status' => OrderStatus::Cancelled->value, 'updated_at' => now()]);
            activity('commerce.order')->causedBy($user)->withProperties([
                'order_number' => $this->string($order->order_number),
                'reservation_count' => $reservations->count(),
                'released_quantity' => $reservations->sum(fn (object $reservation): int => $this->integer($reservation->quantity)),
            ])->log('order.cancelled');
            activity('commerce.inventory')->causedBy($user)->withProperties([
                'order_number' => $this->string($order->order_number),
                'reservation_count' => $reservations->count(),
                'released_quantity' => $reservations->sum(fn (object $reservation): int => $this->integer($reservation->quantity)),
            ])->log('inventory.released');
        }, 3);
    }

    private function reserve(int $orderId, int $orderItemId, int $variantId, int $quantity): void
    {
        $balances = DB::table('inventory_balances')->where('product_variant_id', $variantId)->orderBy('id')->lockForUpdate()->get(['id', 'on_hand', 'reserved', 'version']);
        $remaining = $quantity;
        foreach ($balances as $balance) {
            $available = $this->integer($balance->on_hand) - $this->integer($balance->reserved);
            $take = min($remaining, max(0, $available));
            if ($take < 1) {
                continue;
            }
            DB::table('inventory_balances')->where('id', $balance->id)->update(['reserved' => $this->integer($balance->reserved) + $take, 'version' => $this->integer($balance->version) + 1, 'updated_at' => now()]);
            DB::table('inventory_reservations')->insert(['order_id' => $orderId, 'order_item_id' => $orderItemId, 'product_variant_id' => $variantId, 'inventory_balance_id' => $balance->id, 'quantity' => $take, 'status' => 'reserved', 'reference' => (string) Str::uuid(), 'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $remaining -= $take;
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['inventory' => 'Sản phẩm không còn đủ tồn kho khả dụng.']);
        }
    }

    /** @return array{label:string,fee_minor:int} */
    private function shipping(string $method): array
    {
        $config = config('checkout.shipping_methods.'.$method);
        if (! is_array($config) || ! is_string($config['label'] ?? null) || ! is_int($config['fee_minor'] ?? null)) {
            throw ValidationException::withMessages(['shipping_method' => 'Phương thức vận chuyển không hợp lệ.']);
        }

        return ['label' => $config['label'], 'fee_minor' => $config['fee_minor']];
    }

    private function assertBuyer(User $user): void
    {
        abort_unless($user->access_enabled && DB::table('buyer_profiles')->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
    }

    private function orderNumber(): string
    {
        return 'ODR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function integer(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException('Checkout persistence value is not an integer.');
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new RuntimeException('Checkout persistence value is not a string.');
    }

    /**
     * @param  iterable<array-key, mixed>  $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }
}
