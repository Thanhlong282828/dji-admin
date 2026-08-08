<?php

namespace Modules\Recruitment\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Recruitment\Application\Listeners\HandleCandidateHireCompleted;
use Modules\Recruitment\Application\Listeners\SendRecruitmentWorkflowNotifications;
use Modules\Recruitment\Domain\Events\CandidateHireCompleted;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        CandidateHireCompleted::class => [
            HandleCandidateHireCompleted::class,
        ],
        RecruitmentWorkflowChanged::class => [
            SendRecruitmentWorkflowNotifications::class,
        ],
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
