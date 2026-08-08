<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;
use RuntimeException;

final class EmployeeAvatarService
{
    /** @var list<string> */
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function upload(int $companyId, int $employeeId, UploadedFile $file): Employee
    {
        $employee = Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $mime = $file->getMimeType();
        if (! is_string($mime) || ! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['avatar' => 'Ảnh đại diện chỉ hỗ trợ JPEG, PNG hoặc WebP.']);
        }$dimensions = getimagesize($file->getRealPath());
        if ($dimensions === false || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw ValidationException::withMessages(['avatar' => 'Kích thước ảnh không hợp lệ hoặc vượt 4096px.']);
        }$extension = match ($mime) {
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'webp'
        };
        $key = 'employees/'.$companyId.'/'.$employeeId.'/avatar/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('local')->putFileAs(dirname($key), $file, basename($key));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu ảnh đại diện.');
        }$old = is_string($employee->avatar_storage_key) ? $employee->avatar_storage_key : null;
        $employee->update(['avatar_storage_key' => $stored, 'avatar_url' => null]);
        if ($old !== null) {
            Storage::disk('local')->delete($old);
        }

        return $employee->refresh();
    }

    public function remove(int $companyId, int $employeeId): void
    {
        $employee = Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $old = is_string($employee->avatar_storage_key) ? $employee->avatar_storage_key : null;
        $employee->update(['avatar_storage_key' => null, 'avatar_url' => null]);
        if ($old !== null) {
            Storage::disk('local')->delete($old);
        }
    }
}
