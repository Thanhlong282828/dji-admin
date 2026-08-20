<?php

namespace Modules\Employee\Infrastructure\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;
use RuntimeException;

final class LocalEmployeeAvatarStorage implements EmployeeAvatarStorageInterface
{
    public function upload(UploadedFile $file): StoredEmployeeAvatar
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'webp',
        };
        $reference = 'employees/avatars/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('local')->putFileAs(dirname($reference), $file, basename($reference));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu ảnh đại diện local.');
        }

        return new StoredEmployeeAvatar($stored, null);
    }

    public function delete(string $reference): void
    {
        Storage::disk('local')->delete($reference);
    }
}
