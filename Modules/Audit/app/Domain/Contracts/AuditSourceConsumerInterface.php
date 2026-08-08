<?php

namespace Modules\Audit\Domain\Contracts;

interface AuditSourceConsumerInterface
{
    public function consume(int $sourceRowId): ?int;

    public function consumePending(int $limit = 100): int;
}
