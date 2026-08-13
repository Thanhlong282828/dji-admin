<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DevelopmentDataBootstrapCommandTest extends TestCase
{
    public function test_command_refuses_testing_database(): void
    {
        self::assertSame(Command::FAILURE, Artisan::call('app:bootstrap-development-data'));
    }

    public function test_command_refuses_browser_testing_database(): void
    {
        config()->set('app.env', 'local');
        config()->set('database.connections.mysql.database', 'schema_admin_browser_testing');

        self::assertSame(Command::FAILURE, Artisan::call('app:bootstrap-development-data'));
    }
}
