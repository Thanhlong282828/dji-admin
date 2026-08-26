<?php

namespace App\Support\Commerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class InventoryService
{
    public function set(int $variantId, int $locationId, int $onHand, int $reserved): void
    {
        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand) {
            throw ValidationException::withMessages(['inventory' => 'Tồn kho không hợp lệ: reserved không được vượt on_hand.']);
        }

        $existing = DB::table('inventory_balances')
            ->where('product_variant_id', $variantId)
            ->where('inventory_location_id', $locationId)
            ->lockForUpdate()
            ->first(['id', 'version']);

        if ($existing === null) {
            DB::table('inventory_balances')->insert([
                'product_variant_id' => $variantId,
                'inventory_location_id' => $locationId,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('inventory_balances')->where('id', $this->integer($existing->id))->update([
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'version' => $this->integer($existing->version) + 1,
            'updated_at' => now(),
        ]);
    }

    public function available(int $onHand, int $reserved): int
    {
        return max(0, $onHand - $reserved);
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
}
