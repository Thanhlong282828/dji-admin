<?php

namespace Modules\Leave\Application\Queries;

use Modules\Leave\Domain\Services\LeaveAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadLeaveAttachmentHandler
{
    public function __construct(private LeaveAttachmentService $service) {}

    public function handle(DownloadLeaveAttachmentQuery $query): StreamedResponse
    {
        return $this->service->download($query);
    }
}
