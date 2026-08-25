<?php

namespace Modules\Employee\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;
use Spatie\Permission\Models\Role;

final class EmployeeAccountService
{
    /** @param array{email:string,password?:string,role:string,access_enabled:bool,verified:bool} $data */
    public function save(int $companyId, int $employeeId, array $data): User
    {
        return DB::transaction(function () use ($companyId, $employeeId, $data): User {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $user = $employee->user_id === null ? new User : User::query()->lockForUpdate()->findOrFail($employee->user_id);
            $existing = User::query()->where('email', $data['email'])->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))->exists();
            if ($existing) {
                throw ValidationException::withMessages(['account_email' => 'Email đăng nhập đã được sử dụng.']);
            }
            if (! $user->exists && ! isset($data['password'])) {
                throw ValidationException::withMessages(['account_password' => 'Mật khẩu là bắt buộc cho tài khoản mới.']);
            }
            $user->name = (string) $employee->full_name;
            $user->email = $data['email'];
            $user->access_enabled = $data['access_enabled'];
            $user->email_verified_at = $data['verified'] ? ($user->email_verified_at ?? now()) : null;
            if (isset($data['password']) && $data['password'] !== '') {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
            if (! $user->access_enabled) {
                DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', false)->update(['revoked' => true]);
            }
            $role = Role::findByName($data['role'], 'web');
            $user->syncRoles([$role]);
            DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $user->id], ['created_at' => now(), 'updated_at' => now()]);
            if ($employee->user_id === null) {
                $employee->update(['user_id' => $user->id]);
            }

            return $user->refresh();
        }, 3);
    }

    public function setAccess(int $companyId, int $employeeId, bool $enabled, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $employeeId, $enabled, $actorId): void {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $user = User::query()->lockForUpdate()->findOrFail($employee->user_id);
            if (! $enabled && $user->id === $actorId) {
                throw ValidationException::withMessages(['account' => 'Không thể tự vô hiệu hóa tài khoản đang đăng nhập.']);
            }
            if (! $enabled && $user->hasRole('Super Admin') && User::query()->where('access_enabled', true)->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'Không thể vô hiệu hóa Super Admin cuối cùng.']);
            }
            $user->update(['access_enabled' => $enabled]);
            if (! $enabled) {
                DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', false)->update(['revoked' => true]);
            }
        }, 3);
    }
}
