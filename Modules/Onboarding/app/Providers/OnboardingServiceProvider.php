<?php

namespace Modules\Onboarding\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Onboarding\Domain\Repositories\OnboardingReadRepositoryInterface;
use Modules\Onboarding\Domain\Services\OnboardingChecklistTemplateProviderInterface;
use Modules\Onboarding\Domain\Services\OnboardingProvisioningTemplateProviderInterface;
use Modules\Onboarding\Infrastructure\Persistence\Repositories\EloquentOnboardingReadRepository;
use Modules\Onboarding\Infrastructure\Templates\ConfigOnboardingChecklistTemplateProvider;
use Modules\Onboarding\Infrastructure\Templates\ConfigOnboardingProvisioningTemplateProvider;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OnboardingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->bind(OnboardingReadRepositoryInterface::class, EloquentOnboardingReadRepository::class);
        $this->app->bind(OnboardingChecklistTemplateProviderInterface::class, ConfigOnboardingChecklistTemplateProvider::class);
        $this->app->bind(OnboardingProvisioningTemplateProviderInterface::class, ConfigOnboardingProvisioningTemplateProvider::class);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Onboarding';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'onboarding';

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
