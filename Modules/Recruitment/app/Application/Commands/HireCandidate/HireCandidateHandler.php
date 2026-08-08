<?php

namespace Modules\Recruitment\Application\Commands\HireCandidate;

use Modules\Recruitment\Application\DTOs\HireCandidateResult;
use Modules\Recruitment\Domain\Services\HireCandidateService;

final readonly class HireCandidateHandler
{
    public function __construct(private HireCandidateService $service) {}

    public function handle(HireCandidateCommand $command): HireCandidateResult
    {
        return $this->service->hire($command->companyId, $command->offerId, $command->data, $command->actorId);
    }
}
