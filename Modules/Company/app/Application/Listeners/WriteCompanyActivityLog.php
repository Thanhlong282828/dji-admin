<?php

namespace Modules\Company\Application\Listeners;

use App\Models\User;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Events\CompanyDeleted;
use Modules\Company\Domain\Events\CompanyRestored;
use Modules\Company\Domain\Events\CompanyStatusChanged;
use Modules\Company\Domain\Events\CompanyUpdated;

final class WriteCompanyActivityLog
{
    public function handle(CompanyCreated|CompanyUpdated|CompanyStatusChanged|CompanyDeleted|CompanyRestored $event): void
    {
        $action = match (true) {
            $event instanceof CompanyCreated => 'created',$event instanceof CompanyUpdated => 'updated',$event instanceof CompanyStatusChanged => 'status_changed',$event instanceof CompanyDeleted => 'deleted',default => 'restored'
        };
        activity('company')->causedBy(User::query()->find($event->actorId))->performedOn($event->company)->event($action)->withProperties(['company_id' => $event->company->id, 'code' => $event->company->code])->log("company.{$action}");
    }
}
