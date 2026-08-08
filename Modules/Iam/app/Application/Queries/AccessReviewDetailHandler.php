<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class AccessReviewDetailHandler
{
    public function __construct(private IamReadRepository $repository) {}

    public function handle(AccessReviewDetailQuery $query): IamPageViewModel
    {
        return $this->repository->review($query->companyId, $query->reviewId);
    }
}
