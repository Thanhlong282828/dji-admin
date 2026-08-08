<?php

namespace Modules\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Services\PayrollAdjustmentBrowserFixtureService;

final class PayrollAdjustmentBrowserFixtureCommand extends Command
{
    protected $signature = 'payroll:adjustment-browser-fixture {--scenario=positive : positive or negative} {--cleanup : Remove only marked browser fixtures}';

    protected $description = 'Create or remove a guarded local/testing Payroll Adjustment browser fixture.';

    public function handle(ConnectionInterface $db, PayrollAdjustmentBrowserFixtureService $fixtures): int
    {
        $environment = app()->environment();
        $scenario = $this->text($this->option('scenario'), 'positive');
        $connection = $this->text(config('database.default'), 'mysql');
        $this->line('APP_ENV='.$environment);
        $this->line('DB_CONNECTION='.$connection);
        $this->line('DB_HOST='.$this->text(config('database.connections.'.$connection.'.host'), 'unknown'));
        $this->line('DB_PORT='.$this->text(config('database.connections.'.$connection.'.port'), 'unknown'));
        $this->line('DB_DATABASE='.(string) $db->getDatabaseName());

        if ((bool) $this->option('cleanup')) {
            $result = $fixtures->cleanup($environment, $scenario);
            $this->info(sprintf('Cleanup completed: companies=%d users=%d files=%d', $result['companies'], $result['users'], $result['files']));

            return self::SUCCESS;
        }

        $result = $fixtures->create($environment, $scenario);
        $base = rtrim($this->text(config('app.url'), 'http://127.0.0.1:8000'), '/');
        $this->info('Browser fixture created.');
        $this->line('Login email: '.$result['email']);
        $this->line('Unauthorized login email: '.$result['unauthorized_email']);
        $this->line('Local password: '.$result['password']);
        $this->line('Adjustment reference: '.$result['adjustment_number']);
        $this->line('Adjustment Detail URL: '.$base.'/admin/payroll/adjustments/'.$result['adjustment_id'].'?company_id='.$result['company_id']);
        $this->line('Statement Download URL: '.$base.'/admin/payroll/adjustments/'.$result['adjustment_id'].'/statement/download?company_id='.$result['company_id']);
        $this->line('Cleanup: php artisan payroll:adjustment-browser-fixture --scenario='.$scenario.' --cleanup');

        return self::SUCCESS;
    }

    private function text(mixed $value, string $fallback): string
    {
        return is_string($value) || is_int($value) ? (string) $value : $fallback;
    }
}
