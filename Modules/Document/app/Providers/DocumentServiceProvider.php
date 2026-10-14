<?php

namespace Modules\Document\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Document\Application\Services\DocumentMalwareScannerInterface;
use Modules\Document\Console\Commands\ReconcileDocumentStorageCommand;
use Modules\Document\Console\Commands\ReconcileTenantDocumentStorageCommand;
use Modules\Document\Console\Commands\StorageRuntimeReadinessCommand;
use Modules\Document\Infrastructure\Security\ClamAvDocumentMalwareScanner;
use Modules\Document\Infrastructure\Security\NullDocumentMalwareScanner;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DocumentServiceProvider extends ModuleServiceProvider
{
    /** @var string[] */
    protected array $commands = [ReconcileDocumentStorageCommand::class, ReconcileTenantDocumentStorageCommand::class, StorageRuntimeReadinessCommand::class];

    public function register(): void
    {
        parent::register();
        $this->app->bind(DocumentMalwareScannerInterface::class, fn () => config('document.malware_scanner') === 'clamav' ? new ClamAvDocumentMalwareScanner : new NullDocumentMalwareScanner);
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Document';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'document';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        if (! config('document.reconciliation.schedule_enabled', false)) {
            return;
        }

        $schedule->command('documents:storage-reconcile-tenants')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
    }
}
