<?php

namespace App\Providers;

use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Policies\Workforce\WorkforceAuthorizationPolicy;
use App\Policies\Workforce\WorkforcePolicyGateRegistrar;
use App\Services\LaravelWorkforceNotificationChannelSender;
use App\Support\EnvironmentSafetyGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        $protectedValue = config('environment-safety.protected_databases', ['schema_admin']);
        $protectedDatabases = is_array($protectedValue)
            ? array_values(array_filter($protectedValue, static fn (mixed $name): bool => is_string($name)))
            : ['schema_admin'];
        $environmentValue = config('app.env');
        EnvironmentSafetyGuard::assertSafe(
            is_string($environmentValue) ? $environmentValue : 'production',
            (bool) config('app.debug'),
            $database,
            $protectedDatabases,
            (bool) config('environment-safety.require_testing_suffix', true),
        );

        $this->app->bind(WorkforceNotificationChannelSenderInterface::class, LaravelWorkforceNotificationChannelSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WorkforcePolicyGateRegistrar::register();
        Gate::define('workforce.company', function (User $user, string $permission, int $companyId): bool {
            return app(WorkforceAuthorizationPolicy::class)->company($user, $permission, $companyId);
        });
    }
}
