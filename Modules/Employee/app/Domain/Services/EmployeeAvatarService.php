<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;
use Throwable;

final readonly class EmployeeAvatarService
{
    /** @var list<string> */
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private EmployeeAvatarStorageInterface $storage) {}

    public function upload(int $companyId, int $employeeId, UploadedFile $file): Employee
    {
        $uploaded = $this->stage($file);
        try {
            return DB::transaction(fn (): Employee => $this->persist($companyId, $employeeId, $uploaded), 3);
        } catch (Throwable $exception) {
            $this->compensate($uploaded);
            throw $exception;
        }
    }

    public function stage(UploadedFile $file): StoredEmployeeAvatar
    {
        $this->validate($file);

        return $this->storage->upload($file);
    }

    public function persist(int $companyId, int $employeeId, StoredEmployeeAvatar $uploaded): Employee
    {
        $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
        $old = is_string($employee->avatar_storage_key) && $employee->avatar_storage_key !== '' ? $employee->avatar_storage_key : null;
        $employee->update(['avatar_storage_key' => $uploaded->reference, 'avatar_url' => $uploaded->deliveryUrl]);
        if ($old !== null && $old !== $uploaded->reference && ! Employee::query()->whereKeyNot($employee->id)->where('avatar_storage_key', $old)->exists()) {
            DB::afterCommit(fn () => $this->deleteSafely($old, 'replaced_avatar_cleanup'));
        }

        return $employee->refresh();
    }

    public function compensate(StoredEmployeeAvatar $uploaded): void
    {
        $this->deleteSafely($uploaded->reference, 'new_upload_compensation');
    }

    public function remove(int $companyId, int $employeeId): void
    {
        DB::transaction(function () use ($companyId, $employeeId): void {
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $old = is_string($employee->avatar_storage_key) && $employee->avatar_storage_key !== '' ? $employee->avatar_storage_key : null;
            $employee->update(['avatar_storage_key' => null, 'avatar_url' => null]);
            if ($old !== null && ! Employee::query()->whereKeyNot($employee->id)->where('avatar_storage_key', $old)->exists()) {
                DB::afterCommit(fn () => $this->deleteSafely($old, 'removed_avatar_cleanup'));
            }
        }, 3);
    }

    private function validate(UploadedFile $file): void
    {
        $mime = $file->getMimeType();
        if (! is_string($mime) || ! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['avatar' => 'Ảnh đại diện chỉ hỗ trợ JPEG, PNG hoặc WebP.']);
        }
        $dimensions = getimagesize($file->getRealPath());
        if ($dimensions === false || $dimensions[0] > 4096 || $dimensions[1] > 4096 || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['avatar' => 'Kích thước ảnh không hợp lệ hoặc vượt giới hạn 5 MB / 4096px.']);
        }
    }

    private function deleteSafely(string $reference, string $operation): void
    {
        try {
            $this->storage->delete($reference);
        } catch (Throwable $exception) {
            Log::warning('Employee avatar orphan cleanup deferred.', ['component' => 'employee_avatar', 'operation' => $operation, 'reference_hash' => hash('sha256', $reference), 'exception' => $exception::class]);
        }
    }
}
