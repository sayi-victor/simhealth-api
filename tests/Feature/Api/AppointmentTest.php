<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentCategory;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppointmentCategorySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, AppointmentCategorySeeder::class]);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_calendar_requests_return_401(): void
    {
        $this->getJson('/api/appointments')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_rbac_enforces_calendar_permissions(): void
    {
        $patient = Patient::factory()->create();

        // Lab role has no calendar permissions
        $this->actingAsRole('lab');
        $this->getJson('/api/appointments')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Nurse can view calendar, but cannot book/manage appointments
        $this->actingAsRole('nurse');
        $this->getJson('/api/appointments')->assertOk();
        $this->postJson('/api/appointments', [
            'patient_id' => $patient->id,
            'starts_at' => '2026-11-25 09:00:00',
        ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_can_book_appointment_and_detect_provider_conflict(): void
    {
        $this->actingAsRole('front_desk');
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();
        $provider = User::factory()->provider()->create();
        $category = AppointmentCategory::where('slug', 'office_visit')->firstOrFail();

        // Categories endpoint returns seeded categories
        $this->getJson('/api/appointment-categories')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        // Book first appointment (09:00 - 09:30 via category default duration)
        $res = $this->postJson('/api/appointments', [
            'patient_id' => $patientA->id,
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'starts_at' => '2026-11-25 09:00:00',
            'reason' => 'Annual physical',
            'room' => 'Room 2',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.category.slug', 'office_visit');

        // Overlapping appointment for the same provider (09:15 - 09:45) fails with 422
        $this->postJson('/api/appointments', [
            'patient_id' => $patientB->id,
            'provider_id' => $provider->id,
            'starts_at' => '2026-11-25 09:15:00',
            'duration_minutes' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['starts_at']]]);

        // Adjacent slot (09:30 - 10:00) succeeds
        $this->postJson('/api/appointments', [
            'patient_id' => $patientB->id,
            'provider_id' => $provider->id,
            'starts_at' => '2026-11-25 09:30:00',
            'duration_minutes' => 30,
        ])->assertCreated();
    }

    public function test_can_update_status_check_in_with_encounter_and_cancel(): void
    {
        $this->actingAsRole('clinician');
        $appointment = Appointment::factory()->create([
            'starts_at' => '2026-11-26 10:00:00',
            'ends_at' => '2026-11-26 10:30:00',
            'reason' => 'Blood pressure follow-up',
        ]);

        // Confirm status
        $this->postJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // Check-in and auto-create linked encounter
        $checkInRes = $this->postJson("/api/appointments/{$appointment->id}/check-in", [
            'create_encounter' => true,
        ]);

        $checkInRes->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
        $this->assertNotNull($checkInRes->json('data.encounter_id'));
        $this->assertNotNull($checkInRes->json('data.checked_in_at'));

        // Filter by date window and status
        $this->getJson('/api/appointments?date_from=2026-11-26&date_to=2026-11-26&status=checked_in')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Cancel another appointment
        $appt2 = Appointment::factory()->create();
        $this->postJson("/api/appointments/{$appt2->id}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Patient requested reschedule',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Patient requested reschedule');

        // Delete (soft delete)
        $this->deleteJson("/api/appointments/{$appt2->id}")
            ->assertNoContent();
        $this->assertSoftDeleted('appointments', ['id' => $appt2->id]);
    }
}
