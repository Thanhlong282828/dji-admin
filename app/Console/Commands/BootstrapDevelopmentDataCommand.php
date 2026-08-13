<?php

namespace App\Console\Commands;

use Database\Seeders\LocalDemoOperationalSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Modules\Company\Database\Seeders\CompanyDatabaseSeeder;
use Modules\Employee\Database\Seeders\EmployeeDatabaseSeeder;
use Modules\Onboarding\Database\Seeders\OnboardingDatabaseSeeder;
use Modules\Organization\Database\Seeders\OrganizationDatabaseSeeder;
use Modules\Recruitment\Database\Seeders\RecruitmentDatabaseSeeder;

final class BootstrapDevelopmentDataCommand extends Command
{
    protected $signature = 'app:bootstrap-development-data';

    protected $description = 'Deprecated alias: idempotently create safe synthetic data for the local main database';

    public function handle(): int
    {
        $database = $this->databaseName();

        if (! app()->environment('local') || $database !== 'schema_admin') {
            $this->components->error('Refusing to bootstrap outside APP_ENV=local and schema_admin.');

            return self::FAILURE;
        }

        foreach ([
            CompanyDatabaseSeeder::class,
            OrganizationDatabaseSeeder::class,
            EmployeeDatabaseSeeder::class,
            RecruitmentDatabaseSeeder::class,
            OnboardingDatabaseSeeder::class,
            LocalDemoOperationalSeeder::class,
        ] as $seeder) {
            $exitCode = Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            if ($exitCode !== self::SUCCESS) {
                $this->components->error("Development seeder failed: {$seeder}");

                return self::FAILURE;
            }
        }

        $this->components->info('Synthetic development data bootstrap completed.');
        $this->line("Database: {$database}");
        $this->line('Dataset: Company, Organization, Employee, Recruitment and Onboarding');

        return self::SUCCESS;
    }

    private function databaseName(): string
    {
        $connection = config('database.default');
        $database = is_string($connection) ? config("database.connections.{$connection}.database") : null;

        return is_string($database) ? $database : '';
    }
}
