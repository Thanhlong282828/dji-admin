<?php

namespace Modules\Notification\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Notification\Console\Commands\ConsumeWorkflowNotificationOutboxCommand;
use Modules\Notification\Console\Commands\NotificationBrowserFixtureCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NotificationServiceProvider extends ModuleServiceProvider
{
    /** @var string[] */
    protected array $commands = [ConsumeWorkflowNotificationOutboxCommand::class, NotificationBrowserFixtureCommand::class];

    public function boot(): void
    {
        parent::boot();
        $this->app->make(Schedule::class)->command('notification:consume-workflow-outbox')->everyMinute()->withoutOverlapping();
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Notification';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'notification';

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
