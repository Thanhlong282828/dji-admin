<?php

namespace Tests;

use App\Support\TestGovernance\PhpunitDatabaseGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = config('database.default');
        $configuredDatabase = is_string($connection)
            ? config('database.connections.'.$connection.'.database')
            : null;
        $actualDatabase = $app->make('db')->connection()->getDatabaseName();

        PhpunitDatabaseGuard::assertPostLaravel(
            $app->environment(),
            is_string($configuredDatabase) ? $configuredDatabase : '',
            $actualDatabase,
        );

        return $app;
    }
}
