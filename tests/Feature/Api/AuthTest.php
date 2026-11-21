<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_user_can_login_and_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'dr.mensah',
            'password' => 'Secret123!',
        ]);
        $user->roles()->attach(Role::where('slug', 'clinician')->firstOrFail()->id);

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'dr.mensah',
            'password' => 'Secret123!',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'username', 'full_name', 'roles', 'permissions', 'facility'],
                ],
            ])
            ->assertJsonPath('data.user.username', 'dr.mensah')
            ->assertJsonPath('data.user.roles', ['clinician']);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', 'dr.mensah');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login',
            'success' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.logout',
            'success' => true,
        ]);
    }

    public function test_login_validation_error_uses_standard_error_envelope(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['username', 'password'],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials_and_records_audit_log(): void
    {
        $user = User::factory()->create([
            'username' => 'nurse.ama',
            'password' => 'CorrectPass123!',
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'nurse.ama',
            'password' => 'WrongPass',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertTrue(
            AuditLog::where('user_id', $user->id)
                ->where('event', 'auth.login_failed')
                ->where('success', false)
                ->exists()
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'username' => 'inactive.user',
            'password' => 'Secret123!',
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'inactive.user',
            'password' => 'Secret123!',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
