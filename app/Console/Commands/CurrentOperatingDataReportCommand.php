<?php

namespace App\Console\Commands;

use App\Support\DataQuality\DataQualityDatabaseScope;
use App\Support\Reporting\CurrentOperatingDataReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CurrentOperatingDataReportCommand extends Command
{
    protected $signature = 'app:current-operating-data-report {--company= : Limit to one company ID} {--output= : Output path; defaults to project DU_LIEU_THUC_HIEN_TAI.txt}';

    protected $description = 'Generate a read-only, secret-safe current ERP operating-data snapshot from the active database.';

    public function handle(CurrentOperatingDataReportService $service, DataQualityDatabaseScope $databaseScope): int
    {
        $company = $this->option('company');
        if ($company !== null && (! is_string($company) || ! ctype_digit($company) || (int) $company < 1)) {
            $this->error('--company must be a positive integer.');

            return self::INVALID;
        }
        $requestedOutput = $this->option('output');
        if ($requestedOutput !== null && (! is_string($requestedOutput) || trim($requestedOutput) === '')) {
            $this->error('--output must be a non-empty path.');

            return self::INVALID;
        }
        if ($requestedOutput === null && $databaseScope->metadata()['database_purpose'] !== 'OPERATING') {
            $this->error('The default operating-data report can only be generated from the OPERATING database. Use --output for an explicitly scoped non-operating report.');

            return self::FAILURE;
        }
        $output = $requestedOutput ?? base_path('DU_LIEU_THUC_HIEN_TAI.txt');
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $service->render($company === null ? null : (int) $company));
        $this->info('Current operating data report generated: '.$output);

        return self::SUCCESS;
    }
}
