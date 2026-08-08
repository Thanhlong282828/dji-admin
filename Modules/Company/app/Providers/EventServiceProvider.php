<?php

namespace Modules\Company\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Company\Application\Listeners\ClearCompanyCache;
use Modules\Company\Application\Listeners\WriteCompanyActivityLog;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Events\CompanyDeleted;
use Modules\Company\Domain\Events\CompanyRestored;
use Modules\Company\Domain\Events\CompanyStatusChanged;
use Modules\Company\Domain\Events\CompanyUpdated;

final class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string,array<int,class-string>> */
    protected $listen = [CompanyCreated::class => [ClearCompanyCache::class, WriteCompanyActivityLog::class], CompanyUpdated::class => [ClearCompanyCache::class, WriteCompanyActivityLog::class], CompanyStatusChanged::class => [ClearCompanyCache::class, WriteCompanyActivityLog::class], CompanyDeleted::class => [ClearCompanyCache::class, WriteCompanyActivityLog::class], CompanyRestored::class => [ClearCompanyCache::class, WriteCompanyActivityLog::class]];
}
