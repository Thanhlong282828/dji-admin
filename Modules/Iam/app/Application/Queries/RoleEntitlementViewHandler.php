<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class RoleEntitlementViewHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(RoleEntitlementViewQuery $q): IamPageViewModel
    {
        return $this->r->catalog($q->companyId);
    }
}
