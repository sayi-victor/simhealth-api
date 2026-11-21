<?php

namespace Tests\Feature\Api;

use App\Models\Encounter;
use App\Models\FormDefinition;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FormDefinitionSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EncounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, FormDefinitionSeeder::class]);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_encounter_requests_return_401(): void
    {
        $this->getJson('/api/encounters')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_rbac_enforces_encounter_permissions(): void
    {
        $encounter = Encounter::factory()->create();

        // Front desk has no encounter permissions
        $this->actingAsRole('front_desk');
        $this->getJson('/api/encounters')->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // Nurse can view/create/update encounters, but cannot sign
        $this->actingAsRole('nurse');
        $this->getJson('/api/encounters')->assertOk();
        $this->postJson("/api/encounters/{$encounter->id}/sign")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_can_create_encounter_record_vitals_notes_and_sign(): void
    {
        $clinician = $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        // Create encounter
        $createRes = $this->postJson('/api/encounters', [
            'patient_id' => $patient->id,
            'visit_category' => 'outpatient',
            'reason' => 'Persistent headache and mild fever',
            'onset_date' => '2026-11-18',
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.provider_id', $clinician->id)
            ->assertJsonPath('data.status', 'open');

        $encounterId = $createRes->json('data.id');
        $this->assertSame(sprintf('ENC-%06d', $encounterId), $createRes->json('data.encounter_number'));

        // Record vitals and verify auto-BMI calculation (70kg / 1.75m^2 = 22.9 Normal)
        $this->postJson("/api/encounters/{$encounterId}/vitals", [
            'bp_systolic' => 122,
            'bp_diastolic' => 78,
            'pulse' => 74,
            'temperature' => 37.8,
            'temp_method' => 'oral',
            'weight_kg' => 70,
            'height_cm' => 175,
            'oxygen_saturation' => 98.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bmi', '22.9')
            ->assertJsonPath('data.bmi_status', 'Normal');

        // Add SOAP consultation note
        $this->postJson("/api/encounters/{$encounterId}/notes", [
            'note_type' => 'soap',
            'title' => 'Initial Consultation',
            'subjective' => 'Patient reports 2-day headache.',
            'objective' => 'Alert, oriented, afebrile.',
            'assessment' => 'Tension headache.',
            'plan' => 'Hydration, paracetamol PRN, follow up in 3 days.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.note_type', 'soap');

        // Sign encounter
        $this->postJson("/api/encounters/{$encounterId}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');

        // Signed encounter rejects further edits
        $this->putJson("/api/encounters/{$encounterId}", [
            'reason' => 'Attempted edit after sign',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_lbf_form_engine_lists_definitions_and_validates_submissions(): void
    {
        $this->actingAsRole('clinician');
        $encounter = Encounter::factory()->create();

        // List form definitions
        $this->getJson('/api/form-definitions')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $excuseDuty = FormDefinition::where('slug', 'excuse_duty')->firstOrFail();

        // Missing required fields & invalid select option -> 422
        $this->postJson("/api/encounters/{$encounter->id}/forms", [
            'form_definition_id' => $excuseDuty->id,
            'data' => [
                'recommendation' => 'Invalid Option',
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['data.start_date', 'data.end_date', 'data.days_off', 'data.recommendation'],
                ],
            ]);

        // Valid dynamic submission succeeds
        $this->postJson("/api/encounters/{$encounter->id}/forms", [
            'form_definition_id' => $excuseDuty->id,
            'data' => [
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-22',
                'days_off' => 3,
                'recommendation' => 'Bed rest',
                'remarks' => 'Rest and fluids.',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.form_slug', 'excuse_duty')
            ->assertJsonPath('data.data.days_off', 3);
    }

    public function test_can_filter_and_search_encounters(): void
    {
        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create(['first_name' => 'Yaw', 'last_name' => 'Adjei']);
        Encounter::factory()->create([
            'patient_id' => $patient->id,
            'visit_category' => 'emergency',
            'reason' => 'Acute asthma exacerbation',
        ]);
        Encounter::factory()->create([
            'visit_category' => 'outpatient',
            'reason' => 'Routine checkup',
        ]);

        $this->getJson('/api/encounters?visit_category=emergency&search=Adjei')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient.id', $patient->id);
    }
}
