<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DevelopmentAdminBootstrapCommandTest extends TestCase
{
    public function test_command_refuses_testing_environment(): void
    {
        $exitCode = Artisan::call('app:bootstrap-development-admin', [
            '--email' => 'admin@local.test',
            '--password' => 'fixture-only',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function test_command_refuses_deprecated_database_even_when_environment_is_local(): void
    {
        config()->set('app.env', 'local');
        config()->set('database.connections.mysql.database', 'schema_admin_development');

        $exitCode = Artisan::call('app:bootstrap-development-admin', [
            '--email' => 'admin@local.test',
            '--password' => 'fixture-only',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
    }
}
