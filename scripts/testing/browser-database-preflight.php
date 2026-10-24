<?php

declare(strict_types=1);

use App\Support\TestGovernance\BrowserDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

BrowserDatabaseGuard::assertRaw((string) getenv('APP_ENV'), (string) getenv('DB_DATABASE'));

if (is_file($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Browser safety guard: default Laravel config cache must be cleared before browser testing.');
}

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
$configuredDatabase = is_string($connection) ? config('database.connections.'.$connection.'.database') : null;
$actualDatabase = $app->make('db')->connection()->getDatabaseName();

BrowserDatabaseGuard::assertActual(
    $app->environment(),
    is_string($configuredDatabase) ? $configuredDatabase : '',
    is_string($actualDatabase) ? $actualDatabase : '',
);

fwrite(STDOUT, "Browser database preflight: PASS\n");
