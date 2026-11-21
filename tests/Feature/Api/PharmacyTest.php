<?php

namespace Tests\Feature\Api;

use App\Models\Drug;
use App\Models\DrugLot;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_pharmacy_requests_return_401(): void
    {
        $this->getJson('/api/prescriptions')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_rbac_separates_prescribing_and_dispensing(): void
    {
        $patient = Patient::factory()->create();
        $rx = Prescription::factory()->create(['patient_id' => $patient->id]);

        // Clinician can prescribe, but cannot dispense or add drug stock
        $this->actingAsRole('clinician');
        $this->postJson("/api/prescriptions/{$rx->id}/dispense", ['quantity' => 5])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Pharmacist can dispense and manage stock, but cannot write new prescriptions
        $this->actingAsRole('pharmacy');
        $this->postJson('/api/prescriptions', [
            'patient_id' => $patient->id,
            'drug_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'frequency' => 'TID',
            'quantity_prescribed' => 21,
        ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_full_formulary_prescribe_and_fefo_dispense_flow(): void
    {
        $patient = Patient::factory()->create();

        // 1. Pharmacist creates formulary drug + 2 stock lots (one expired, one earlier expiry, one later expiry)
        $this->actingAsRole('pharmacy');

        $drugRes = $this->postJson('/api/drugs', [
            'code' => 'AMOX-500',
            'name' => 'Amoxicillin 500mg Capsule',
            'generic_name' => 'Amoxicillin',
            'form' => 'capsule',
            'strength' => '500 mg',
            'route' => 'oral',
            'unit' => 'capsule',
            'unit_price' => '1.50',
            'reorder_level' => 20,
        ]);
        $drugRes->assertCreated()->assertJsonPath('data.code', 'AMOX-500');
        $drugId = $drugRes->json('data.id');

        // Expired lot (must be skipped by FEFO)
        DrugLot::create([
            'drug_id' => $drugId,
            'lot_number' => 'LOT-EXPIRED',
            'quantity_on_hand' => 100,
            'expires_at' => now()->subDays(10)->toDateString(),
            'is_active' => true,
        ]);

        // Earlier valid expiry (should be picked first by FEFO)
        $earlyLotRes = $this->postJson("/api/drugs/{$drugId}/lots", [
            'lot_number' => 'LOT-FEFO-1',
            'quantity_on_hand' => 50,
            'unit_cost' => '0.80',
            'expires_at' => now()->addMonths(3)->toDateString(),
        ]);
        $earlyLotRes->assertCreated();
        $earlyLotId = $earlyLotRes->json('data.id');

        // Later valid expiry
        $this->postJson("/api/drugs/{$drugId}/lots", [
            'lot_number' => 'LOT-LATER-2',
            'quantity_on_hand' => 100,
            'expires_at' => now()->addMonths(12)->toDateString(),
        ])->assertCreated();

        // 2. Clinician prescribes 21 capsules
        $this->actingAsRole('clinician');
        $rxRes = $this->postJson('/api/prescriptions', [
            'patient_id' => $patient->id,
            'drug_id' => $drugId,
            'dosage' => '500 mg (1 cap)',
            'frequency' => 'TID',
            'duration_days' => 7,
            'quantity_prescribed' => 21,
            'indication' => 'Acute pharyngitis',
        ]);
        $rxRes->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.quantity_remaining', 21);
        $rxId = $rxRes->json('data.id');
        $this->assertSame(sprintf('RX-%06d', $rxId), $rxRes->json('data.prescription_number'));

        // 3. Pharmacist dispenses 10 (partial) — FEFO selects LOT-FEFO-1
        $this->actingAsRole('pharmacy');
        $disp1 = $this->postJson("/api/prescriptions/{$rxId}/dispense", [
            'quantity' => 10,
        ]);
        $disp1->assertCreated()
            ->assertJsonPath('data.lot_number', 'LOT-FEFO-1')
            ->assertJsonPath('data.unit_price', '1.50')
            ->assertJsonPath('data.total_price', '15.00');

        $this->assertSame(40, DrugLot::findOrFail($earlyLotId)->quantity_on_hand);
        $this->assertSame('partially_dispensed', Prescription::findOrFail($rxId)->status);

        // Over-dispensing remaining (> 11) is rejected with 422
        $this->postJson("/api/prescriptions/{$rxId}/dispense", [
            'quantity' => 15,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // Dispense remaining 11 -> status becomes dispensed
        $this->postJson("/api/prescriptions/{$rxId}/dispense", [
            'quantity' => 11,
        ])->assertCreated();

        $this->assertSame(29, DrugLot::findOrFail($earlyLotId)->quantity_on_hand);
        $this->assertSame('dispensed', Prescription::findOrFail($rxId)->status);
    }

    public function test_can_cancel_pending_prescription_and_block_dispensing_it(): void
    {
        $drug = Drug::factory()->create();
        $rx = Prescription::factory()->create(['drug_id' => $drug->id, 'status' => 'pending']);

        $this->actingAsRole('clinician');
        $this->postJson("/api/prescriptions/{$rx->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAsRole('pharmacy');
        $this->postJson("/api/prescriptions/{$rx->id}/dispense", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
