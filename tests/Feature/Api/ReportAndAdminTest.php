<?php

namespace Tests\Feature\Api;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\WardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportAndAdminTest extends TestCase
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

    public function test_reports_endpoints_enforce_rbac_and_return_aggregates(): void
    {
        $this->getJson('/api/reports/overview')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // Front desk lacks reports.view
        $this->actingAsRole('front_desk');
        $this->getJson('/api/reports/overview')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Seed some clinical activity
        $patient = Patient::factory()->create();
        Encounter::factory()->create(['patient_id' => $patient->id, 'visit_category' => 'outpatient']);

        $this->actingAsRole('clinician');
        $this->getJson('/api/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.patients.total_active', 1)
            ->assertJsonPath('data.encounters.total_in_period', 1)
            ->assertJsonPath('data.inpatient.total_beds', 9);

        $this->getJson('/api/reports/revenue')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'invoices' => ['count', 'total_billed', 'total_paid', 'total_outstanding'],
                    'payments_by_method',
                    'claims_by_status',
                ],
            ]);

        $this->getJson('/api/reports/inventory-alerts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['low_stock_inventory', 'low_stock_drugs', 'expiring_drug_lots'],
            ]);
    }

    public function test_admin_endpoints_enforce_rbac_and_manage_users_facilities_and_audit_logs(): void
    {
        // Clinician cannot access admin user management or audit logs
        $this->actingAsRole('clinician');
        $this->getJson('/api/admin/users')->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->getJson('/api/admin/audit-logs')->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // Admin can list roles, create & update users, create facilities, and inspect audit logs
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $facRes = $this->postJson('/api/facilities', [
            'name' => 'Tema Annex Clinic',
            'code' => 'TEM01',
            'city' => 'Tema',
            'is_service_location' => true,
        ]);
        $facRes->assertCreated()->assertJsonPath('data.code', 'TEM01');
        $facilityId = $facRes->json('data.id');

        $userRes = $this->postJson('/api/admin/users', [
            'facility_id' => $facilityId,
            'username' => 'dr.abena',
            'email' => 'abena@hms.local',
            'password' => 'StrongPass123!',
            'first_name' => 'Abena',
            'last_name' => 'Kwarteng',
            'title' => 'Dr.',
            'is_provider' => true,
            'roles' => ['clinician'],
            'provider_profile' => [
                'specialty' => 'Internal Medicine',
                'npi' => '1234567890',
            ],
        ]);
        $userRes->assertCreated()
            ->assertJsonPath('data.username', 'dr.abena')
            ->assertJsonPath('data.roles', ['clinician']);
        $createdUserId = $userRes->json('data.id');

        // Update user role & deactivate
        $this->putJson("/api/admin/users/{$createdUserId}", [
            'is_active' => false,
            'roles' => ['clinician', 'billing'],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Audit logs contain the admin events
        $this->getJson('/api/admin/audit-logs?category=admin')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}
