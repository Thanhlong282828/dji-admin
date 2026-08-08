<?php

namespace Modules\Workflow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Workflow\Domain\Services\WorkflowBrowserFixtureService;

final class WorkflowBrowserFixtureCommand extends Command
{
    protected $signature = 'workflow:browser-fixture {--cleanup}';

    protected $description = 'Create or clean the guarded Enterprise Workflow browser fixture.';

    public function handle(ConnectionInterface $connection, WorkflowBrowserFixtureService $fixture): int
    {
        $database = (string) $connection->getDatabaseName();
        if (! app()->environment(['local', 'testing']) || ! str_ends_with($database, '_testing')) {
            $this->error('Fixture is restricted to local/testing with a *_testing database.');

            return self::FAILURE;
        }
        if ((bool) $this->option('cleanup')) {
            $fixture->cleanup();
            $this->info('Workflow browser fixture cleaned.');

            return self::SUCCESS;
        }
        $data = $fixture->create();
        $this->line('Login: workflow.approver1@schema.test / 123456');
        $this->line(url('/admin/workflows?company_id='.$data['company_id']));
        $this->line(url('/admin/workflows/instances/'.$data['instance_id'].'?company_id='.$data['company_id']));

        return self::SUCCESS;
    }
}
