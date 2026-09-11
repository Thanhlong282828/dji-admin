<?php

namespace Modules\Employee\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Employee\Application\Listeners\EmployeeAssignmentChangeListener;
use Modules\Employee\Application\Listeners\EmployeeChangeListener;
use Modules\Employee\Application\Listeners\SendHrOperationsNotification;
use Modules\Employee\Domain\Events\EmployeeAssignmentChanged;
use Modules\Employee\Domain\Events\EmployeeCreated;
use Modules\Employee\Domain\Events\EmployeeDeleted;
use Modules\Employee\Domain\Events\EmployeeRestored;
use Modules\Employee\Domain\Events\EmployeeUpdated;
use Modules\Employee\Domain\Events\HrOperationsChanged;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        EmployeeAssignmentChanged::class => [EmployeeAssignmentChangeListener::class],
        EmployeeCreated::class => [EmployeeChangeListener::class],
        EmployeeUpdated::class => [EmployeeChangeListener::class],
        EmployeeDeleted::class => [EmployeeChangeListener::class],
        EmployeeRestored::class => [EmployeeChangeListener::class],
        HrOperationsChanged::class => [SendHrOperationsNotification::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
