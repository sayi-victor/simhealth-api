<?php

namespace Database\Seeders;

use App\Models\OrderCatalogItem;
use Illuminate\Database\Seeder;

class OrderCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'LAB-CBC', 'name' => 'Full Blood Count (FBC / CBC)', 'order_type' => 'laboratory', 'category' => 'Hematology', 'specimen_type' => 'Whole Blood (EDTA)', 'units' => 'g/dL', 'reference_range' => '12.0 - 16.5', 'unit_price' => '45.00'],
            ['code' => 'LAB-FBG', 'name' => 'Fasting Blood Glucose', 'order_type' => 'laboratory', 'category' => 'Chemistry', 'specimen_type' => 'Fluoride Oxalate Blood', 'units' => 'mmol/L', 'reference_range' => '3.9 - 5.5', 'unit_price' => '25.00'],
            ['code' => 'LAB-MRDT', 'name' => 'Malaria Rapid Diagnostic Test (RDT)', 'order_type' => 'laboratory', 'category' => 'Parasitology', 'specimen_type' => 'Whole Blood', 'units' => null, 'reference_range' => 'Negative', 'unit_price' => '20.00'],
            ['code' => 'LAB-URINE', 'name' => 'Urinalysis (Routine Examination)', 'order_type' => 'laboratory', 'category' => 'Microbiology', 'specimen_type' => 'Midstream Urine', 'units' => null, 'reference_range' => 'Normal', 'unit_price' => '20.00'],
            ['code' => 'IMG-CXR', 'name' => 'Chest X-Ray (PA View)', 'order_type' => 'imaging', 'category' => 'Radiology', 'specimen_type' => null, 'units' => null, 'reference_range' => null, 'unit_price' => '120.00'],
            ['code' => 'IMG-ABDUSS', 'name' => 'Abdominal Ultrasound Scan', 'order_type' => 'imaging', 'category' => 'Ultrasound', 'specimen_type' => null, 'units' => null, 'reference_range' => null, 'unit_price' => '180.00'],
            ['code' => 'PROC-ECG', 'name' => '12-Lead Electrocardiogram (ECG)', 'order_type' => 'procedure', 'category' => 'Cardiology', 'specimen_type' => null, 'units' => null, 'reference_range' => null, 'unit_price' => '95.00'],
            ['code' => 'PROC-DRESS', 'name' => 'Wound Dressing & Debridement', 'order_type' => 'procedure', 'category' => 'Nursing', 'specimen_type' => null, 'units' => null, 'reference_range' => null, 'unit_price' => '60.00'],
        ];

        foreach ($items as $item) {
            OrderCatalogItem::query()->updateOrCreate(
                ['code' => $item['code']],
                $item,
            );
        }
    }
}
