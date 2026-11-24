<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_type_id' => fn () => VehicleType::where('code', 'van')->value('id') ?? VehicleType::query()->value('id'),
            'registration_number' => strtoupper(fake()->unique()->bothify('#####-?-##')),
            'make' => fake()->randomElement(['Renault', 'Peugeot', 'Ford', 'Mercedes-Benz', 'Isuzu', 'Iveco']),
            'model' => fake()->randomElement(['Master', 'Boxer', 'Transit', 'Sprinter', 'NPR', 'Daily']),
            'year' => fake()->numberBetween(2016, 2026),
            'color' => fake()->safeColorName(),
            'vin' => strtoupper(fake()->unique()->bothify('VF1##########????')),
            'fuel_type' => 'diesel',
            'capacity' => 1500,
            'capacity_unit' => 'kg',
            'odometer' => fake()->numberBetween(5000, 180000),
            'insurance_expiry_date' => now()->addMonths(8)->toDateString(),
            'registration_expiry_date' => now()->addYear()->toDateString(),
        ];
    }
}
