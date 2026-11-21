<?php

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Encounter> */
class EncounterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'encounter_number' => 'ENC-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'facility_id' => Facility::factory(),
            'provider_id' => User::factory()->provider(),
            'encounter_date' => now(),
            'visit_category' => 'outpatient',
            'sensitivity' => 'normal',
            'status' => 'open',
            'reason' => 'General clinical consultation',
            'price_level' => 'standard',
        ];
    }
}
