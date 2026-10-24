<?php

namespace App\Console\Commands;

use App\Support\DataQuality\DataQualityRemediationAuditService;
use Illuminate\Console\Command;
use RuntimeException;

final class DataQualityRemediationAuditCommand extends Command
{
    protected $signature = 'operations:data-quality-remediation-audit
        {--expected-database= : Exact configured and actual database name}
        {--company= : Optional positive company ID}
        {--json : Emit deterministic redacted JSON}';

    protected $description = 'Dry-run detailed audit for approved DQ remediation findings; never mutates records.';

    public function handle(DataQualityRemediationAuditService $service): int
    {
        try {
            $companyOption = $this->option('company');
            $company = null;
            if ($companyOption !== null) {
                if (! is_string($companyOption) || ! ctype_digit($companyOption)) {
                    throw new RuntimeException('Company scope must be a positive integer.');
                }
                $company = intval($companyOption);
            }
            $expectedDatabaseOption = $this->option('expected-database');
            $expectedDatabase = is_string($expectedDatabaseOption) ? $expectedDatabaseOption : null;
            $snapshot = $service->snapshot($company, $expectedDatabase);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rules = $snapshot['rules'] ?? null;
            $checksum = $snapshot['checksum'] ?? null;
            if (! is_array($rules) || ! is_string($checksum)) {
                throw new RuntimeException('The remediation audit returned an invalid result.');
            }
            $rows = [];
            foreach ($rules as $rule) {
                if (! is_array($rule) || ! is_string($rule['rule_id'] ?? null) || ! is_int($rule['count'] ?? null)) {
                    throw new RuntimeException('The remediation audit returned an invalid rule result.');
                }
                $rows[] = [$rule['rule_id'], (string) $rule['count']];
            }
            $this->table(['Rule', 'Records'], $rows);
            $this->info('Mode: DRY_RUN_READ_ONLY; mutations: 0; checksum: '.$checksum);
        }

        return self::SUCCESS;
    }
}
