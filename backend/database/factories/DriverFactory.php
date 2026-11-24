<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+2126########'),
            'license_number' => strtoupper(fake()->unique()->bothify('LIC-######')),
            'license_type' => 'C',
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'employment_date' => now()->subYears(2)->toDateString(),
        ];
    }
}
