<?php

namespace Tests\Feature\Api;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\WardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, WardSeeder::class]);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_and_unauthorized_clinical_module_requests_are_rejected(): void
    {
        $this->getJson('/api/admissions')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->actingAsRole('front_desk');
        $this->getJson('/api/admissions')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_inpatient_admit_bed_conflict_transfer_and_discharge_flow(): void
    {
        $this->actingAsRole('clinician');
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        $this->getJson('/api/wards')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $bed1 = Bed::where('bed_number', 'M-101')->firstOrFail();
        $bed2 = Bed::where('bed_number', 'S-301')->firstOrFail();

        // Admit patient A to bed M-101
        $admRes = $this->postJson('/api/admissions', [
            'patient_id' => $patientA->id,
            'bed_id' => $bed1->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Severe malaria',
        ]);
        $admRes->assertCreated()
            ->assertJsonPath('data.status', 'admitted')
            ->assertJsonPath('data.bed.bed_number', 'M-101');
        $admId = $admRes->json('data.id');
        $this->assertSame(sprintf('ADM-%06d', $admId), $admRes->json('data.admission_number'));
        $this->assertSame('occupied', $bed1->fresh()->status);

        // Admitting patient B to occupied bed M-101 fails with 422
        $this->postJson('/api/admissions', [
            'patient_id' => $patientB->id,
            'bed_id' => $bed1->id,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // Transfer patient A from M-101 -> S-301
        $this->postJson("/api/admissions/{$admId}/transfer", ['bed_id' => $bed2->id])
            ->assertOk()
            ->assertJsonPath('data.bed.bed_number', 'S-301');
        $this->assertSame('available', $bed1->fresh()->status);
        $this->assertSame('occupied', $bed2->fresh()->status);

        // Discharge patient A -> bed S-301 freed back to available
        $this->postJson("/api/admissions/{$admId}/discharge", [
            'discharge_diagnosis' => 'Resolved severe malaria',
            'discharge_summary' => 'Completed IV artesunate, transitioned to oral ACT.',
            'discharge_disposition' => 'home',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'discharged');
        $this->assertSame('available', $bed2->fresh()->status);
    }

    public function test_surgery_schedule_and_completion_and_immunization_recording(): void
    {
        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        // Schedule surgery
        $surgRes = $this->postJson('/api/surgeries', [
            'patient_id' => $patient->id,
            'procedure_name' => 'Laparoscopic Appendectomy',
            'theater_room' => 'OR-2',
            'priority' => 'urgent',
            'scheduled_at' => '2026-11-21 08:30:00',
            'anesthesia_type' => 'general',
            'consent_signed' => true,
            'pre_op_diagnosis' => 'Acute appendicitis',
        ]);
        $surgRes->assertCreated()->assertJsonPath('data.status', 'scheduled');
        $surgId = $surgRes->json('data.id');
        $this->assertSame(sprintf('SRG-%06d', $surgId), $surgRes->json('data.surgery_number'));

        // Complete surgery
        $this->postJson("/api/surgeries/{$surgId}/complete", [
            'post_op_diagnosis' => 'Acute uncomplicated appendicitis',
            'operative_notes' => 'Procedure completed without complications.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Record immunization
        $this->postJson('/api/immunizations', [
            'patient_id' => $patient->id,
            'vaccine_name' => 'Hepatitis B Vaccine',
            'cvx_code' => '08',
            'dose_number' => 1,
            'dose_amount' => '0.50',
            'dose_unit' => 'mL',
            'route' => 'im',
            'site' => 'left_deltoid',
            'lot_number' => 'VAX-2026-A1',
            'next_due_date' => '2026-12-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.vaccine_name', 'Hepatitis B Vaccine')
            ->assertJsonPath('data.dose_number', 1);

        $this->getJson("/api/immunizations?patient_id={$patient->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
