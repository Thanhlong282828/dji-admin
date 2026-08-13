<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class LocalDemoBootstrapGuardTest extends TestCase
{
    public function test_testing_environment_refuses_local_demo_even_on_testing_database(): void
    {
        self::assertSame(Command::FAILURE, Artisan::call('app:bootstrap-local-demo'));
    }

    public function test_testing_environment_refuses_local_main_database(): void
    {
        config()->set('database.connections.mysql.database', 'schema_admin');
        self::assertSame(Command::FAILURE, Artisan::call('app:bootstrap-local-demo'));
    }

    public function test_production_environment_refuses_local_demo(): void
    {
        config()->set('app.env', 'production');
        config()->set('database.connections.mysql.database', 'vps_admin_live');
        self::assertSame(Command::FAILURE, Artisan::call('app:bootstrap-local-demo'));
    }
}
