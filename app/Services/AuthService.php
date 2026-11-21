<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{token: string, user: User}
     *
     * @throws AuthenticationException
     */
    public function login(string $username, string $password, string $deviceName, Request $request): array
    {
        $user = User::query()
            ->with(['facility', 'roles.permissions'])
            ->where('username', $username)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit->log(
                event: 'auth.login_failed',
                category: 'auth',
                user: $user,
                success: false,
                metadata: ['reason' => 'invalid_credentials'],
                request: $request,
            );

            throw new AuthenticationException('Invalid username or password.');
        }

        if (! $user->is_active) {
            $this->audit->log(
                event: 'auth.login_blocked',
                category: 'auth',
                user: $user,
                success: false,
                metadata: ['reason' => 'account_inactive'],
                request: $request,
            );

            throw new AuthenticationException('Account is inactive.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName)->plainTextToken;

        $this->audit->log(
            event: 'auth.login',
            category: 'auth',
            user: $user,
            auditable: $user,
            success: true,
            request: $request,
        );

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(User $user, Request $request): void
    {
        $user->currentAccessToken()?->delete();

        $this->audit->log(
            event: 'auth.logout',
            category: 'auth',
            user: $user,
            auditable: $user,
            success: true,
            request: $request,
        );
    }
}
