<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\FxWorkspaceReadService;

final readonly class GetFxWorkspaceHandler
{
    public function __construct(private FxWorkspaceReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetFxWorkspaceQuery $query): array
    {
        return match ($query->workspace) {
            'rates' => $this->service->rates($query->companyId, $query->actor, $query->filters), 'exposure' => $this->service->exposure($query->companyId, $query->actor), 'revaluations' => $this->service->revaluations($query->companyId, $query->actor), default => abort(404)
        };
    }
}
