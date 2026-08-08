<?php

namespace Modules\Integration\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Integration\Console\Commands\IntegrationBrowserFixtureCommand;
use Modules\Integration\Domain\Contracts\IntegrationAdapterRegistryInterface;
use Modules\Integration\Domain\Contracts\IntegrationCredentialProviderInterface;
use Modules\Integration\Infrastructure\Adapters\IntegrationAdapterRegistry;
use Modules\Integration\Infrastructure\Security\EncryptedCredentialProvider;
use Nwidart\Modules\Support\ModuleServiceProvider;

class IntegrationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Integration';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'integration';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [IntegrationBrowserFixtureCommand::class];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();
        $this->app->bind(IntegrationCredentialProviderInterface::class, EncryptedCredentialProvider::class);
        $this->app->singleton(IntegrationAdapterRegistryInterface::class, IntegrationAdapterRegistry::class);
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
