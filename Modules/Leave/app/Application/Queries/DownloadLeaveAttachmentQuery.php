<?php

namespace Modules\Leave\Application\Queries;

final readonly class DownloadLeaveAttachmentQuery
{
    public function __construct(public int $companyId, public int $leaveRequestId, public int $attachmentId, public int $actorId) {}
}
