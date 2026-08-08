<?php

namespace Modules\Notification\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Services\NotificationBrowserFixtureService;

final class NotificationBrowserFixtureCommand extends Command
{
    protected $signature = 'notification:browser-fixture {--cleanup}';

    protected $description = 'Create or clean Enterprise Notification Center browser data.';

    public function handle(NotificationBrowserFixtureService $fixture): int
    {
        $database = DB::connection()->getDatabaseName();
        if (! app()->environment(['local', 'testing']) || ! str_ends_with($database, '_testing')) {
            $this->error('Fixture requires local/testing and a *_testing database.');

            return self::FAILURE;
        }
        if ($this->option('cleanup')) {
            $fixture->cleanup();
            $this->info('Notification fixture cleaned.');

            return self::SUCCESS;
        }
        $result = $fixture->create();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
