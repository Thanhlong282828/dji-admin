<?php

namespace Modules\Recruitment\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Recruitment\Application\Jobs\ExpireCandidateOffersJob;
use Modules\Recruitment\Application\Jobs\SendInterviewRemindersJob;
use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;
use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;
use Modules\Recruitment\Infrastructure\Persistence\Repositories\EloquentRecruitmentReadRepository;
use Modules\Recruitment\Infrastructure\Persistence\Repositories\EloquentRecruitmentWriteRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RecruitmentServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(RecruitmentReadRepositoryInterface::class, EloquentRecruitmentReadRepository::class);
        $this->app->bind(RecruitmentWriteRepositoryInterface::class, EloquentRecruitmentWriteRepository::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Recruitment';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'recruitment';

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
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new ExpireCandidateOffersJob)->everyFiveMinutes()->withoutOverlapping();
        $schedule->job(new SendInterviewRemindersJob)->everyFiveMinutes()->withoutOverlapping();
    }
}
