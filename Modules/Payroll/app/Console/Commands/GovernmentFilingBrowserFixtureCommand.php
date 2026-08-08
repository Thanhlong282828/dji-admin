<?php

namespace Modules\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Services\GovernmentFilingBrowserFixtureService;

final class GovernmentFilingBrowserFixtureCommand extends Command
{
    protected $signature = 'payroll:government-filing-browser-fixture {--cleanup}';

    protected $description = 'Create or remove a guarded Government Filing browser fixture.';

    public function handle(ConnectionInterface $db, GovernmentFilingBrowserFixtureService $service): int
    {
        $environment = app()->environment();
        $this->line('APP_ENV='.$environment);
        $this->line('DB_DATABASE='.(string) $db->getDatabaseName());
        if ((bool) $this->option('cleanup')) {
            $service->cleanup($environment);
            $this->info('Government Filing fixture cleaned.');

            return self::SUCCESS;
        }
        $data = $service->create($environment);
        $configuredUrl = config('app.url');
        $baseUrl = rtrim(is_string($configuredUrl) ? $configuredUrl : 'http://127.0.0.1:8000', '/');
        $this->line('Login: '.$data['email'].' / '.$data['password']);
        $this->line('List URL: '.$baseUrl.'/admin/payroll/government-filings?company_id='.$data['company_id']);
        $this->line('Detail URL: '.$baseUrl.'/admin/payroll/government-filings/'.$data['filing_id'].'?company_id='.$data['company_id']);

        return self::SUCCESS;
    }
}
