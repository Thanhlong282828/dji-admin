<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class CreateFiscalYearCommand
{
    /** @param array{name:string,start_date:string,end_date:string} $data */
    public function __construct(public int $companyId, public User $actor, public array $data) {}
}
