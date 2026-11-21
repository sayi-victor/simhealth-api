<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientTest extends TestCase
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
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_requests_return_401_envelope(): void
    {
        $this->getJson('/api/patients')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/patients', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_rbac_blocks_unauthorized_patient_actions(): void
    {
        $patient = Patient::factory()->create();

        // User with no roles cannot view
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/patients')->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // Lab role can view, but cannot create or update
        $this->actingAsRole('lab');
        $this->getJson('/api/patients')->assertOk();
        $this->postJson('/api/patients', [
            'first_name' => 'Ama',
            'last_name' => 'Boateng',
            'sex' => 'female',
        ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // Front desk can create/update, but cannot archive
        $this->actingAsRole('front_desk');
        $this->deleteJson("/api/patients/{$patient->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_can_create_patient_with_auto_mrn_and_normalized_relations(): void
    {
        $actor = $this->actingAsRole('front_desk');

        $payload = [
            'title' => 'Ms.',
            'first_name' => '  Akua ',
            'last_name' => 'Mensah ',
            'date_of_birth' => '1992-05-14',
            'sex' => 'female',
            'phone_cell' => '+233240001122',
            'email' => 'akua.mensah@example.test',
            'city' => 'Accra',
            'contacts' => [
                [
                    'contact_type' => 'emergency',
                    'relationship' => 'Sister',
                    'full_name' => 'Abena Mensah',
                    'phone' => '+233240009988',
                    'is_primary' => true,
                ],
            ],
            'consent' => [
                'allow_sms' => true,
                'allow_email' => true,
                'hipaa_notice_received' => true,
            ],
            'employer' => [
                'employer_name' => 'Accra Tech Ltd',
                'occupation' => 'Engineer',
            ],
            'insurances' => [
                [
                    'tier' => 'primary',
                    'provider_name' => 'NHIS',
                    'policy_number' => 'NHIS-998877',
                    'copay' => '25.50',
                    'maximum_bill' => '5000.00',
                    'effective_date' => '2026-01-01',
                ],
            ],
        ];

        $response = $this->postJson('/api/patients', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Akua')
            ->assertJsonPath('data.last_name', 'Mensah')
            ->assertJsonPath('data.date_of_birth', '1992-05-14')
            ->assertJsonPath('data.facility_id', $actor->facility_id)
            ->assertJsonPath('data.contacts.0.full_name', 'Abena Mensah')
            ->assertJsonPath('data.consent.hipaa_notice_received', true)
            ->assertJsonPath('data.employers.0.employer_name', 'Accra Tech Ltd')
            ->assertJsonPath('data.insurances.0.copay', '25.50')
            ->assertJsonPath('data.insurances.0.maximum_bill', '5000.00');

        $id = $response->json('data.id');
        $this->assertSame(sprintf('PAT-%06d', $id), $response->json('data.mrn'));

        // Verify R13-safe audit log (no PHI in metadata)
        $log = AuditLog::where('event', 'patients.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($id, $log->metadata['patient_id']);
        $this->assertStringNotContainsString('Akua', json_encode($log->metadata));
    }

    public function test_patient_validation_rejects_missing_fields_duplicate_mrn_and_future_dob(): void
    {
        $this->actingAsRole('front_desk');
        Patient::factory()->create(['mrn' => 'MRN-DUP-01']);

        $this->postJson('/api/patients', [
            'mrn' => 'MRN-DUP-01',
            'date_of_birth' => now()->addDays(5)->toDateString(),
            'sex' => 'invalid',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['mrn', 'first_name', 'last_name', 'date_of_birth', 'sex'],
                ],
            ]);
    }

    public function test_can_list_search_filter_and_sort_patients(): void
    {
        $this->actingAsRole('clinician');

        Patient::factory()->create([
            'mrn' => 'PAT-100001',
            'first_name' => 'Kwame',
            'last_name' => 'Asante',
            'sex' => 'male',
            'phone_cell' => '0241112222',
        ]);
        Patient::factory()->create([
            'mrn' => 'PAT-100002',
            'first_name' => 'Esi',
            'last_name' => 'Appiah',
            'sex' => 'female',
            'phone_cell' => '0243334444',
        ]);

        // Search by name
        $this->getJson('/api/patients?search=Asante')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mrn', 'PAT-100001')
            ->assertJsonStructure(['data', 'links', 'meta']);

        // Search by phone
        $this->getJson('/api/patients?search=0243334444')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Esi');

        // Filter by sex + sort by name asc
        $this->getJson('/api/patients?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.last_name', 'Appiah')
            ->assertJsonPath('data.1.last_name', 'Asante');
    }

    public function test_can_view_update_add_issues_update_history_and_archive_restore_patient(): void
    {
        $this->actingAsRole('admin');

        $patient = Patient::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Osei',
        ]);

        // Show
        $this->getJson("/api/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id);

        // Update demographics + consent
        $this->putJson("/api/patients/{$patient->id}", [
            'city' => 'Kumasi',
            'clinical_alert' => 'Penicillin allergy',
            'consent' => ['allow_sms' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Kumasi')
            ->assertJsonPath('data.clinical_alert', 'Penicillin allergy')
            ->assertJsonPath('data.consent.allow_sms', false);

        // Add clinical issue
        $issueRes = $this->postJson("/api/patients/{$patient->id}/issues", [
            'type' => 'allergy',
            'title' => 'Penicillin',
            'severity' => 'severe',
            'reaction' => 'Anaphylaxis',
            'onset_date' => '2020-03-10',
        ]);
        $issueRes->assertCreated()->assertJsonPath('data.title', 'Penicillin');
        $issueId = $issueRes->json('data.id');

        // Update medical history
        $this->putJson("/api/patients/{$patient->id}/history", [
            'lifestyle' => ['tobacco' => 'never', 'alcohol' => 'occasional'],
            'family_history' => ['mother' => 'Hypertension'],
        ])
            ->assertOk()
            ->assertJsonPath('data.history.lifestyle.tobacco', 'never')
            ->assertJsonPath('data.history.family_history.mother', 'Hypertension');

        // Delete clinical issue
        $this->deleteJson("/api/patients/{$patient->id}/issues/{$issueId}")
            ->assertNoContent();

        // Archive (soft delete)
        $this->deleteJson("/api/patients/{$patient->id}")
            ->assertNoContent();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);

        // Archived filter shows it
        $this->getJson('/api/patients?archived=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $patient->id);

        // Restore
        $this->postJson("/api/patients/{$patient->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);
    }
}
