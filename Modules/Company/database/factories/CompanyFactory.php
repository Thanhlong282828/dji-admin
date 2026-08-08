<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Domain\Models\Company;

/** @extends Factory<Company> */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('SCHEMA-??-###'));

        return ['code' => $code, 'name' => fake()->company(), 'country_code' => 'VN', 'currency' => 'VND', 'currency_code' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'is_active' => true];
    }
}
