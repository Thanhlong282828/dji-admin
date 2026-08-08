<?php

namespace Modules\Performance\Application\Queries\CareerTimeline;

use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;

final readonly class CareerTimelineHandler
{
    public function __construct(private CareerReadRepositoryInterface $repo) {}

    /** @return list<array<string,mixed>> */
    public function handle(CareerTimelineQuery $q): array
    {
        return $this->repo->timeline($q->companyId, $q->employeeId);
    }
}
