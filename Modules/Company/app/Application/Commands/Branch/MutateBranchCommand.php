<?php

namespace Modules\Company\Application\Commands\Branch;

use App\Models\User;

final readonly class MutateBranchCommand
{
    /** @param array{company_id:int,leader_employee_id:?int,code?:string,name?:string,address?:?string,province?:?string,status?:string,timezone?:string} $data */
    public function __construct(public string $operation, public array $data, public User $actor, public ?int $branchId = null) {}
}
