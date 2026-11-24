<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'contact_email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'country' => 'MA',
            'city' => 'Casablanca',
            'address' => fake()->streetAddress(),
            'timezone' => 'Africa/Casablanca',
            'currency' => 'MAD',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Company $c) {
            $c->slug ??= Str::slug($c->name).'-'.Str::lower(Str::random(5));
            $c->status ??= CompanyStatus::Active;
            $c->owner_id ??= User::factory()->create()->id;
        });
    }

    public function suspended(): static
    {
        return $this->afterMaking(function (Company $c) {
            $c->status = CompanyStatus::Suspended;
            $c->suspended_at = now();
        });
    }
}
