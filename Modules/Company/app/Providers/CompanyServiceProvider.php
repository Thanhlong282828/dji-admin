<?php

namespace Modules\Company\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;
use Modules\Company\Infrastructure\Persistence\Repositories\EloquentCompanyReadRepository;
use Modules\Company\Infrastructure\Persistence\Repositories\EloquentCompanyWriteRepository;
use Modules\Company\Policies\CompanyPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CompanyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(CompanyReadRepositoryInterface::class, EloquentCompanyReadRepository::class);
        $this->app->bind(CompanyWriteRepositoryInterface::class, EloquentCompanyWriteRepository::class);
    }

    public function boot(): void
    {
        parent::boot();
        Gate::policy(Company::class, CompanyPolicy::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Company';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'company';

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
