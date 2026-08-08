<?php

namespace Modules\Leave\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class ManageLeaveAttachmentCommand
{
    public function __construct(public string $action, public int $companyId, public int $leaveRequestId, public ?int $attachmentId, public ?UploadedFile $file, public int $expectedVersion, public string $idempotencyKey, public int $actorId) {}
}
