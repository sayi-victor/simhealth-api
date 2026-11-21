<?php

namespace Database\Seeders;

use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    public function run(): void
    {
        $wards = [
            ['code' => 'MED-W1', 'name' => 'General Medical Ward', 'category' => 'general', 'floor' => '1st Floor', 'daily_rate' => '150.00', 'beds' => ['M-101', 'M-102', 'M-103']],
            ['code' => 'MAT-W1', 'name' => 'Maternity Ward', 'category' => 'maternity', 'floor' => '2nd Floor', 'daily_rate' => '200.00', 'beds' => ['MAT-201', 'MAT-202', 'MAT-203']],
            ['code' => 'SUR-W1', 'name' => 'Surgical Ward', 'category' => 'surgical', 'floor' => '2nd Floor', 'daily_rate' => '250.00', 'beds' => ['S-301', 'S-302', 'S-303']],
        ];

        foreach ($wards as $w) {
            $beds = $w['beds'];
            unset($w['beds']);

            $ward = Ward::query()->updateOrCreate(['code' => $w['code']], $w);
            foreach ($beds as $bedNum) {
                $ward->beds()->firstOrCreate(['bed_number' => $bedNum], ['status' => 'available']);
            }
        }
    }
}
