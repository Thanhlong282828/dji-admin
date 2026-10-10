<?php

namespace Modules\Payroll\Application\Queries;

use App\Models\User;

final readonly class GetPayrollProcessHelpQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
