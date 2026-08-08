<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class PrivilegedAccessViewHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(PrivilegedAccessViewQuery $q): IamPageViewModel
    {
        return $this->r->privileged($q->companyId);
    }
}
