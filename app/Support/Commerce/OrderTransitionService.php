<?php

namespace App\Support\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderTransitionService
{
    public function sellerTransition(User $actor, int $orderId, string $action): void
    {
        DB::transaction(function () use ($actor, $orderId, $action): void {
            $order = DB::table('orders as o')
                ->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')
                ->where('o.id', $orderId)
                ->where('sp.user_id', $actor->id)
                ->lockForUpdate()
                ->firstOrFail(['o.id', 'o.order_number', 'o.store_id', 'o.status']);
            $current = OrderStatus::from($this->string($order->status));
            $next = match ($action) {
                'confirm' => $current === OrderStatus::PendingPayment ? OrderStatus::Confirmed : null,
                'process' => $current === OrderStatus::Confirmed ? OrderStatus::Processing : null,
                default => null,
            };
            abort_unless($next instanceof OrderStatus, 409, 'Chuyển trạng thái không hợp lệ.');
            DB::table('orders')->where('id', $orderId)->update(['status' => $next->value, 'updated_at' => now()]);
            activity('commerce.order')->causedBy($actor)->withProperties([
                'order_number' => $this->string($order->order_number),
                'store_id' => $this->integer($order->store_id),
                'from' => $current->value,
                'to' => $next->value,
            ])->log('order.status_transitioned');
        }, 3);
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new RuntimeException('Order persistence value is not a string.');
    }

    private function integer(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new RuntimeException('Order persistence value is not an integer.');
    }
}
