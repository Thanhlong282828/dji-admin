<?php

namespace Modules\Attendance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Attendance\Application\Jobs\DetectAttendanceAnomaliesJob;
use Modules\Attendance\Application\Jobs\GenerateAttendanceDailyRecordsJob;
use Modules\Attendance\Application\Jobs\RecalculateOpenAttendanceRecordsJob;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;
use Modules\Attendance\Domain\Repositories\AttendanceWriteRepositoryInterface;
use Modules\Attendance\Domain\Repositories\OvertimeReadRepositoryInterface;
use Modules\Attendance\Domain\Repositories\ScheduleRuleReadRepositoryInterface;
use Modules\Attendance\Domain\Repositories\WorkforceReadRepositoryInterface;
use Modules\Attendance\Domain\Services\AttendanceDeviceProviderInterface;
use Modules\Attendance\Infrastructure\Devices\NullAttendanceDeviceProvider;
use Modules\Attendance\Infrastructure\Persistence\EloquentAttendanceWorkflowRepository;
use Modules\Attendance\Infrastructure\Persistence\EloquentAttendanceWriteRepository;
use Modules\Attendance\Infrastructure\Persistence\EloquentOvertimeReadRepository;
use Modules\Attendance\Infrastructure\Persistence\EloquentScheduleRuleReadRepository;
use Modules\Attendance\Infrastructure\Persistence\EloquentWorkforceReadRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(AttendanceWriteRepositoryInterface::class, EloquentAttendanceWriteRepository::class);
        $this->app->bind(WorkforceReadRepositoryInterface::class, EloquentWorkforceReadRepository::class);
        $this->app->bind(AttendanceWorkflowRepositoryInterface::class, EloquentAttendanceWorkflowRepository::class);
        $this->app->bind(OvertimeReadRepositoryInterface::class, EloquentOvertimeReadRepository::class);
        $this->app->bind(ScheduleRuleReadRepositoryInterface::class, EloquentScheduleRuleReadRepository::class);
        $this->app->bind(AttendanceDeviceProviderInterface::class, NullAttendanceDeviceProvider::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Attendance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'attendance';

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

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new GenerateAttendanceDailyRecordsJob)->dailyAt('00:05')->withoutOverlapping()->onOneServer();
        $schedule->job(new RecalculateOpenAttendanceRecordsJob)->everyTenMinutes()->withoutOverlapping()->onOneServer();
        $schedule->job(new DetectAttendanceAnomaliesJob)->hourly()->withoutOverlapping()->onOneServer();
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
