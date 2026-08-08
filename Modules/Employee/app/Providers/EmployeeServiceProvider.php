<?php

namespace Modules\Employee\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Employee\Application\Jobs\ExpireEmploymentContractsJob;
use Modules\Employee\Application\Jobs\NotifyExpiringDataConsentsJob;
use Modules\Employee\Application\Jobs\NotifyExpiringEmployeeCredentialsJob;
use Modules\Employee\Application\Jobs\NotifyExpiringEmployeeDocumentsJob;
use Modules\Employee\Console\Commands\HRGovernanceBrowserFixtureCommand;
use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;
use Modules\Employee\Domain\Repositories\EmployeeReadRepositoryInterface;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;
use Modules\Employee\Domain\Services\MalwareScannerInterface;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentEmployeeLifecycleReadRepository;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentEmployeeReadRepository;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentEmployeeWriteRepository;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentHRGovernanceReadRepository;
use Modules\Employee\Infrastructure\Security\ClamAvMalwareScanner;
use Modules\Employee\Infrastructure\Security\NullMalwareScanner;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EmployeeServiceProvider extends ModuleServiceProvider
{
    /** @var string[] */
    protected array $commands = [HRGovernanceBrowserFixtureCommand::class];

    public function boot(): void
    {
        parent::boot();
        $schedule = $this->app->make(Schedule::class);
        $schedule->job(new ExpireEmploymentContractsJob)->dailyAt('00:20')->withoutOverlapping();
        $schedule->job(new NotifyExpiringEmployeeCredentialsJob)->dailyAt('07:00')->withoutOverlapping();
        $schedule->job(new NotifyExpiringDataConsentsJob)->dailyAt('07:10')->withoutOverlapping();
        $schedule->job(new NotifyExpiringEmployeeDocumentsJob)->dailyAt('07:20')->withoutOverlapping();
    }

    public function register(): void
    {
        parent::register();
        $this->app->bind(EmployeeReadRepositoryInterface::class, EloquentEmployeeReadRepository::class);
        $this->app->bind(EmployeeWriteRepositoryInterface::class, EloquentEmployeeWriteRepository::class);
        $this->app->bind(EmployeeLifecycleReadRepositoryInterface::class, EloquentEmployeeLifecycleReadRepository::class);
        $this->app->bind(HRGovernanceReadRepositoryInterface::class, EloquentHRGovernanceReadRepository::class);
        $this->app->bind(MalwareScannerInterface::class, fn () => config('employee.malware_scanner') === 'clamav' ? new ClamAvMalwareScanner : new NullMalwareScanner);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Employee';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'employee';

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
