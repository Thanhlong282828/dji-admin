<?php

namespace Modules\Workflow\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Workflow\Console\Commands\EscalateOverdueWorkflowTasksCommand;
use Modules\Workflow\Console\Commands\WorkflowBrowserFixtureCommand;
use Modules\Workflow\Domain\Contracts\WorkflowActorResolverInterface;
use Modules\Workflow\Domain\Contracts\WorkflowCompletionHandlerInterface;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;
use Modules\Workflow\Infrastructure\Callbacks\OutboxWorkflowCompletionHandler;
use Modules\Workflow\Infrastructure\Persistence\EloquentWorkflowReadRepository;
use Modules\Workflow\Infrastructure\Resolvers\DatabaseWorkflowActorResolver;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WorkflowServiceProvider extends ModuleServiceProvider
{
    /** @var string[] */
    protected array $commands = [EscalateOverdueWorkflowTasksCommand::class, WorkflowBrowserFixtureCommand::class];

    public function register(): void
    {
        parent::register();
        $this->app->bind(WorkflowActorResolverInterface::class, DatabaseWorkflowActorResolver::class);
        $this->app->bind(WorkflowCompletionHandlerInterface::class, OutboxWorkflowCompletionHandler::class);
        $this->app->bind(WorkflowReadRepositoryInterface::class, EloquentWorkflowReadRepository::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->make(Schedule::class)->command('workflow:escalate-overdue')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Workflow';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'workflow';

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
