<?php

namespace App\Support\Release;

final class RollbackCompatibilityChecker
{
    /** @return array{compatible:bool,checks:array{previous_image:bool,migration_compatible:bool,config_compatible:bool,database_backward_compatible:bool},strategy:string} */
    public function check(string $previousImage, bool $migrationCompatible, bool $configCompatible, bool $databaseBackwardCompatible): array
    {
        $image = preg_match('/^[a-zA-Z0-9._\/-]+:(?:[0-9a-f]{40}|[a-zA-Z0-9._-]+)(?:@sha256:[0-9a-f]{64})?$/', $previousImage) === 1;
        $checks = ['previous_image' => $image, 'migration_compatible' => $migrationCompatible, 'config_compatible' => $configCompatible, 'database_backward_compatible' => $databaseBackwardCompatible];

        return ['compatible' => ! in_array(false, $checks, true), 'checks' => $checks, 'strategy' => $migrationCompatible && $databaseBackwardCompatible ? 'application_rollback' : 'forward_fix'];
    }
}
