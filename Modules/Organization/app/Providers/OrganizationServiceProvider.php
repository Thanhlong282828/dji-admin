<?php

namespace Modules\Organization\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;
use Modules\Organization\Infrastructure\Persistence\Repositories\EloquentOrganizationReadRepository;
use Modules\Organization\Infrastructure\Persistence\Repositories\EloquentOrganizationWriteRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OrganizationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(OrganizationReadRepositoryInterface::class, EloquentOrganizationReadRepository::class);
        $this->app->bind(OrganizationWriteRepositoryInterface::class, EloquentOrganizationWriteRepository::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Organization';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'organization';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
