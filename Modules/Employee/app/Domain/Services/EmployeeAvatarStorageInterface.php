<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Http\UploadedFile;

interface EmployeeAvatarStorageInterface
{
    public function upload(UploadedFile $file): StoredEmployeeAvatar;

    public function delete(string $reference): void;
}
