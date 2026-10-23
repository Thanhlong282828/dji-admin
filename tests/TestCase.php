<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase can roll back permission rows while a persistent
        // cache still holds their former IDs. Clear Spatie's cache after
        // Laravel has completed trait setup so each test resolves current rows.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
