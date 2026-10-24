<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_balances')->orderBy('id')->chunkById(500, function ($balances): void {
            foreach ($balances as $balance) {
                $exists = DB::table('inventory_movements')
                    ->where('product_variant_id', $balance->product_variant_id)
                    ->where('inventory_location_id', $balance->inventory_location_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('inventory_movements')->insert([
                    'product_variant_id' => $balance->product_variant_id,
                    'inventory_location_id' => $balance->inventory_location_id,
                    'movement_type' => 'opening_balance',
                    'quantity_delta' => $balance->on_hand,
                    'on_hand_before' => 0,
                    'on_hand_after' => $balance->on_hand,
                    'source_type' => 'migration_opening_balance',
                    'source_id' => $balance->id,
                    'reference' => 'OPENING-BALANCE-'.$balance->id,
                    'idempotency_key' => 'inventory-opening-balance-'.$balance->id,
                    'actor_id' => null,
                    'reason' => 'Opening evidence for inventory balance existing before movement ledger activation.',
                    'created_at' => $balance->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        }, 'id');
    }

    public function down(): void
    {
        DB::table('inventory_movements')->where('source_type', 'migration_opening_balance')->delete();
    }
};
