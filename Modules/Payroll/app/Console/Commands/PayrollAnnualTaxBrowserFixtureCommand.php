<?php

namespace Modules\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxBrowserFixtureService;

final class PayrollAnnualTaxBrowserFixtureCommand extends Command
{
    protected $signature = 'payroll:annual-tax-browser-fixture {--cleanup : Remove only marked Annual Tax browser fixtures}';

    protected $description = 'Create or remove a guarded Annual PIT browser fixture.';

    public function handle(ConnectionInterface $db, PayrollAnnualTaxBrowserFixtureService $fixtures): int
    {
        $environment = app()->environment();
        $this->line('APP_ENV='.$environment);
        $this->line('DB_DATABASE='.(string) $db->getDatabaseName());
        if ((bool) $this->option('cleanup')) {
            $result = $fixtures->cleanup($environment);
            $this->info(sprintf('Cleanup completed: companies=%d users=%d files=%d', $result['companies'], $result['users'], $result['files']));

            return self::SUCCESS;
        }
        $result = $fixtures->create($environment);
        $base = rtrim($this->text(config('app.url'), 'http://127.0.0.1:8000'), '/');
        $this->info('Annual PIT browser fixture created.');
        $this->line('Login email: '.$result['email']);
        $this->line('Local password: '.$result['password']);
        $this->line('List URL: '.$base.'/admin/payroll/annual-tax?company_id='.$result['company_id']);
        $this->line('Detail URL: '.$base.'/admin/payroll/annual-tax/'.$result['reconciliation_id'].'?company_id='.$result['company_id']);
        $this->line('Cleanup: php artisan payroll:annual-tax-browser-fixture --cleanup');

        return self::SUCCESS;
    }

    private function text(mixed $value, string $fallback): string
    {
        return is_string($value) || is_int($value) ? (string) $value : $fallback;
    }
}
