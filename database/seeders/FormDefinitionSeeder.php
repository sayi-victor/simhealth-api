<?php

namespace Database\Seeders;

use App\Models\FormDefinition;
use Illuminate\Database\Seeder;

class FormDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'slug' => 'excuse_duty',
                'name' => 'Excuse Duty / Medical Certificate',
                'category' => 'clinical',
                'description' => 'Official medical excuse duty certificate for work or school.',
                'fields' => [
                    ['field_key' => 'start_date', 'label' => 'Excuse From Date', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'end_date', 'label' => 'Excuse To Date', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'days_off', 'label' => 'Number of Days', 'field_type' => 'number', 'is_required' => true, 'sort_order' => 3],
                    ['field_key' => 'recommendation', 'label' => 'Recommendation', 'field_type' => 'select', 'is_required' => true, 'sort_order' => 4, 'options' => ['Bed rest', 'Light duty', 'Fit for work']],
                    ['field_key' => 'remarks', 'label' => 'Clinical Remarks', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 5],
                ],
            ],
            [
                'slug' => 'treatment_sheet',
                'name' => 'Treatment Sheet',
                'category' => 'nursing',
                'description' => 'Medication administration and clinical treatment record.',
                'fields' => [
                    ['field_key' => 'treatment_name', 'label' => 'Treatment / Procedure', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'dosage', 'label' => 'Dosage / Details', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 2],
                    ['field_key' => 'route', 'label' => 'Route', 'field_type' => 'select', 'is_required' => false, 'sort_order' => 3, 'options' => ['Oral', 'IV', 'IM', 'SC', 'Topical', 'Inhalation']],
                    ['field_key' => 'administered_at', 'label' => 'Administered Date', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 4],
                    ['field_key' => 'response', 'label' => 'Patient Response / Notes', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 5],
                ],
            ],
            [
                'slug' => 'wound_monitoring',
                'name' => 'Wound Monitoring',
                'category' => 'nursing',
                'description' => 'Wound assessment, dressing, and healing progress chart.',
                'fields' => [
                    ['field_key' => 'location', 'label' => 'Wound Location', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'appearance', 'label' => 'Appearance', 'field_type' => 'select', 'is_required' => true, 'sort_order' => 2, 'options' => ['Clean / healing', 'Granulating', 'Sloughy', 'Infected / purulent', 'Necrotic']],
                    ['field_key' => 'dressing_used', 'label' => 'Dressing Used', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 3],
                    ['field_key' => 'notes', 'label' => 'Assessment Notes', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 4],
                ],
            ],
            [
                'slug' => 'inhouse_referral',
                'name' => 'In-House Referral',
                'category' => 'clinical',
                'description' => 'Internal specialist or department referral form.',
                'fields' => [
                    ['field_key' => 'target_department', 'label' => 'Target Department / Clinic', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'urgency', 'label' => 'Urgency', 'field_type' => 'select', 'is_required' => true, 'sort_order' => 2, 'options' => ['Routine', 'Urgent', 'Emergency']],
                    ['field_key' => 'reason', 'label' => 'Reason for Referral', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $fields = $def['fields'];
            unset($def['fields']);

            $form = FormDefinition::query()->updateOrCreate(
                ['slug' => $def['slug']],
                $def,
            );

            foreach ($fields as $field) {
                $form->fields()->updateOrCreate(
                    ['field_key' => $field['field_key']],
                    $field,
                );
            }
        }
    }
}
