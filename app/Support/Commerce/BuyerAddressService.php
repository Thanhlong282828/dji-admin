<?php

namespace App\Support\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class BuyerAddressService
{
    /** @param array{label:string,recipient_name:string,phone:string,province:string,district:string,ward:string,address_line:string,postal_code:?string,is_default:bool} $data */
    public function save(User $user, ?int $addressId, array $data): int
    {
        $this->assertBuyer($user);

        return DB::transaction(function () use ($user, $addressId, $data): int {
            $this->lockBuyer($user);
            if ($addressId !== null) {
                DB::table('buyer_addresses')->where('id', $addressId)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            }
            $makeDefault = $data['is_default'] || ! DB::table('buyer_addresses')->where('user_id', $user->id)->exists();
            if ($makeDefault) {
                DB::table('buyer_addresses')->where('user_id', $user->id)->update(['is_default' => false, 'updated_at' => now()]);
            }
            $values = [...$data, 'is_default' => $makeDefault, 'updated_at' => now()];
            if ($addressId === null) {
                return (int) DB::table('buyer_addresses')->insertGetId([...$values, 'user_id' => $user->id, 'created_at' => now()]);
            }
            DB::table('buyer_addresses')->where('id', $addressId)->where('user_id', $user->id)->update($values);

            return $addressId;
        }, 3);
    }

    public function makeDefault(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId): void {
            $this->lockBuyer($user);
            DB::table('buyer_addresses')->where('id', $addressId)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            DB::table('buyer_addresses')->where('user_id', $user->id)->update(['is_default' => false, 'updated_at' => now()]);
            DB::table('buyer_addresses')->where('id', $addressId)->update(['is_default' => true, 'updated_at' => now()]);
        }, 3);
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId): void {
            $this->lockBuyer($user);
            $address = DB::table('buyer_addresses')->where('id', $addressId)->where('user_id', $user->id)->lockForUpdate()->firstOrFail(['id', 'is_default']);
            DB::table('buyer_addresses')->where('id', $addressId)->delete();
            if ((bool) $address->is_default) {
                $replacement = DB::table('buyer_addresses')->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->value('id');
                if (is_numeric($replacement)) {
                    DB::table('buyer_addresses')->where('id', (int) $replacement)->update(['is_default' => true, 'updated_at' => now()]);
                }
            }
        }, 3);
    }

    private function assertBuyer(User $user): void
    {
        abort_unless($user->access_enabled && DB::table('buyer_profiles')->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
    }

    private function lockBuyer(User $user): void
    {
        DB::table('buyer_profiles')->where('user_id', $user->id)->lockForUpdate()->firstOrFail(['id']);
    }
}
