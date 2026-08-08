<?php

namespace Modules\Audit\Application\Queries;

use Modules\Audit\Application\DTOs\AuditPageViewModel;
use Modules\Audit\Infrastructure\Persistence\AuditReadRepository;

final readonly class AuditPageHandler
{
    public function __construct(private AuditReadRepository $repository) {}

    public function handle(AuditPageQuery $query): AuditPageViewModel
    {
        return $this->repository->page($query->companyId, $query->page, $query->id, $query->search);
    }
}
