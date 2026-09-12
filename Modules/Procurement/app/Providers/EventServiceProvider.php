<?php

namespace Modules\Procurement\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Procurement\Application\Listeners\CompleteServiceAcceptanceWorkflow;
use Modules\Workflow\Domain\Events\WorkflowCompleted;

final class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        WorkflowCompleted::class => [CompleteServiceAcceptanceWorkflow::class],
    ];
}
