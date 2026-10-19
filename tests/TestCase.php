<?php

namespace Tests;

use App\Support\TestGovernance\PhpunitDatabaseGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase can recreate permission rows with different IDs while
        // Spatie's singleton still holds models from the preceding test. Clear
        // that process-local cache after Laravel has completed trait setup so
        // role/permission relations always target the current database rows.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

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
