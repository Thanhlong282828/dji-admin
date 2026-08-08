<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\LaravelCommandBus;
use Modules\Core\Application\Buses\LaravelQueryBus;
use Modules\Core\Application\Buses\QueryBusInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->singleton(CommandBusInterface::class, LaravelCommandBus::class);
        $this->app->singleton(QueryBusInterface::class, LaravelQueryBus::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

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
