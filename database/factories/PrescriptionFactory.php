<?php

namespace Database\Factories;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prescription> */
class PrescriptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'prescription_number' => 'RX-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'provider_id' => User::factory()->provider(),
            'drug_id' => Drug::factory(),
            'drug_name' => 'Amoxicillin 500mg',
            'dosage' => '500 mg',
            'route' => 'oral',
            'frequency' => 'TID',
            'duration_days' => 7,
            'quantity_prescribed' => 21,
            'quantity_dispensed' => 0,
            'status' => 'pending',
            'prescribed_at' => now(),
        ];
    }
}
