<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class AccessHistoryViewHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(AccessHistoryViewQuery $q): IamPageViewModel
    {
        return $this->r->history($q->companyId);
    }
}
