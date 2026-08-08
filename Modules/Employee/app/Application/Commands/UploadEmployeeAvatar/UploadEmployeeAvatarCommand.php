<?php

namespace Modules\Employee\Application\Commands\UploadEmployeeAvatar;

use Illuminate\Http\UploadedFile;

final readonly class UploadEmployeeAvatarCommand
{
    public function __construct(public int $companyId, public int $employeeId, public UploadedFile $file, public int $actorId) {}
}
