<?php

use App\Console\Commands\ProjectServeCommand;
use App\Contracts\ErrorTrackerInterface;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsureAccountAccessEnabled;
use App\Http\Middleware\RequestCorrelationContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withCommands([
        ProjectServeCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestCorrelationContext::class);
        $middleware->append(ApplySecurityHeaders::class);
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'account.enabled' => EnsureAccountAccessEnabled::class,
            'scope' => CheckToken::class,
            'scopes' => CheckTokenForAnyScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            if (app()->bound(ErrorTrackerInterface::class)) {
                app(ErrorTrackerInterface::class)->capture($exception, [
                    'environment' => app()->environment(),
                ]);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
