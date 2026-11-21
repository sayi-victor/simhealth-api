<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'mrn' => 'PAT-'.fake()->unique()->numerify('######'),
            'facility_id' => Facility::factory(),
            'title' => fake()->randomElement(['Mr.', 'Mrs.', 'Ms.', 'Dr.', null]),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'sex' => fake()->randomElement(['male', 'female']),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'nationality' => 'Ghanaian',
            'phone_cell' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country_code' => 'GH',
            'registration_date' => now()->toDateString(),
            'registration_type' => 'opd',
            'price_level' => 'standard',
        ];
    }
}
