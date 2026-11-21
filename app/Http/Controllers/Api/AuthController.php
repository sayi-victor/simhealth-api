<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            username: $validated['username'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? 'hms-web',
            request: $request,
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['facility', 'roles.permissions']);

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user, $request);

        return response()->noContent();
    }
}
