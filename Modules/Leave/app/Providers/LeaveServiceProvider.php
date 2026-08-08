<?php

namespace Modules\Leave\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Leave\Application\Jobs\AccrueLeaveBalancesJob;
use Modules\Leave\Application\Jobs\CarryForwardLeaveBalancesJob;
use Modules\Leave\Application\Jobs\ExpireLeaveBalancesJob;
use Modules\Leave\Domain\Repositories\LeaveReadRepositoryInterface;
use Modules\Leave\Domain\Services\MalwareScannerInterface;
use Modules\Leave\Infrastructure\Persistence\EloquentLeaveReadRepository;
use Modules\Leave\Infrastructure\Security\UnavailableMalwareScanner;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LeaveServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(LeaveReadRepositoryInterface::class, EloquentLeaveReadRepository::class);
        $this->app->bind(MalwareScannerInterface::class, UnavailableMalwareScanner::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Leave';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'leave';

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

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new AccrueLeaveBalancesJob)->dailyAt('00:20')->withoutOverlapping()->onOneServer();
        $schedule->job(new CarryForwardLeaveBalancesJob)->dailyAt('00:30')->withoutOverlapping()->onOneServer();
        $schedule->job(new ExpireLeaveBalancesJob)->dailyAt('00:40')->withoutOverlapping()->onOneServer();
    }

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
