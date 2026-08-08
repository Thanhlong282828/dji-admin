<?php

namespace Modules\Dashboard\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;
use Modules\Dashboard\Infrastructure\Persistence\Repositories\EloquentDashboardReadRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DashboardServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(DashboardReadRepositoryInterface::class, EloquentDashboardReadRepository::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Dashboard';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'dashboard';

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
