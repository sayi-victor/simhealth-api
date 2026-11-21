<?php

namespace Database\Seeders;

use App\Models\AppointmentCategory;
use Illuminate\Database\Seeder;

class AppointmentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'office_visit', 'name' => 'Office Visit', 'color' => '#0f766e', 'default_duration_minutes' => 30, 'sort_order' => 1],
            ['slug' => 'new_patient', 'name' => 'New Patient Consultation', 'color' => '#0369a1', 'default_duration_minutes' => 45, 'sort_order' => 2],
            ['slug' => 'follow_up', 'name' => 'Follow-up Visit', 'color' => '#4f46e5', 'default_duration_minutes' => 20, 'sort_order' => 3],
            ['slug' => 'antenatal', 'name' => 'Antenatal Care', 'color' => '#be185d', 'default_duration_minutes' => 30, 'sort_order' => 4],
            ['slug' => 'procedure', 'name' => 'Clinical Procedure', 'color' => '#b45309', 'default_duration_minutes' => 60, 'sort_order' => 5],
            ['slug' => 'telehealth', 'name' => 'Telehealth Consultation', 'color' => '#6d28d9', 'default_duration_minutes' => 20, 'sort_order' => 6],
        ];

        foreach ($categories as $cat) {
            AppointmentCategory::query()->updateOrCreate(
                ['slug' => $cat['slug']],
                $cat,
            );
        }
    }
}
