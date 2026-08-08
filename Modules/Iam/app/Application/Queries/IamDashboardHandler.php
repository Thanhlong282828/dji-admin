<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class IamDashboardHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(IamDashboardQuery $q): IamPageViewModel
    {
        return $this->r->dashboard($q->companyId);
    }
}
