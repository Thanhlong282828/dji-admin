<?php

namespace Modules\Company\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanySellerAccountService
{
    /** @param array<mixed,mixed> $rows */
    public function apply(int $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422, 'Dữ liệu Seller account không hợp lệ.');
            $profileId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            $profile = $profileId === null ? null : DB::table('seller_profiles')->where('id', $profileId)->where('company_id', $companyId)->lockForUpdate()->first();
            abort_if($profileId !== null && $profile === null, 422, 'Seller account không thuộc Company đang chỉnh sửa.');

            if ($profile === null) {
                $user = User::query()->create([
                    'name' => $this->string($row, 'account_name'),
                    'email' => $this->string($row, 'email'),
                    'phone_e164' => $this->nullableString($row, 'phone_e164'),
                    'password' => $this->string($row, 'password'),
                    'access_enabled' => true,
                ]);
                $user->assignRole('Seller');
                DB::table('company_user_access')->insert([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('seller_profiles')->insert([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'display_name' => $this->string($row, 'display_name'),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            $user = User::query()->findOrFail($this->positiveInteger($profile->user_id));
            $user->name = $this->string($row, 'account_name');
            $user->email = $this->string($row, 'email');
            $user->phone_e164 = $this->nullableString($row, 'phone_e164');
            if (isset($row['password']) && is_string($row['password']) && $row['password'] !== '') {
                $user->password = $row['password'];
            }
            $user->save();
            if (! $user->hasRole('Seller')) {
                $user->assignRole('Seller');
            }
            DB::table('company_user_access')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $user->id],
                ['created_at' => now(), 'updated_at' => now()],
            );
            DB::table('seller_profiles')->where('id', $profileId)->update([
                'display_name' => $this->string($row, 'display_name'),
                'updated_at' => now(),
            ]);
        }
    }

    public function remove(int $companyId, int $profileId): int
    {
        return DB::transaction(function () use ($companyId, $profileId): int {
            $profile = DB::table('seller_profiles')->where('id', $profileId)->where('company_id', $companyId)->lockForUpdate()->firstOrFail();
            $blocked = DB::table('stores')->where('seller_profile_id', $profileId)->exists()
                || DB::table('orders')->where('seller_profile_id', $profileId)->exists()
                || DB::table('media_assets')->where('owner_type', 'seller')->where('owner_id', $profileId)->exists();
            if ($blocked) {
                throw ValidationException::withMessages(['seller_account' => 'Seller đã có Store, Order hoặc media nên không thể xóa. Hãy suspend/ban tại Seller Governance để giữ lịch sử.']);
            }

            $userId = $this->positiveInteger($profile->user_id);
            DB::table('seller_profiles')->where('id', $profileId)->delete();
            if (! DB::table('seller_profiles')->where('user_id', $userId)->exists()) {
                $user = User::query()->findOrFail($userId);
                if ($user->hasRole('Seller')) {
                    $user->removeRole('Seller');
                }
            }

            return $userId;
        }, 3);
    }

    /** @param array<mixed,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        abort_unless(is_string($value) && $value !== '', 422, "Trường {$key} không hợp lệ.");

        return $value;
    }

    /** @param array<mixed,mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        abort_unless($value === null || is_string($value), 422, "Trường {$key} không hợp lệ.");

        return $value === '' ? null : $value;
    }

    private function positiveInteger(mixed $value): int
    {
        abort_unless(is_int($value) || (is_string($value) && ctype_digit($value)), 500, 'Seller owner không hợp lệ.');

        return (int) $value;
    }
}
