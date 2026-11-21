<?php

namespace Database\Factories;

use App\Models\Drug;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Drug> */
class DrugFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'DRG-'.fake()->unique()->numerify('#####'),
            'name' => fake()->randomElement(['Paracetamol 500mg', 'Amoxicillin 500mg', 'Ibuprofen 400mg', 'Metformin 500mg', 'Omeprazole 20mg']).' '.fake()->lexify('??'),
            'generic_name' => fake()->word(),
            'form' => 'tablet',
            'strength' => '500 mg',
            'route' => 'oral',
            'unit' => 'tablet',
            'unit_price' => '2.50',
            'reorder_level' => 20,
            'requires_prescription' => true,
            'is_controlled' => false,
            'is_active' => true,
        ];
    }
}
