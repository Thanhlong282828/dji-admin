<?php

namespace Modules\Payroll\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Services\ErpIntegrationBrowserFixtureService;

final class ErpIntegrationBrowserFixtureCommand extends Command
{
    protected $signature = 'payroll:erp-browser-fixture {--cleanup : Remove only marked ERP browser fixtures}';

    protected $description = 'Create or remove a guarded ERP Integration browser fixture.';

    public function handle(ConnectionInterface $db, ErpIntegrationBrowserFixtureService $fixture): int
    {
        $environment = app()->environment();
        $this->line('APP_ENV='.$environment);
        $this->line('DB_DATABASE='.(string) $db->getDatabaseName());
        if ((bool) $this->option('cleanup')) {
            $fixture->cleanup($environment);
            $this->info('ERP browser fixture cleaned.');

            return self::SUCCESS;
        }
        $data = $fixture->create($environment);
        $configuredUrl = config('app.url');
        $base = rtrim(is_string($configuredUrl) ? $configuredUrl : 'http://127.0.0.1:8001', '/');
        $this->info('ERP browser fixture created.');
        $this->line('Login email: '.$data['email']);
        $this->line('Local password: '.$data['password']);
        $this->line('Connections URL: '.$base.'/admin/payroll/erp/connections?company_id='.$data['company_id']);
        $this->line('Mappings URL: '.$base.'/admin/payroll/erp/mappings?company_id='.$data['company_id']);
        $this->line('Exports URL: '.$base.'/admin/payroll/erp/exports?company_id='.$data['company_id']);
        $this->line('Detail URL: '.$base.'/admin/payroll/erp/exports/'.$data['export_id'].'?company_id='.$data['company_id']);

        return self::SUCCESS;
    }
}
