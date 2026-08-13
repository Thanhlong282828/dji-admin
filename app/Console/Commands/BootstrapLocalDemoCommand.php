<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class BootstrapLocalDemoCommand extends Command
{
    protected $signature = 'app:bootstrap-local-demo
        {--email=admin@local.test : Local demo Super Admin email}
        {--password=LocalAdmin123! : LOCAL DEMO ONLY password}';

    protected $description = 'Idempotently orchestrate the guarded synthetic local schema_admin dataset';

    public function handle(): int
    {
        $defaultConnection = config('database.default');
        if (! is_string($defaultConnection) || $defaultConnection === '') {
            $this->components->error('The default database connection is not configured.');

            return self::FAILURE;
        }

        $database = config('database.connections.'.$defaultConnection.'.database');
        if (! app()->environment('local') || $database !== 'schema_admin') {
            $this->components->error('Local demo bootstrap requires APP_ENV=local and DB_DATABASE=schema_admin.');

            return self::FAILURE;
        }

        $admin = $this->call('app:bootstrap-development-admin', [
            '--email' => $this->option('email'),
            '--password' => $this->option('password'),
        ]);
        if ($admin !== self::SUCCESS) {
            return self::FAILURE;
        }

        $data = $this->call('app:bootstrap-development-data');
        if ($data !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->call('cache:clear');
        $this->components->info('Synthetic local demo bootstrap completed without external delivery.');

        return self::SUCCESS;
    }
}
