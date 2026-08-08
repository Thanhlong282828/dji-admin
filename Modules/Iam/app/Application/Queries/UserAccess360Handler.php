<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\UserAccess360ViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class UserAccess360Handler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(UserAccess360Query $q): UserAccess360ViewModel
    {
        return $this->r->access360($q->companyId, $q->userId);
    }
}
