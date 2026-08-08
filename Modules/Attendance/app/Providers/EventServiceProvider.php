<?php

namespace Modules\Attendance\Providers;

use App\Listeners\InvalidateWorkforceReadModels;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Attendance\Application\Listeners\SendAttendanceWorkflowNotifications;
use Modules\Attendance\Application\Listeners\SendOvertimeWorkflowNotifications;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;
use Modules\Attendance\Domain\Events\WorkforceOvertimeChanged;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        WorkforceAttendanceChanged::class => [InvalidateWorkforceReadModels::class, SendAttendanceWorkflowNotifications::class],
        WorkforceOvertimeChanged::class => [InvalidateWorkforceReadModels::class, SendOvertimeWorkflowNotifications::class],
    ];

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
