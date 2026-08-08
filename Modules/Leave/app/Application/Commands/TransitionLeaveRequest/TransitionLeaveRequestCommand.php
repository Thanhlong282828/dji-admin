<?php

namespace Modules\Leave\Application\Commands\TransitionLeaveRequest;

use Modules\Leave\Domain\Enums\LeaveRequestStatus;

final readonly class TransitionLeaveRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public LeaveRequestStatus $target, public int $expectedVersion, public int $actorId, public ?string $comment) {}
}
