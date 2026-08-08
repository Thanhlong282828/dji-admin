<?php

namespace Modules\Attendance\Application\Commands;

final readonly class ManageOvertimeCommand
{
    /** @param array<string, bool|int|string|null|list<string>> $data */
    public function __construct(public string $action, public int $companyId, public ?int $subjectId, public int $actorId, public int $expectedVersion, public string $idempotencyKey, public array $data) {}
}
