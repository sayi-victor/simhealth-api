<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->addDays(1)->setTime(9, 0);

        return [
            'patient_id' => Patient::factory(),
            'provider_id' => User::factory()->provider(),
            'facility_id' => Facility::factory(),
            'title' => 'Clinic Appointment',
            'starts_at' => $start,
            'ends_at' => (clone $start)->addMinutes(30),
            'duration_minutes' => 30,
            'all_day' => false,
            'status' => 'scheduled',
            'reason' => 'Scheduled consultation',
        ];
    }
}
