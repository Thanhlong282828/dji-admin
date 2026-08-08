<?php

namespace Modules\Leave\Providers;

use App\Listeners\InvalidateWorkforceReadModels;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Leave\Application\Listeners\SendLeaveWorkflowNotifications;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [WorkforceLeaveChanged::class => [InvalidateWorkforceReadModels::class, SendLeaveWorkflowNotifications::class]];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
