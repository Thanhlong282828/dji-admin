<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\ServiceProvider;

final class CRMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(module_path('CRM', 'resources/views'), 'crm');
    }
}
