<?php

namespace Modules\Performance\Application\Commands\CreateCareerPath;

use Modules\Performance\Domain\Services\CareerPathService;

final readonly class CreateCareerPathHandler
{
    public function __construct(private CareerPathService $service) {}

    public function handle(CreateCareerPathCommand $c): int
    {
        return $this->service->create($c->data);
    }
}
