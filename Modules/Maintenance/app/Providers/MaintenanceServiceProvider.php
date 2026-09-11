<?php

namespace Modules\Maintenance\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

final class MaintenanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Maintenance';

    protected string $nameLower = 'maintenance';

    /** @var string[] */
    protected array $providers = [RouteServiceProvider::class];
}
