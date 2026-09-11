<?php

namespace App\Console\Commands;

use App\Support\Reporting\CurrentOperatingDataReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CurrentOperatingDataReportCommand extends Command
{
    protected $signature = 'app:current-operating-data-report {--company= : Limit to one company ID} {--output= : Output path; defaults to project DU_LIEU_THUC_HIEN_TAI.txt}';

    protected $description = 'Generate a read-only, secret-safe current ERP operating-data snapshot from the active database.';

    public function handle(CurrentOperatingDataReportService $service): int
    {
        $company = $this->option('company');
        if ($company !== null && (! ctype_digit((string) $company) || (int) $company < 1)) {
            $this->error('--company must be a positive integer.');

            return self::INVALID;
        }
        $output = (string) ($this->option('output') ?: base_path('DU_LIEU_THUC_HIEN_TAI.txt'));
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $service->render($company === null ? null : (int) $company));
        $this->info('Current operating data report generated: '.$output);

        return self::SUCCESS;
    }
}
