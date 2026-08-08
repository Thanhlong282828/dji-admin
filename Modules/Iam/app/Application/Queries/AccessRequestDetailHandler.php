<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class AccessRequestDetailHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(AccessRequestDetailQuery $q): IamPageViewModel
    {
        return $this->r->request($q->companyId, $q->requestId);
    }
}
