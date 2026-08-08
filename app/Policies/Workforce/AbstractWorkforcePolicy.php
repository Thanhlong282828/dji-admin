<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract readonly class AbstractWorkforcePolicy
{
    public function __construct(protected WorkforceAuthorizationPolicy $authorization) {}

    protected function company(User $user, string $permission, int $companyId): bool
    {
        return $this->authorization->company($user, $permission, $companyId);
    }

    protected function record(User $user, string $permission, int $companyId, string $table, int $id): bool
    {
        if (! $this->company($user, $permission, $companyId)) {
            return false;
        }
        if (! DB::table($table)->where('id', $id)->where('company_id', $companyId)->exists()) {
            abort(404);
        }

        return true;
    }

    protected function employee(User $user, string $permission, int $companyId, int $employeeId, string $managementPermission): bool
    {
        return $this->authorization->employee($user, $permission, $companyId, $employeeId, $managementPermission);
    }

    protected function status(string $table, int $id): string
    {
        return $this->textValue($table, $id, 'status');
    }

    protected function textValue(string $table, int $id, string $column): string
    {
        $status = DB::table($table)->where('id', $id)->value($column);

        return is_string($status) ? $status : '';
    }

    protected function unlockedAttendanceRecord(int $recordId): bool
    {
        return ! (bool) DB::table('attendance_records')->where('id', $recordId)->value('is_locked');
    }
}
