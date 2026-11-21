<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            FormDefinitionSeeder::class,
            AppointmentCategorySeeder::class,
            OrderCatalogSeeder::class,
            WardSeeder::class,
        ]);

        $facility = Facility::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Bengel Medical Center',
                'is_service_location' => true,
                'is_billing_location' => true,
                'is_primary' => true,
                'is_active' => true,
            ],
        );

        $admin = User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'facility_id' => $facility->id,
                'email' => 'admin@hms.local',
                'password' => 'ChangeMe123!',
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'is_provider' => false,
                'is_active' => true,
            ],
        );

        $adminRole = Role::query()->where('slug', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
