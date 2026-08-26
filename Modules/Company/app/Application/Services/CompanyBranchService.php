<?php

namespace Modules\Company\Application\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CompanyBranchService
{
    /** @param array<array-key,mixed> $branches */
    public function apply(int $companyId, array $branches, int $actorId): void
    {
        foreach ($branches as $branch) {
            if (! is_array($branch)) {
                throw new RuntimeException('Branch row không hợp lệ.');
            }
            $id = isset($branch['id']) && is_numeric($branch['id']) ? (int) $branch['id'] : null;
            $attributes = [
                'company_id' => $companyId,
                'code' => $this->requiredString($branch['code'] ?? null),
                'name' => $this->requiredString($branch['name'] ?? null),
                'address_line' => $this->nullableString($branch['address_line'] ?? null),
                'city' => $this->nullableString($branch['city'] ?? null),
                'status' => $this->requiredString($branch['status'] ?? 'active'),
                'timezone' => $this->requiredString($branch['timezone'] ?? 'Asia/Ho_Chi_Minh'),
                'updated_at' => now(),
            ];
            if ($id === null) {
                $id = $this->databaseId(DB::table('branches')->insertGetId([...$attributes, 'created_at' => now()]));
                DB::table('branch_user_access')->updateOrInsert(['branch_id' => $id, 'user_id' => $actorId], ['created_at' => now(), 'updated_at' => now()]);
            } else {
                $updated = DB::table('branches')->where('id', $id)->where('company_id', $companyId)->whereNull('deleted_at')->update($attributes);
                if ($updated === 0 && ! DB::table('branches')->where('id', $id)->where('company_id', $companyId)->whereNull('deleted_at')->exists()) {
                    throw new RuntimeException('Branch không thuộc Company đang chỉnh sửa.');
                }
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Branch code/name/status/timezone không hợp lệ.');
        }

        return $value;
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database không trả về branch ID hợp lệ.');
        }

        return (int) $value;
    }
}
