<?php

namespace Modules\Company\Application\Listeners;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Events\CompanyDeleted;
use Modules\Company\Domain\Events\CompanyFunctionalCurrencyConfigured;
use Modules\Company\Domain\Events\CompanyRestored;
use Modules\Company\Domain\Events\CompanyStatusChanged;
use Modules\Company\Domain\Events\CompanyUpdated;

final readonly class ClearCompanyCache
{
    public function __construct(private CacheRepository $cache) {}

    public function handle(CompanyCreated|CompanyUpdated|CompanyStatusChanged|CompanyDeleted|CompanyRestored|CompanyFunctionalCurrencyConfigured $event): void
    {
        $this->cache->increment('schema:admin:company:version');
        $this->cache->forget('schema:admin:company:stats');
        $this->cache->forget('schema:local:dashboard:0:overview');
        $this->cache->forget('schema:dashboard:0:overview');
    }
}
