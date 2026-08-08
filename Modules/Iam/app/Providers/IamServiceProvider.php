<?php

namespace Modules\Iam\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Iam\Console\Commands\ConsumeEmployeeIamOutboxCommand;
use Modules\Iam\Console\Commands\ConsumeIamNotificationOutboxCommand;
use Modules\Iam\Console\Commands\ConsumeWorkflowIamOutboxCommand;
use Modules\Iam\Console\Commands\IamBrowserFixtureCommand;
use Modules\Iam\Domain\Contracts\AccessProvisioningAdapterInterface;
use Modules\Iam\Infrastructure\Persistence\SpatieAccessProvisioningAdapter;
use Nwidart\Modules\Support\ModuleServiceProvider;

final class IamServiceProvider extends ModuleServiceProvider
{
    /** @var string[] */
    protected array $commands = [IamBrowserFixtureCommand::class, ConsumeWorkflowIamOutboxCommand::class, ConsumeIamNotificationOutboxCommand::class, ConsumeEmployeeIamOutboxCommand::class];

    protected string $name = 'Iam';

    protected string $nameLower = 'iam';

    protected array $providers = [RouteServiceProvider::class];

    public function register(): void
    {
        parent::register();
        $this->app->bind(AccessProvisioningAdapterInterface::class, SpatieAccessProvisioningAdapter::class);
    }

    public function boot(): void
    {
        parent::boot();
        $schedule = $this->app->make(Schedule::class);
        $schedule->command('iam:consume-workflow')->everyMinute()->withoutOverlapping();
        $schedule->command('iam:consume-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('iam:consume-employee-lifecycle')->everyMinute()->withoutOverlapping();
    }
}
