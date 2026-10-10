<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class SubmitCustomerCreditNoteCommand
{
    public function __construct(public int $companyId, public int $creditNoteId, public User $actor) {}
}
