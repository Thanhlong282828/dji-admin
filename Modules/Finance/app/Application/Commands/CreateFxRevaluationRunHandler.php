<?php

namespace Modules\Finance\Application\Commands;

use Illuminate\Container\Attributes\Tag;
use Modules\Finance\Application\Services\ArFxRevaluationSourceService;
use Modules\Finance\Application\Services\FxRevaluationService;
use Modules\Finance\Application\Services\FxRevaluationSourceProvider;

final readonly class CreateFxRevaluationRunHandler
{
    /** @param iterable<FxRevaluationSourceProvider> $externalSources */
    public function __construct(
        private FxRevaluationService $service,
        private ArFxRevaluationSourceService $arSources,
        #[Tag('finance.fx-revaluation-source-providers')]
        private iterable $externalSources,
    ) {}

    public function handle(CreateFxRevaluationRunCommand $command): int
    {
        $sources = $command->sources;
        if ($sources === []) {
            $sources = $this->arSources->eligible($command->companyId, $command->branchId, $command->asOfDate);
            foreach ($this->externalSources as $provider) {
                $sources = [...$sources, ...$provider->eligible($command->companyId, $command->branchId, $command->asOfDate)];
            }
        }

        return $this->service->create($command->companyId, $command->branchId, $command->periodId, $command->asOfDate, $command->idempotencyKey, $command->actor, $sources);
    }
}
