<?php

namespace Modules\Performance\Application\Commands\UpdateCareerProgress;

use Modules\Performance\Domain\Services\CareerPathService;

final readonly class UpdateCareerProgressHandler
{
    public function __construct(private CareerPathService $service) {}

    public function handle(UpdateCareerProgressCommand $c): int
    {
        return $this->service->progress($c->data);
    }
}
