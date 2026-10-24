<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Inventory\Domain\Services\InventoryService;
use RuntimeException;

final readonly class CommerceFulfillmentService
{
    public function __construct(private ConnectionInterface $db, private CommerceRevenueRecognitionService $revenue, private InventoryService $inventory) {}

    public function create(User $seller, int $orderId, int $branchId): int
    {
        return $this->db->transaction(function () use ($seller, $orderId, $branchId): int {
            $order = $this->ownedOrder($seller, $orderId);
            abort_unless($this->text($order, 'payment_status') === 'paid' && $this->text($order, 'status') === OrderStatus::Confirmed->value, 409, 'Order phải paid và confirmed trước khi fulfillment.');
            $branch = $this->db->table('branches')->where('id', $branchId)->whereNull('deleted_at')->firstOrFail(['id', 'company_id']);
            $companyId = $this->integer($branch, 'company_id');
            abort_unless($this->integer($order, 'seller_company_id') === $companyId, 404);
            abort_unless($seller->can('organization.tenant-all') || $this->db->table('company_user_access')->where('company_id', $companyId)->where('user_id', $seller->id)->exists(), 404);
            $invalidLocation = $this->db->table('inventory_reservations as r')->join('inventory_balances as b', 'b.id', '=', 'r.inventory_balance_id')->join('inventory_locations as l', 'l.id', '=', 'b.inventory_location_id')
                ->where('r.order_id', $orderId)->where('r.status', 'reserved')->where(fn (Builder $query): Builder => $query->whereNull('l.branch_id')->orWhere('l.branch_id', '<>', $branchId))->exists();
            if ($invalidLocation) {
                throw ValidationException::withMessages(['branch_id' => 'Toàn bộ inventory reservation phải thuộc Branch fulfillment đã chọn.']);
            }
            $existing = $this->db->table('commerce_fulfillments')->where('order_id', $orderId)->first(['id']);
            if ($existing !== null) {
                return $this->integer($existing, 'id');
            }

            return (int) $this->db->table('commerce_fulfillments')->insertGetId([
                'company_id' => $companyId, 'order_id' => $orderId, 'branch_id' => $branchId, 'reference' => (string) Str::uuid(), 'status' => 'pending',
                'created_by' => $seller->id, 'updated_by' => $seller->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function transition(User $seller, int $orderId, string $action, ?string $carrier = null, ?string $tracking = null): void
    {
        $this->db->transaction(function () use ($seller, $orderId, $action, $carrier, $tracking): void {
            $order = $this->ownedOrder($seller, $orderId);
            $fulfillment = $this->db->table('commerce_fulfillments')->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
            $current = $this->text($fulfillment, 'status');
            $next = match ($action) {
                'pack' => $current === 'pending' ? 'packed' : null,
                'ship' => $current === 'packed' ? 'shipped' : null,
                'deliver' => $current === 'shipped' ? 'delivered' : null,
                default => null,
            };
            abort_unless(is_string($next), 409, 'Fulfillment transition không hợp lệ.');
            if ($next === 'shipped') {
                if (trim((string) $carrier) === '' || trim((string) $tracking) === '') {
                    throw ValidationException::withMessages(['tracking_reference' => 'Carrier và tracking reference là bắt buộc khi ship.']);
                }
                $this->consumeReservations($orderId, $seller);
            }
            $values = ['status' => $next, 'updated_by' => $seller->id, 'updated_at' => now()];
            if ($next === 'packed') {
                $values['packed_at'] = now();
            } elseif ($next === 'shipped') {
                $values['shipped_at'] = now();
                $values['carrier'] = trim((string) $carrier);
                $values['tracking_reference'] = trim((string) $tracking);
                $this->db->table('orders')->where('id', $orderId)->update(['status' => OrderStatus::Processing->value, 'updated_at' => now()]);
            } else {
                $values['delivered_at'] = now();
                $this->db->table('orders')->where('id', $orderId)->update(['status' => OrderStatus::Fulfilled->value, 'fulfilled_at' => now(), 'updated_at' => now()]);
            }
            $this->db->table('commerce_fulfillments')->where('id', $fulfillment->id)->update($values);
            if ($next === 'delivered') {
                $this->revenue->recognizeIfEligible($orderId, $seller->id);
            }
            activity('commerce.fulfillment')->causedBy($seller)->withProperties(['order_id' => $orderId, 'from' => $current, 'to' => $next])->log('fulfillment.transitioned');
        }, 3);
    }

    private function consumeReservations(int $orderId, User $actor): void
    {
        $reservations = $this->db->table('inventory_reservations')->where('order_id', $orderId)->where('status', 'reserved')->lockForUpdate()->get(['id', 'inventory_balance_id', 'quantity']);
        abort_if($reservations->isEmpty(), 409, 'Order không còn inventory reservation để ship.');
        foreach ($reservations as $reservation) {
            $quantity = $this->integer($reservation, 'quantity');
            $reservationId = $this->integer($reservation, 'id');
            $this->inventory->consumeReservedBalance($this->integer($reservation, 'inventory_balance_id'), $quantity, ['source_type' => 'commerce_order', 'source_id' => $orderId, 'reference' => "ORDER-{$orderId}", 'idempotency_key' => "order:{$orderId}:reservation:{$reservationId}", 'actor_id' => $actor->id, 'reason' => 'Fulfillment shipped']);
            $this->db->table('inventory_reservations')->where('id', $reservation->id)->update(['status' => 'consumed', 'consumed_at' => now(), 'updated_at' => now()]);
        }
    }

    private function ownedOrder(User $seller, int $orderId): object
    {
        return $this->db->table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where('o.id', $orderId)->where('sp.user_id', $seller->id)->lockForUpdate()->firstOrFail(['o.*', 'sp.company_id as seller_company_id']);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException("Fulfillment {$key} is invalid.");
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (is_string($value)) {
            return $value;
        }
        throw new RuntimeException("Fulfillment {$key} is invalid.");
    }
}
