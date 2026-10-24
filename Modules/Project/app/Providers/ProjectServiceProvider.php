<?php

namespace Modules\Project\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

final class ProjectServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Project';

    protected string $nameLower = 'project';

    /** @var string[] */
    protected array $providers = [RouteServiceProvider::class];
}
