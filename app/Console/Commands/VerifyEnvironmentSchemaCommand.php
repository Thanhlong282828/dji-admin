<?php

namespace App\Console\Commands;

use App\Support\DatabaseSchemaComparator;
use Illuminate\Console\Command;

final class VerifyEnvironmentSchemaCommand extends Command
{
    protected $signature = 'app:verify-environment-schema
        {--production=schema_admin : Left/local-main schema}
        {--development=schema_admin_testing : Right/automation schema}';

    protected $description = 'Read-only comparison of two environment migration/schema contracts';

    public function handle(DatabaseSchemaComparator $comparator): int
    {
        $production = $this->stringOption('production');
        $development = $this->stringOption('development');
        $result = $comparator->compare($production, $development);

        $this->line("{$production}: ".count($result['left_migrations']).' migrations');
        $this->line("{$development}: ".count($result['right_migrations']).' migrations');

        foreach ($result['physical_name_differences'] as $difference) {
            $this->components->warn($difference);
        }

        if (! $result['matches']) {
            foreach ($result['differences'] as $difference) {
                $this->components->error($difference);
            }

            return self::FAILURE;
        }

        $this->components->info('Migration inventory and schema contract match.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }
}
