<?php

namespace Modules\Audit\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Audit\Console\Commands\AuditBrowserFixtureCommand;
use Modules\Audit\Console\Commands\ConsumeAuditSourcesCommand;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Services\EnterpriseAuditService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Audit';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'audit';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [AuditBrowserFixtureCommand::class, ConsumeAuditSourcesCommand::class];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();
        $this->app->bind(AuditRecorderInterface::class, EnterpriseAuditService::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->make(Schedule::class)->command('audit:consume-sources --limit=100')->everyMinute()->withoutOverlapping();
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
