<?php

namespace Modules\Inventory\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class InventoryService
{
    /** @param array{source_type?:string,source_id?:int,reference?:string,idempotency_key?:string,actor_id?:int,reason?:string,unit_cost_minor?:int,movement_value_minor?:int} $context */
    public function adjust(int $variantId, int $locationId, string $operation, int $quantity, array $context = []): int
    {
        if (! in_array($operation, ['increase', 'decrease', 'set'], true) || $quantity < 0) {
            throw ValidationException::withMessages(['inventory' => 'Yêu cầu điều chỉnh tồn kho không hợp lệ.']);
        }

        return DB::transaction(function () use ($variantId, $locationId, $operation, $quantity, $context): int {
            $balance = DB::table('inventory_balances')
                ->where('product_variant_id', $variantId)
                ->where('inventory_location_id', $locationId)
                ->lockForUpdate()
                ->firstOrFail(['id', 'on_hand', 'reserved', 'inventory_value_minor', 'average_unit_cost_minor', 'version']);
            $current = $this->integer($balance->on_hand);
            $currentValue = $this->integer($balance->inventory_value_minor);
            $currentCost = $this->integer($balance->average_unit_cost_minor);
            $next = match ($operation) {
                'increase' => $current + $quantity,
                'decrease' => $current - $quantity,
                'set' => $quantity,
            };
            $unitCost = $context['unit_cost_minor'] ?? $currentCost;
            if ($unitCost < 0) {
                throw ValidationException::withMessages(['inventory' => 'Đơn giá tồn kho không hợp lệ.']);
            }
            $calculatedMovementValue = ($next - $current) * $unitCost;
            $movementValue = in_array($operation, ['increase', 'decrease'], true) && array_key_exists('movement_value_minor', $context)
                ? $context['movement_value_minor']
                : $calculatedMovementValue;
            if ($movementValue < 0 && $operation === 'increase') {
                throw ValidationException::withMessages(['inventory' => 'Giá trị movement tồn kho không hợp lệ.']);
            }
            if ($movementValue > 0 && $operation === 'decrease') {
                throw ValidationException::withMessages(['inventory' => 'Giá trị movement giảm tồn kho phải âm.']);
            }
            $nextValue = match ($operation) {
                'increase' => $currentValue + $movementValue,
                'decrease' => $currentValue + $movementValue,
                'set' => max(0, $next * $unitCost),
            };
            if ($next === 0) {
                $nextValue = 0;
            }
            if ($nextValue < 0) {
                throw ValidationException::withMessages(['inventory' => 'Giá trị xuất kho vượt giá trị tồn hiện hữu.']);
            }
            $average = $next > 0 ? intdiv($nextValue + intdiv($next, 2), $next) : 0;
            $this->persist($variantId, $locationId, $next, $this->integer($balance->reserved), $nextValue, $average);
            $this->movement($variantId, $locationId, $operation, $current, $next, $currentValue, $nextValue, $unitCost, $movementValue, $context);

            return $next;
        }, 3);
    }

    public function set(int $variantId, int $locationId, int $onHand, int $reserved): void
    {
        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand) {
            throw ValidationException::withMessages(['inventory' => 'Tồn kho không hợp lệ: reserved không được vượt on_hand.']);
        }

        $existing = DB::table('inventory_balances')
            ->where('product_variant_id', $variantId)
            ->where('inventory_location_id', $locationId)
            ->lockForUpdate()
            ->first(['id', 'version', 'on_hand', 'inventory_value_minor', 'average_unit_cost_minor']);

        $before = 0;
        if ($existing === null) {
            DB::table('inventory_balances')->insert([
                'product_variant_id' => $variantId,
                'inventory_location_id' => $locationId,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'inventory_value_minor' => 0,
                'average_unit_cost_minor' => 0,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } else {
            $before = $this->integer($existing->on_hand);
            $beforeValue = $this->integer($existing->inventory_value_minor);
            $unitCost = $this->integer($existing->average_unit_cost_minor);
            $afterValue = $onHand * $unitCost;
            DB::table('inventory_balances')->where('id', $this->integer($existing->id))->update([
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'inventory_value_minor' => $afterValue,
                'average_unit_cost_minor' => $unitCost,
                'version' => $this->integer($existing->version) + 1,
                'updated_at' => now(),
            ]);
        }
        if ($before !== $onHand) {
            $beforeValue ??= 0;
            $afterValue ??= 0;
            $unitCost ??= 0;
            $this->movement($variantId, $locationId, 'set', $before, $onHand, $beforeValue, $afterValue, $unitCost, $afterValue - $beforeValue, ['source_type' => 'product_catalog', 'reason' => 'Product catalog inventory set']);
        }
    }

    public function available(int $onHand, int $reserved): int
    {
        return max(0, $onHand - $reserved);
    }

    public function reserveBalance(int $balanceId, int $quantity): void
    {
        DB::transaction(function () use ($balanceId, $quantity): void {
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->firstOrFail(['id', 'on_hand', 'reserved', 'version']);
            $reserved = $this->integer($balance->reserved) + $quantity;
            if ($quantity < 1 || $reserved > $this->integer($balance->on_hand)) {
                throw ValidationException::withMessages(['inventory' => 'Không đủ tồn kho khả dụng để giữ chỗ.']);
            }
            DB::table('inventory_balances')->where('id', $balanceId)->update(['reserved' => $reserved, 'version' => $this->integer($balance->version) + 1, 'updated_at' => now()]);
        }, 3);
    }

    public function releaseBalanceReservation(int $balanceId, int $quantity): void
    {
        DB::transaction(function () use ($balanceId, $quantity): void {
            $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->firstOrFail(['id', 'reserved', 'version']);
            $reserved = $this->integer($balance->reserved) - $quantity;
            if ($quantity < 1 || $reserved < 0) {
                throw new RuntimeException('Reservation ledger không khớp inventory balance.');
            }
            DB::table('inventory_balances')->where('id', $balanceId)->update(['reserved' => $reserved, 'version' => $this->integer($balance->version) + 1, 'updated_at' => now()]);
        }, 3);
    }

    /** @param array{source_type?:string,source_id?:int,reference?:string,idempotency_key?:string,actor_id?:int,reason?:string,unit_cost_minor?:int,movement_value_minor?:int} $context */
    public function consumeReservedBalance(int $balanceId, int $quantity, array $context = []): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['inventory' => 'Số lượng consume phải dương.']);
        }
        $balance = DB::table('inventory_balances')->where('id', $balanceId)->lockForUpdate()->firstOrFail(['id', 'product_variant_id', 'inventory_location_id', 'on_hand', 'reserved', 'inventory_value_minor', 'average_unit_cost_minor', 'version']);
        $before = $this->integer($balance->on_hand);
        $reserved = $this->integer($balance->reserved) - $quantity;
        $after = $before - $quantity;
        if ($after < 0 || $reserved < 0) {
            throw ValidationException::withMessages(['inventory' => 'Inventory reservation không reconcile với balance.']);
        }
        $beforeValue = $this->integer($balance->inventory_value_minor);
        $unitCost = $context['unit_cost_minor'] ?? $this->integer($balance->average_unit_cost_minor);
        $movementValue = -($quantity * $unitCost);
        $afterValue = $after === 0 ? 0 : $beforeValue + $movementValue;
        if ($afterValue < 0) {
            throw ValidationException::withMessages(['inventory' => 'COGS vượt giá trị tồn hiện hữu.']);
        }
        DB::table('inventory_balances')->where('id', $balanceId)->update(['on_hand' => $after, 'reserved' => $reserved, 'inventory_value_minor' => $afterValue, 'average_unit_cost_minor' => $after > 0 ? intdiv($afterValue + intdiv($after, 2), $after) : 0, 'version' => $this->integer($balance->version) + 1, 'updated_at' => now()]);
        $this->movement($this->integer($balance->product_variant_id), $this->integer($balance->inventory_location_id), 'reservation_consumed', $before, $after, $beforeValue, $afterValue, $unitCost, $movementValue, $context);
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new RuntimeException('Inventory persistence value is not an integer.');
    }

    private function persist(int $variantId, int $locationId, int $onHand, int $reserved, int $inventoryValue, int $averageUnitCost): void
    {
        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand) {
            throw ValidationException::withMessages(['inventory' => 'Tồn kho không hợp lệ: reserved không được vượt on_hand.']);
        }
        $existing = DB::table('inventory_balances')->where('product_variant_id', $variantId)->where('inventory_location_id', $locationId)->lockForUpdate()->firstOrFail(['id', 'version']);
        DB::table('inventory_balances')->where('id', $this->integer($existing->id))->update(['on_hand' => $onHand, 'reserved' => $reserved, 'inventory_value_minor' => $inventoryValue, 'average_unit_cost_minor' => $averageUnitCost, 'version' => $this->integer($existing->version) + 1, 'updated_at' => now()]);
    }

    /** @param array{source_type?:string,source_id?:int,reference?:string,idempotency_key?:string,actor_id?:int,reason?:string,unit_cost_minor?:int} $context */
    private function movement(int $variantId, int $locationId, string $type, int $before, int $after, int $valueBefore, int $valueAfter, int $unitCost, int $movementValue, array $context): void
    {
        DB::table('inventory_movements')->insert([
            'product_variant_id' => $variantId,
            'inventory_location_id' => $locationId,
            'movement_type' => $type,
            'quantity_delta' => $after - $before,
            'unit_cost_minor' => $unitCost,
            'movement_value_minor' => $movementValue,
            'on_hand_before' => $before,
            'on_hand_after' => $after,
            'balance_value_before_minor' => $valueBefore,
            'balance_value_after_minor' => $valueAfter,
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'reference' => $context['reference'] ?? null,
            'idempotency_key' => $context['idempotency_key'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'reason' => $context['reason'] ?? null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
