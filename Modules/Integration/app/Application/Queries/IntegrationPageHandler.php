<?php

namespace Modules\Integration\Application\Queries;

use Modules\Integration\Application\DTOs\IntegrationPageViewModel;
use Modules\Integration\Infrastructure\Persistence\IntegrationReadRepository;

final readonly class IntegrationPageHandler
{
    public function __construct(private IntegrationReadRepository $repository) {}

    public function handle(IntegrationPageQuery $q): IntegrationPageViewModel
    {
        return $this->repository->page($q->companyId, $q->page, $q->id);
    }
}
