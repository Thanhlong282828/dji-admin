<?php

namespace Modules\Iam\Application\Queries;

use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Infrastructure\Persistence\IamReadRepository;

final readonly class SodViewHandler
{
    public function __construct(private IamReadRepository $r) {}

    public function handle(SodViewQuery $q): IamPageViewModel
    {
        return $this->r->sod($q->companyId);
    }
}
