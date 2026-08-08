<?php

namespace Modules\Leave\Application\Commands;

use Modules\Leave\Domain\Services\LeaveAttachmentService;

final readonly class ManageLeaveAttachmentHandler
{
    public function __construct(private LeaveAttachmentService $service) {}

    public function handle(ManageLeaveAttachmentCommand $command): int
    {
        return $this->service->execute($command);
    }
}
