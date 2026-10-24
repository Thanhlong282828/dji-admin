<?php

namespace Modules\Company\Application\Listeners;

use App\Models\User;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Events\CompanyDeleted;
use Modules\Company\Domain\Events\CompanyFunctionalCurrencyConfigured;
use Modules\Company\Domain\Events\CompanyRestored;
use Modules\Company\Domain\Events\CompanyStatusChanged;
use Modules\Company\Domain\Events\CompanyUpdated;

final class WriteCompanyActivityLog
{
    public function handle(CompanyCreated|CompanyUpdated|CompanyStatusChanged|CompanyDeleted|CompanyRestored|CompanyFunctionalCurrencyConfigured $event): void
    {
        $action = match (true) {
            $event instanceof CompanyCreated => 'created',$event instanceof CompanyUpdated => 'updated',$event instanceof CompanyStatusChanged => 'status_changed',$event instanceof CompanyDeleted => 'deleted',$event instanceof CompanyFunctionalCurrencyConfigured => 'functional_currency_configured',default => 'restored'
        };
        $properties = ['company_id' => $event->company->id, 'code' => $event->company->code];
        if ($event instanceof CompanyFunctionalCurrencyConfigured) {
            $properties += ['old_value' => null, 'new_value' => $event->currency, 'configured_at' => now()->toIso8601String()];
        }
        activity('company')->causedBy(User::query()->find($event->actorId))->performedOn($event->company)->event($action)->withProperties($properties)->log("company.{$action}");
    }
}
