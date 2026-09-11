<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Domain\Enums\OrderStatus;
use RuntimeException;

final readonly class CommercePaymentService
{
    public function __construct(private ConnectionInterface $db, private CommerceRevenueRecognitionService $revenue) {}

    public function recordSucceeded(User $actor, int $companyId, int $orderId, string $method, string $settlementReference, string $idempotencyKey): int
    {
        if (! in_array($method, ['bank_transfer', 'cash_on_delivery', 'internal_demo'], true) || trim($settlementReference) === '' || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages(['payment' => 'Payment evidence không hợp lệ.']);
        }
        if ($method === 'internal_demo' && ! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['method' => 'Internal demo settlement bị chặn ngoài local/testing.']);
        }

        return $this->db->transaction(function () use ($actor, $companyId, $orderId, $method, $settlementReference, $idempotencyKey): int {
            $order = $this->db->table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where('o.id', $orderId)->lockForUpdate()
                ->firstOrFail(['o.id', 'o.order_number', 'o.status', 'o.payment_status', 'o.total_minor', 'o.currency', 'sp.user_id as seller_user_id', 'sp.company_id as seller_company_id']);
            abort_unless($this->integer($order, 'seller_company_id') === $companyId, 404);
            abort_unless($this->db->table('company_user_access')->where('company_id', $companyId)->where('user_id', $this->integer($order, 'seller_user_id'))->exists(), 404);
            $locations = $this->db->table('inventory_reservations as r')->join('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')
                ->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('r.order_id', $orderId);
            abort_unless((clone $locations)->where('b.company_id', $companyId)->exists(), 404);
            abort_if((clone $locations)->where('b.company_id', '<>', $companyId)->exists(), 409, 'Order có inventory reservation ngoài Company payment scope.');
            abort_if($this->text($order, 'status') === OrderStatus::Cancelled->value, 409, 'Order đã hủy không thể thanh toán.');
            $existing = $this->db->table('commerce_payment_records')->where('order_id', $orderId)->where('idempotency_key', $idempotencyKey)->first(['id']);
            if ($existing !== null) {
                return $this->integer($existing, 'id');
            }
            abort_if($this->text($order, 'payment_status') === 'paid', 409, 'Order đã có payment succeeded.');
            $paymentId = (int) $this->db->table('commerce_payment_records')->insertGetId([
                'company_id' => $companyId, 'order_id' => $orderId, 'reference' => (string) Str::uuid(), 'idempotency_key' => $idempotencyKey,
                'method' => $method, 'provider' => 'manual_evidence', 'status' => 'succeeded', 'amount_minor' => $this->integer($order, 'total_minor'),
                'currency' => $this->text($order, 'currency'), 'settlement_reference' => trim($settlementReference), 'paid_at' => now(), 'recorded_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('orders')->where('id', $orderId)->update(['payment_status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
            $this->revenue->recognizeIfEligible($orderId, $actor->id);
            activity('commerce.payment')->causedBy($actor)->withProperties(['company_id' => $companyId, 'order_id' => $orderId, 'payment_id' => $paymentId, 'method' => $method])->log('payment.succeeded');

            return $paymentId;
        }, 3);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException("Payment {$key} is invalid.");
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (is_string($value)) {
            return $value;
        }
        throw new RuntimeException("Payment {$key} is invalid.");
    }
}
