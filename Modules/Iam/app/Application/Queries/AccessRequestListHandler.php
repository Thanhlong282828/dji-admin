<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class AccessRequestListHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(AccessRequestListQuery $q): IamPageViewModel
    {
        return $this->r->requests($q->companyId);
    }
}
