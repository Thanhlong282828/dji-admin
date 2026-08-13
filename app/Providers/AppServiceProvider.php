<?php

namespace App\Providers;

use App\Contracts\ErrorTrackerInterface;
use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Policies\Workforce\WorkforceAuthorizationPolicy;
use App\Policies\Workforce\WorkforcePolicyGateRegistrar;
use App\Services\LaravelWorkforceNotificationChannelSender;
use App\Services\NullErrorTracker;
use App\Support\Broadcasting\FailOpenRealtimeBroadcaster;
use App\Support\EnvironmentSafetyGuard;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use App\Support\Secrets\ConfiguredSecretProvider;
use App\Support\Secrets\SecretProviderInterface;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $connectionValue = config('database.default');
        $connection = is_string($connectionValue) ? $connectionValue : '';
        $databaseValue = config('database.connections.'.$connection.'.database');
        $database = is_string($databaseValue) ? $databaseValue : '';
        $protectedValue = config('environment-safety.protected_databases', []);
        $protectedDatabases = is_array($protectedValue)
            ? array_values(array_filter($protectedValue, static fn (mixed $name): bool => is_string($name)))
            : [];
        $environmentValue = config('app.env');
        EnvironmentSafetyGuard::assertSafe(
            is_string($environmentValue) ? $environmentValue : 'production',
            (bool) config('app.debug'),
            $database,
            $protectedDatabases,
            (bool) config('environment-safety.require_testing_suffix', true),
        );

        $this->app->bind(WorkforceNotificationChannelSenderInterface::class, LaravelWorkforceNotificationChannelSender::class);
        $this->app->singleton(ErrorTrackerInterface::class, NullErrorTracker::class);
        $this->app->bind(SecretProviderInterface::class, ConfiguredSecretProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::extend('fail-open-reverb', function (Application $app, array $config): FailOpenRealtimeBroadcaster {
            $transport = $app->make(BroadcastManager::class)->connection('reverb_transport');
            if (! $transport instanceof BroadcasterContract) {
                throw new RuntimeException('Reverb transport does not implement the broadcaster contract.');
            }

            return new FailOpenRealtimeBroadcaster(
                $transport,
                $app->make(RuntimeConfigurationService::class),
                $app->make(LoggerInterface::class),
            );
        });

        WorkforcePolicyGateRegistrar::register();
        Gate::define('workforce.company', function (User $user, string $permission, int $companyId): bool {
            return app(WorkforceAuthorizationPolicy::class)->company($user, $permission, $companyId);
        });
    }
}
