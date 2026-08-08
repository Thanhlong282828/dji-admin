<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class AccessReviewViewHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(AccessReviewViewQuery $q): IamPageViewModel
    {
        return $this->r->reviews($q->companyId);
    }
}
