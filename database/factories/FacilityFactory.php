<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Facility> */
class FacilityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Clinic',
            'code' => strtoupper(fake()->unique()->lexify('FAC???')),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'city' => fake()->city(),
            'country_code' => 'GH',
            'is_service_location' => true,
            'is_billing_location' => true,
            'accepts_assignment' => true,
            'is_primary' => false,
            'is_active' => true,
        ];
    }
}
