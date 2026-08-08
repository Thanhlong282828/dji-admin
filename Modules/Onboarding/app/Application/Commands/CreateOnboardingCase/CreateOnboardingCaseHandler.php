<?php

namespace Modules\Onboarding\Application\Commands\CreateOnboardingCase;

use Modules\Onboarding\Application\DTOs\OnboardingCaseResult;
use Modules\Onboarding\Domain\Services\OnboardingCaseCreationService;

final readonly class CreateOnboardingCaseHandler
{
    public function __construct(private OnboardingCaseCreationService $service) {}

    public function handle(CreateOnboardingCaseCommand $command): OnboardingCaseResult
    {
        return $this->service->create($command->data);
    }
}
