<?php

namespace Modules\Integration\Application\Commands;

final readonly class RetryIntegrationMessageCommand
{
    public function __construct(public int $companyId, public int $messageId, public int $actorId) {}
}
