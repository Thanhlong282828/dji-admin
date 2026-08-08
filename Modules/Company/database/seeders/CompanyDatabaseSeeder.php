<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;

final class CompanyDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CompanySeeder::class);
    }
}
