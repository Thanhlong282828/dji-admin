#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Support\TestGovernance\CoverageInventory;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    exit(CoverageInventory::run($argv, getcwd()));
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Coverage diagnostics failed: '.$throwable->getMessage().PHP_EOL);
    exit(2);
}
