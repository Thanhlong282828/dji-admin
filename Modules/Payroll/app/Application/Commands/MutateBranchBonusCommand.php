<?php

namespace Modules\Payroll\Application\Commands;

use App\Models\User;

final readonly class MutateBranchBonusCommand
{
    /** @param array{company_id:int,branch_id:int,employee_id:int,bonus_reference:string,amount_minor:int,reason_category:string,reason:string,effective_period:string}|null $createData */
    public function __construct(public User $actor, public string $operation, public ?array $createData = null, public ?int $companyId = null, public ?int $bonusId = null, public ?string $action = null, public ?int $version = null, public ?string $comment = null) {}
}
