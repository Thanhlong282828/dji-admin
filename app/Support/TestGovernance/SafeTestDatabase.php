<?php

namespace App\Support\TestGovernance;

final class SafeTestDatabase
{
    public function allows(string $environment, string $database): bool
    {
        if ($environment !== 'testing') {
            return false;
        }

        return $database === 'schema_admin_testing'
            || str_starts_with($database, 'schema_admin_testing_')
            || $database === 'schema_admin_browser_testing';
    }
}
