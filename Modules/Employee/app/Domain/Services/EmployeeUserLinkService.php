<?php

namespace Modules\Employee\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;

final class EmployeeUserLinkService
{
    public function link(int $companyId, int $employeeId, int $userId): Employee
    {
        return DB::transaction(function () use ($companyId, $employeeId, $userId): Employee {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if (Employee::query()->where('user_id', $userId)->whereKeyNot($employeeId)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'Tài khoản đã liên kết với nhân viên khác.']);
            }$hasAccess = DB::table('company_user_access')->where('company_id', $companyId)->where('user_id', $user->id)->exists();
            if (! $hasAccess) {
                throw ValidationException::withMessages(['user_id' => 'Tài khoản không thuộc phạm vi công ty.']);
            }$employee->update(['user_id' => $user->id]);

            return $employee->refresh();
        }, 3);
    }

    public function unlink(int $companyId, int $employeeId): void
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId)->update(['user_id' => null]);
    }

    /** Token is returned only to the notification boundary and is never persisted in plaintext. */
    public function invite(int $companyId, int $employeeId, string $email, int $actorId): string
    {
        return DB::transaction(function () use ($companyId, $employeeId, $email, $actorId): string {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            DB::table('employee_user_invitations')->where('employee_id', $employeeId)->whereNull('accepted_at')->update(['invalidated_at' => now()]);
            $token = Str::random(64);
            DB::table('employee_user_invitations')->insert(['employee_id' => $employee->id, 'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);

            return $token;
        }, 3);
    }
}
