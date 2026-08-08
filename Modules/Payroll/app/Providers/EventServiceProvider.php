<?php

namespace Modules\Payroll\Providers;

use App\Listeners\InvalidateWorkforceReadModels;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Payroll\Application\Listeners\SendPayrollRunNotifications;
use Modules\Payroll\Application\Listeners\SendPayslipPublishedNotification;
use Modules\Payroll\Domain\Events\PayrollConfigurationChanged;
use Modules\Payroll\Domain\Events\PayrollPeriodChanged;
use Modules\Payroll\Domain\Events\PayrollRunChanged;
use Modules\Payroll\Domain\Events\PayslipChanged;
use Modules\Payroll\Domain\Events\WorkforcePayrollInputChanged;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [WorkforcePayrollInputChanged::class => [InvalidateWorkforceReadModels::class], PayrollPeriodChanged::class => [InvalidateWorkforceReadModels::class], PayrollRunChanged::class => [InvalidateWorkforceReadModels::class, SendPayrollRunNotifications::class], PayrollConfigurationChanged::class => [InvalidateWorkforceReadModels::class], PayslipChanged::class => [SendPayslipPublishedNotification::class]];

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
