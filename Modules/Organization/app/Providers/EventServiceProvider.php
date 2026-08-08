<?php

namespace Modules\Organization\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Organization\Application\Listeners\OrganizationChangeListener;
use Modules\Organization\Domain\Events\CostCenterCreated;
use Modules\Organization\Domain\Events\CostCenterDeleted;
use Modules\Organization\Domain\Events\CostCenterUpdated;
use Modules\Organization\Domain\Events\JobPositionCreated;
use Modules\Organization\Domain\Events\JobPositionCriticalStatusChanged;
use Modules\Organization\Domain\Events\JobPositionDeleted;
use Modules\Organization\Domain\Events\JobPositionHeadcountChanged;
use Modules\Organization\Domain\Events\JobPositionUpdated;
use Modules\Organization\Domain\Events\OrganizationUnitCreated;
use Modules\Organization\Domain\Events\OrganizationUnitDeleted;
use Modules\Organization\Domain\Events\OrganizationUnitMoved;
use Modules\Organization\Domain\Events\OrganizationUnitUpdated;

final class EventServiceProvider extends ServiceProvider
{
    protected $listen = [CostCenterCreated::class => [OrganizationChangeListener::class], CostCenterUpdated::class => [OrganizationChangeListener::class], CostCenterDeleted::class => [OrganizationChangeListener::class], OrganizationUnitCreated::class => [OrganizationChangeListener::class], OrganizationUnitUpdated::class => [OrganizationChangeListener::class], OrganizationUnitMoved::class => [OrganizationChangeListener::class], OrganizationUnitDeleted::class => [OrganizationChangeListener::class], JobPositionCreated::class => [OrganizationChangeListener::class], JobPositionUpdated::class => [OrganizationChangeListener::class], JobPositionDeleted::class => [OrganizationChangeListener::class], JobPositionCriticalStatusChanged::class => [OrganizationChangeListener::class], JobPositionHeadcountChanged::class => [OrganizationChangeListener::class]];
}
