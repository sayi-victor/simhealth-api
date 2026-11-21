<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/facilities')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_user_without_permission_receives_403_envelope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/facilities')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_authorized_user_can_list_filter_and_view_facilities(): void
    {
        $facility = Facility::factory()->create([
            'name' => 'Accra Central Clinic',
            'code' => 'ACC01',
        ]);
        Facility::factory()->create([
            'name' => 'Kumasi Annex',
            'code' => 'KUM02',
        ]);

        $user = User::factory()->create(['facility_id' => $facility->id]);
        $user->roles()->attach(Role::where('slug', 'front_desk')->firstOrFail()->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/facilities?search=Accra')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ACC01')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson("/api/facilities/{$facility->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Accra Central Clinic');

        $this->getJson('/api/facilities/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
