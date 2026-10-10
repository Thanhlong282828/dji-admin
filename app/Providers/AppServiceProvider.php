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
use App\Support\Identity\LocalPhoneVerificationProvider;
use App\Support\Identity\PhoneVerificationProviderInterface;
use App\Support\Media\CloudflareImagesStorage;
use App\Support\Media\LocalMediaStorage;
use App\Support\Media\MediaStorageInterface;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use App\Support\Secrets\ConfiguredSecretProvider;
use App\Support\Secrets\SecretProviderInterface;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

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
        $localDatabaseValue = config('environment-safety.local_database');
        $productionDatabaseValue = config('release.production_database');
        EnvironmentSafetyGuard::assertSafe(
            is_string($environmentValue) ? $environmentValue : 'production',
            (bool) config('app.debug'),
            $database,
            $protectedDatabases,
            (bool) config('environment-safety.require_testing_suffix', true),
            is_string($localDatabaseValue) ? $localDatabaseValue : 'schema_admin',
            is_string($productionDatabaseValue) ? $productionDatabaseValue : '',
        );

        $this->app->bind(WorkforceNotificationChannelSenderInterface::class, LaravelWorkforceNotificationChannelSender::class);
        $this->app->singleton(ErrorTrackerInterface::class, NullErrorTracker::class);
        $this->app->bind(SecretProviderInterface::class, ConfiguredSecretProvider::class);
        $this->app->bind(PhoneVerificationProviderInterface::class, function (): PhoneVerificationProviderInterface {
            if (config('services.phone_verification.driver') !== 'local') {
                throw new RuntimeException('Phone verification provider chưa được cấu hình.');
            }

            return new LocalPhoneVerificationProvider;
        });
        $this->app->bind(MediaStorageInterface::class, fn (Application $app): MediaStorageInterface => match (config('media.driver')) {
            'cloudflare-images' => $app->make(CloudflareImagesStorage::class),
            'local' => $app->make(LocalMediaStorage::class),
            default => throw new RuntimeException('Media driver không hợp lệ.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->hasRole('Super Admin')) {
                return null;
            }

            $isRegisteredPermission = app(PermissionRegistrar::class)
                ->getPermissions()
                ->where('guard_name', 'web')
                ->where('name', $ability)
                ->isNotEmpty();

            return $isRegisteredPermission ? true : null;
        });

        Passport::tokensCan([
            'company:read' => 'Đọc dữ liệu công ty',
            'company:write' => 'Thay đổi dữ liệu công ty',
            'organization:read' => 'Đọc cấu trúc tổ chức',
            'onboarding:read' => 'Đọc dữ liệu onboarding',
            'onboarding:write' => 'Thay đổi dữ liệu onboarding',
            'performance:read' => 'Đọc dữ liệu hiệu suất',
            'performance:write' => 'Thay đổi dữ liệu hiệu suất',
            'recruitment:read' => 'Đọc dữ liệu tuyển dụng',
            'recruitment:write' => 'Thay đổi dữ liệu tuyển dụng',
        ]);
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::authorizationView('auth.oauth.authorize');

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
