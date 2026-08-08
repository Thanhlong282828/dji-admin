<?php

namespace Modules\Performance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;
use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;
use Modules\Performance\Infrastructure\Persistence\EloquentCareerReadRepository;
use Modules\Performance\Infrastructure\Persistence\EloquentPerformanceReadRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PerformanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(PerformanceReadRepositoryInterface::class, EloquentPerformanceReadRepository::class);
        $this->app->bind(CareerReadRepositoryInterface::class, EloquentCareerReadRepository::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Performance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'performance';

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
