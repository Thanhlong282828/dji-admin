<?php

namespace Modules\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryFixtureService;

final class PayrollAnnualTaxDeliveryFixtureCommand extends Command
{
    protected $signature = 'payroll:annual-tax-delivery-fixture {--cleanup}';

    protected $description = 'Create payable, refund and balanced Annual Tax delivery browser fixtures.';

    public function handle(ConnectionInterface $db, PayrollAnnualTaxDeliveryFixtureService $service): int
    {
        $environment = app()->environment();
        $this->line('APP_ENV='.$environment);
        $this->line('DB_DATABASE='.(string) $db->getDatabaseName());
        if ((bool) $this->option('cleanup')) {
            $service->cleanup($environment);
            $this->info('Annual Tax delivery fixtures cleaned.');

            return self::SUCCESS;
        }
        $result = $service->create($environment);
        $configuredUrl = config('app.url');
        $baseUrl = is_string($configuredUrl) ? rtrim($configuredUrl, '/') : 'http://127.0.0.1:8000';
        $this->line('Login: '.$result['email'].' / '.$result['password']);
        foreach (['payable', 'refund', 'balanced'] as $type) {
            $this->line(ucfirst($type).' URL: '.$baseUrl.'/admin/payroll/annual-tax/'.$result[$type.'_id'].'?company_id='.$result['company_id']);
        }

        return self::SUCCESS;
    }
}
