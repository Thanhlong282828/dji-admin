<?php

namespace Modules\Performance\Application\Commands\GenerateWorkforceSnapshot;

final readonly class GenerateWorkforceSnapshotCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $key, public string $date) {}
}
